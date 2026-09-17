<?php

namespace App\Services;

use App\Models\Media;
use App\Repositories\MediaFolderItemRepository;
use App\Repositories\MediaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaService
{
    public function __construct(
        protected MediaRepository $mediaRepository,
        protected MediaFolderItemRepository $folderItemRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->mediaRepository->paginate($filters);
    }

    public function listTrashed(array $filters): LengthAwarePaginator
    {
        return $this->mediaRepository->paginateTrashed($filters);
    }

    public function find(int $id): Media
    {
        return $this->mediaRepository->findById($id);
    }

    public function findByUuid(string $uuid): Media
    {
        return $this->mediaRepository->findByUuid($uuid);
    }

    /**
     * Pipeline upload đầy đủ theo mục 4.5:
     * validate (đã xong ở MediaUploadRequest) -> tính SHA-256 -> duplicate
     * check (chỉ xét Media ACTIVE, trùng hash nhưng đang Trash KHÔNG coi là
     * trùng) -> lưu file gốc -> detect type từ MIME thật -> extract
     * width/height -> generate WebP variants (chỉ image) -> lưu DB ->
     * (tuỳ chọn) attach vào Folder.
     *
     * $data có thể chứa: folder_id, alt, title, description, created_by —
     * đúng field MediaUploadRequest::validated() sẽ trả về ở Phase 6.
     *
     * Quy ước path: {base_path}/{type}/{uuid}.{ext} cho file gốc,
     * {base_path}/image/variants/{uuid}-{variant}.webp cho variant.
     * Dùng uuid làm tên file để tránh trùng lặp/va chạm khi số lượng file lớn.
     */
    public function upload(UploadedFile $file, array $data = []): Media
    {
        $mime = $file->getMimeType();
        $type = $this->resolveType($mime);

        if ($type === null) {
            throw ValidationException::withMessages([
                'file' => 'Định dạng file không được hỗ trợ.',
            ]);
        }

        // Hash TRƯỚC khi lưu file — tính trên nội dung binary thật của file tạm.
        $hash = hash_file('sha256', $file->getRealPath());

        // Duplicate check: chỉ xét Media ACTIVE. Trùng hash nhưng đang Trash
        // KHÔNG coi là trùng (mục 4.5) -> rơi thẳng xuống nhánh tạo mới bên dưới,
        // KHÔNG tự động restore ngầm.
        $existing = $this->mediaRepository->findActiveByHash($hash);

        if ($existing) {
            if (! empty($data['folder_id'])) {
                $this->addToFolder($existing, (int) $data['folder_id']);
            }

            return $existing;
        }

        $disk = config('media.disk');
        $basePath = config('media.base_path');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = "{$uuid}.{$extension}";
        $directory = "{$basePath}/{$type}";

        $storedPath = $file->storeAs($directory, $fileName, $disk);

        if ($storedPath === false) {
            throw new \RuntimeException('Không thể lưu file vào storage.');
        }

        [$width, $height, $duration, $metadata] = $this->extractAttributes($file, $type);

        $media = $this->mediaRepository->create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'extension' => $extension,
            'mime_type' => $mime,
            'type' => $type,
            'size' => $file->getSize(),
            'file_hash' => $hash,
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
            'metadata' => $metadata,
            'alt' => $data['alt'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => config('media.default_status', 'ready'),
            'created_by' => $data['created_by'] ?? null,
        ]);

        if ($type === 'image' && config("media.processing.{$type}.generate_variants")) {
            $this->generateImageVariants($media, $file);
        }

        if (! empty($data['folder_id'])) {
            $this->addToFolder($media, (int) $data['folder_id']);
        }

        return $media->fresh('variants');
    }

    /**
     * Cập nhật metadata SEO/accessibility của Media.
     * Chỉ được phép cập nhật: alt, title, description.
     * Các field file identity/system không được đưa vào $data từ Controller
     * (đã bị loại bỏ bởi UpdateMediaRequest trước khi tới đây).
     *
     * Chain: MediaController -> updateMetadata() -> MediaRepository::update()
     */
    public function updateMetadata(Media $media, array $data): Media
    {
        return $this->mediaRepository->update($media, $data);
    }

    /**
     * Add to Folder = INSERT pivot (mục 4.5). Idempotent: đã thuộc Folder
     * này rồi thì bỏ qua thay vì throw lỗi (UNIQUE constraint DB là lớp bảo
     * vệ cuối, không phải nơi Service coi đây là lỗi nghiệp vụ).
     */
    public function addToFolder(Media $media, int $folderId): void
    {
        if ($this->folderItemRepository->exists($media->id, $folderId)) {
            return;
        }

        $this->folderItemRepository->add($media->id, $folderId);
    }

    /** Remove from Folder = DELETE pivot thật — KHÔNG ảnh hưởng Media/file vật lý (mục 4.5). */
    public function removeFromFolder(Media $media, int $folderId): void
    {
        $this->folderItemRepository->remove($media->id, $folderId);
    }

    /**
     * Move to Folder = xoá pivot cũ + tạo pivot mới trong transaction (mục 4.5).
     * KHÔNG di chuyển physical file, KHÔNG đổi medias.path, KHÔNG regenerate variants.
     */
    public function moveToFolder(Media $media, int $fromFolderId, int $toFolderId): void
    {
        DB::transaction(function () use ($media, $fromFolderId, $toFolderId) {
            $this->folderItemRepository->remove($media->id, $fromFolderId);

            if (! $this->folderItemRepository->exists($media->id, $toFolderId)) {
                $this->folderItemRepository->add($media->id, $toFolderId);
            }
        });
    }

    /** Soft delete — KHÔNG xoá file vật lý/variants/folder relationships (mục 4.5). */
    public function delete(Media $media): void
    {
        $this->mediaRepository->delete($media);
    }

    /**
     * Restore — giữ nguyên toàn bộ folder relationships (mục 4.5). Nhận int
     * $id (không phải Model) vì Media đang trashed không lấy được qua route
     * model binding mặc định — cùng convention MediaRepository::findTrashedById().
     */
    public function restore(int $id): Media
    {
        $media = $this->mediaRepository->findTrashedById($id);

        $this->mediaRepository->restore($media);

        return $media->fresh();
    }

    /**
     * Permanent Delete — đọc path của Media + toàn bộ Variant TRƯỚC KHI
     * forceDelete() (CASCADE xoá DB record ngay lập tức, không còn cách lấy
     * lại path sau đó) -> forceDelete -> xoá file vật lý qua Storage layer
     * SAU KHI DB đã xoá thành công (nếu forceDelete() DB thất bại vì lý do
     * khác, file vật lý vẫn còn nguyên, tránh mất dữ liệu oan).
     */
    public function forceDelete(int $id): void
    {
        $media = $this->mediaRepository->findWithTrashedById($id);

        $originalDisk = $media->disk;
        $originalPath = $media->path;
        $variantFiles = $media->variants()->get(['disk', 'path'])
            ->map(fn ($variant) => ['disk' => $variant->disk, 'path' => $variant->path])
            ->all();

        $this->mediaRepository->forceDelete($media);

        if (Storage::disk($originalDisk)->exists($originalPath)) {
            Storage::disk($originalDisk)->delete($originalPath);
        }

        foreach ($variantFiles as $variant) {
            if (Storage::disk($variant['disk'])->exists($variant['path'])) {
                Storage::disk($variant['disk'])->delete($variant['path']);
            }
        }
    }

    /**
     * Soft delete hàng loạt — lặp qua danh sách ID, tái sử dụng nguyên vẹn
     * logic delete() hiện có cho từng Media, không duplicate business rule.
     *
     * Không bọc transaction: mỗi soft delete là 1 UPDATE độc lập, không có
     * ràng buộc dữ liệu chéo giữa các Media cần đảm bảo atomic cùng lúc.
     * Nếu 1 ID không tồn tại (đã bị xoá bởi request khác, hoặc gõ tay sai),
     * findById() throw ModelNotFoundException dừng vòng lặp — các Media đã
     * xử lý trước đó trong cùng lần gọi vẫn giữ nguyên trạng thái đã xoá
     * (chấp nhận được cho bulk action, không rollback ngược).
     *
     * @param array<int> $ids
     */
    public function bulkDelete(array $ids): void
    {
        foreach ($ids as $id) {
            $media = $this->mediaRepository->findById((int) $id);
            $this->delete($media);
        }
    }

    /**
     * Thêm hàng loạt Media vào 1 Folder — tái sử dụng nguyên vẹn addToFolder()
     * đã có (idempotent per-item: Media nào đã có trong Folder sẽ tự bỏ qua).
     *
     * @param array<int> $ids
     */
    public function bulkAddToFolder(array $ids, int $folderId): void
    {
        foreach ($ids as $id) {
            $media = $this->mediaRepository->findById((int) $id);
            $this->addToFolder($media, $folderId);
        }
    }

    /** Xác định type (image/video/document/...) từ MIME thật — không tin extension client khai báo. */
    protected function resolveType(string $mime): ?string
    {
        foreach (config('media.types', []) as $type => $typeConfig) {
            if (in_array($mime, $typeConfig['mimes'] ?? [], true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Trích width/height cho image bằng getimagesize() (nhẹ hơn decode qua
     * Intervention chỉ để lấy kích thước gốc). video/document/audio/file:
     * CHƯA có xử lý trích xuất (ffprobe/pdfinfo...) ở giai đoạn này — giữ
     * NULL, đúng nguyên tắc "không over-engineer" khi chưa có nhu cầu thực
     * tế xác nhận.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: ?array}
     */
    protected function extractAttributes(UploadedFile $file, string $type): array
    {
        $width = null;
        $height = null;
        $duration = null;
        $metadata = null;

        if ($type === 'image') {
            $size = @getimagesize($file->getRealPath());

            if ($size !== false) {
                $width = $size[0];
                $height = $size[1];
            }
        }

        return [$width, $height, $duration, $metadata];
    }

    /**
     * Generate variants WebP theo config('media.image.variants') — resize
     * bằng scaleDown() (built-in, tự đảm bảo không upscale + giữ aspect
     * ratio, đúng mục 4.2), encode WebP theo config('media.image.webp_quality').
     * Lưu qua Storage layer + $media->variants()->create() TRỰC TIẾP (KHÔNG
     * qua Repository) — đúng quyết định Phase 3: không tạo
     * MediaVariantRepository, Variant chỉ truy cập qua relationship.
     */
    protected function generateImageVariants(Media $media, UploadedFile $file): void
    {
        $manager = new ImageManager(new Driver());
        $variants = config('media.image.variants', []);
        // config('media.image.webp_quality') đọc qua env() -> luôn là string,
        // trong khi Intervention\Image\Encoders\WebpEncoder yêu cầu strict
        // int $quality -> phải cast tại đây (boundary config/env <-> Intervention API).
        $quality = (int) config('media.image.webp_quality', 82);
        $disk = config('media.disk');
        $basePath = config('media.base_path');

        foreach ($variants as $name => $targetWidth) {
            // Đọc lại từ file tạm mỗi vòng lặp (thay vì clone) để không phụ
            // thuộc vào API clone() của Intervention v3 — đơn giản, an toàn.
            $image = $manager->read($file->getRealPath());
            $image->scaleDown(width: $targetWidth);

            $encoded = $image->toWebp($quality);
            $binary = (string) $encoded;

            $variantFileName = "{$media->uuid}-{$name}.webp";
            $variantPath = "{$basePath}/image/variants/{$variantFileName}";

            Storage::disk($disk)->put($variantPath, $binary);

            $media->variants()->create([
                'name' => $name,
                'disk' => $disk,
                'path' => $variantPath,
                'width' => $image->width(),
                'height' => $image->height(),
                'mime_type' => 'image/webp',
                'size' => strlen($binary),
                'metadata' => null,
            ]);
        }
    }
}