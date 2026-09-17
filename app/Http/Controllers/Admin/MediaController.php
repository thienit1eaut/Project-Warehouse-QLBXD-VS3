<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\MediaFolderService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(
        protected MediaService       $mediaService,
        protected MediaFolderService $folderService,
    ) {}

    /**
     * Danh sách Media active — hỗ trợ search/type/status/folder_id/sort
     * theo MediaRepository::paginate() (Phase 7 B3+B6: eager-load variants,
     * filter folder_id qua relationship).
     * Authorization: permission:media.view (route middleware).
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type', 'status', 'folder_id', 'sort', 'direction']);

        return Inertia::render('Admin/Media/Index', [
            'pageTitle'   => 'Quản lý Media',
            'media'       => $this->mediaService->list($filters),
            'rootFolders' => $this->folderService->getRootFolders(),
            'allFolders'  => $this->folderService->listAll(),
            'filters'     => $filters,
        ]);
    }

    /**
     * Media trong Trash (Phase 7 B5) — route tĩnh /trash phải khai báo
     * TRƯỚC route động /{media} trong web.php để không bị bắt nhầm.
     * Authorization: permission:media.restore (xem Trash gắn liền quyền restore).
     */
    /**
     * Danh sách Media dạng ảnh (JSON) — phục vụ Media Picker nhúng trong
     * form của module khác (vd Brand chọn ảnh đại diện). Tái sử dụng nguyên
     * MediaService::list() đã có sẵn (eager-load variants, accessor url,
     * $hidden loại file_hash/disk/path/created_by) — không tạo Service
     * method mới, không duplicate logic.
     *
     * type luôn bị ép cứng = 'image' bất kể query param truyền gì — picker
     * này chỉ phục vụ chọn ảnh, không phải danh sách Media tổng quát.
     * Authorization: permission:media.view (route middleware).
     */
    public function picker(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $filters['type'] = 'image';

        return response()->json(
            $this->mediaService->list($filters)
        );
    }

    public function trash(Request $request): Response
    {
        $filters = $request->only(['search', 'type']);

        return Inertia::render('Admin/Media/Trash', [
            'pageTitle' => 'Thùng rác Media',
            'media'     => $this->mediaService->listTrashed($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Trang riêng Upload (theo convention Create trang riêng của project,
     * không dùng modal). Không cần load dữ liệu gì — form trống.
     * Authorization: permission:media.create (route middleware).
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Media/Upload', [
            'pageTitle' => 'Upload Media',
        ]);
    }

    /**
     * Trang riêng sửa metadata — truyền sẵn data qua Inertia props (đúng
     * convention Category/Brand/Supplier/Unit Edit.vue), không bắt frontend
     * tự fetch qua show() JSON. show() JSON vẫn giữ riêng cho nhu cầu khác
     * (ví dụ preview nhanh không chuyển trang).
     * Authorization: permission:media.update (route middleware).
     */
    public function edit(Media $media): Response
    {
        return Inertia::render('Admin/Media/Edit', [
            'pageTitle' => 'Sửa thông tin Media',
            'media'     => $this->transformMediaDetail($media),
        ]);
    }

    /**
     * Chi tiết 1 Media — trả JSON (dùng cho preview nhanh không chuyển
     * trang, hoặc tích hợp sau này). Không expose: file_hash, created_by,
     * disk, path.
     * Authorization: permission:media.view (route middleware).
     */
    public function show(Media $media): JsonResponse
    {
        return response()->json($this->transformMediaDetail($media));
    }

    /**
     * Chuẩn hoá field trả về cho 1 Media chi tiết — dùng chung cho show()
     * và edit() để không lặp lại danh sách field 2 lần.
     */
    protected function transformMediaDetail(Media $media): array
    {
        $media->load(['variants', 'folders']);

        return [
            'id'            => $media->id,
            'uuid'          => $media->uuid,
            'url'           => $media->url,
            'original_name' => $media->original_name,
            'file_name'     => $media->file_name,
            'extension'     => $media->extension,
            'mime_type'     => $media->mime_type,
            'type'          => $media->type,
            'size'          => $media->size,
            'width'         => $media->width,
            'height'        => $media->height,
            'duration'      => $media->duration,
            'alt'           => $media->alt,
            'title'         => $media->title,
            'description'   => $media->description,
            'status'        => $media->status,
            'created_at'    => $media->created_at,
            'updated_at'    => $media->updated_at,
            'variants'      => $media->variants->map(fn ($v) => [
                'name'   => $v->name,
                'url'    => $v->url,
                'width'  => $v->width,
                'height' => $v->height,
            ]),
            'folders'       => $media->folders->map(fn ($f) => [
                'id'        => $f->id,
                'name'      => $f->name,
                'parent_id' => $f->parent_id,
            ]),
        ];
    }

    /**
     * Upload file mới.
     * Authorization: permission:media.create (route middleware).
     *
     * Trả JSON khi caller gửi Accept: application/json (MediaPicker.vue gọi
     * qua fetch() để upload ảnh mới ngay trong picker, không rời trang) —
     * giữ nguyên redirect + flash message cho trang Upload.vue Inertia
     * thông thường. Không tạo route/Controller method riêng cho 2 luồng
     * này vì cùng 1 business logic (MediaService::upload()), chỉ khác định
     * dạng response.
     */
    public function store(MediaUploadRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $media = $this->mediaService->upload(
                $request->file('file'),
                array_merge($request->validated(), [
                    'created_by' => $request->user()->id,
                ])
            );
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            // $media đã fresh('variants') từ Service — Model $appends/$hidden
            // tự lo phần serialize đúng contract (url, variants[].url, không
            // lộ file_hash/disk/path/created_by), không cần transform thủ công.
            return response()->json($media);
        }

        return back()->with('success', 'Đã upload file thành công.');
    }

    /**
     * Cập nhật metadata SEO — chỉ alt, title, description.
     * Authorization: permission:media.update (route middleware).
     */
    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $this->mediaService->updateMetadata($media, $request->validated());

        return back()->with('success', 'Đã cập nhật thông tin media.');
    }

    /**
     * Soft delete — file vật lý/variants/pivot giữ nguyên.
     * Authorization: permission:media.delete (route middleware).
     */
    public function destroy(Media $media): RedirectResponse
    {
        $this->mediaService->delete($media);

        return back()->with('success', 'Đã chuyển media vào thùng rác.');
    }

    /**
     * Restore từ Trash.
     * Route dùng ->withTrashed() — binding tìm được trashed record.
     * Service nhận int $id (contract hiện tại).
     * Authorization: permission:media.restore (route middleware).
     */
    public function restore(Media $media): RedirectResponse
    {
        $this->mediaService->restore($media->id);

        return back()->with('success', 'Đã khôi phục media.');
    }

    /**
     * Permanent delete — xoá DB + file vật lý + variants.
     * Route dùng ->withTrashed() — binding tìm cả active lẫn trashed.
     * Service nhận int $id (contract hiện tại).
     * Authorization: permission:media.force-delete (route middleware).
     */
    public function forceDelete(Media $media): RedirectResponse
    {
        $this->mediaService->forceDelete($media->id);

        return back()->with('success', 'Đã xoá vĩnh viễn media.');
    }

    /**
     * Soft delete hàng loạt — nhận danh sách ID qua request, tái sử dụng
     * MediaService::bulkDelete() (không duplicate logic soft-delete ở đây).
     * Authorization: permission:media.delete (route middleware).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:medias,id'],
        ]);

        $this->mediaService->bulkDelete($validated['ids']);

        $count = count($validated['ids']);

        return back()->with('success', "Đã chuyển {$count} media vào thùng rác.");
    }

    /**
     * Thêm hàng loạt Media vào 1 Folder — nhận danh sách ID + folder_id,
     * tái sử dụng MediaService::bulkAddToFolder().
     * Authorization: permission:media.manage-folder (route middleware).
     */
    public function bulkAddToFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'       => ['required', 'array', 'min:1'],
            'ids.*'     => ['integer', 'exists:medias,id'],
            'folder_id' => ['required', 'integer', 'exists:media_folders,id'],
        ]);

        $this->mediaService->bulkAddToFolder($validated['ids'], $validated['folder_id']);

        $count = count($validated['ids']);

        return back()->with('success', "Đã thêm {$count} media vào thư mục.");
    }

    /**
     * Gắn Media vào Folder — idempotent.
     * Authorization: permission:media.manage-folder (route middleware).
     */
    public function addToFolder(Media $media, MediaFolder $folder): RedirectResponse
    {
        $this->mediaService->addToFolder($media, $folder->id);

        return back()->with('success', 'Đã thêm media vào thư mục.');
    }

    /**
     * Gỡ Media khỏi Folder — DELETE pivot thật, không ảnh hưởng file.
     * Authorization: permission:media.manage-folder (route middleware).
     */
    public function removeFromFolder(Media $media, MediaFolder $folder): RedirectResponse
    {
        $this->mediaService->removeFromFolder($media, $folder->id);

        return back()->with('success', 'Đã gỡ media khỏi thư mục.');
    }

    /**
     * Di chuyển Media từ Folder này sang Folder khác.
     * Authorization: permission:media.manage-folder (route middleware).
     */
    public function moveToFolder(Media $media, MediaFolder $from, MediaFolder $to): RedirectResponse
    {
        $this->mediaService->moveToFolder($media, $from->id, $to->id);

        return back()->with('success', 'Đã di chuyển media sang thư mục mới.');
    }
}