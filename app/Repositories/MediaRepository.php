<?php

namespace App\Repositories;

use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MediaRepository
{
    /**
     * Danh sách Media active (SoftDeletes tự loại trừ trashed), hỗ trợ
     * search/type/status/folder_id/sort/pagination — cùng pattern CategoryRepository.
     *
     * search: quét original_name, file_name, title, alt, description — đúng
     * nhóm field SEO/định danh mà người dùng có thể nhớ để tìm lại 1 file.
     *
     * folder_id (Phase 7 B6): filter qua relationship Eloquent whereHas() trên
     * bảng pivot media_folder_items — không inject Repository khác vào đây,
     * dùng thẳng quan hệ Media::folders() đã có sẵn.
     *
     * variants (Phase 7 B3): eager-load chỉ các cột cần cho thumbnail UI
     * (id, media_id, name, disk, path, width, height) — không load full row
     * (bỏ mime_type/size/metadata) vì Index chỉ cần build URL + kích thước.
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Media::query()
            ->with(['variants:id,media_id,name,disk,path,width,height'])
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('original_name', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('alt', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(
                $filters['folder_id'] ?? null,
                fn ($q, $folderId) => $q->whereHas('folders', fn ($f) => $f->where('folder_id', $folderId))
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Danh sách Media trong Trash — cùng bộ filter search/type như paginate(),
     * nhưng CHỈ trả record đã soft-delete (onlyTrashed()).
     */
    public function paginateTrashed(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Media::onlyTrashed()
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                })
            )
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** internal id — dùng cho relationship/thao tác nội bộ giữa các bảng. */
    public function findById(int $id): Media
    {
        return Media::findOrFail($id);
    }

    /** public identifier — dùng khi expose Media ra ngoài (API/URL) sau này. */
    public function findByUuid(string $uuid): Media
    {
        return Media::where('uuid', $uuid)->firstOrFail();
    }

    public function findTrashedById(int $id): Media
    {
        return Media::onlyTrashed()->findOrFail($id);
    }

    /** Lấy Media bất kể đang active hay trashed — dùng khi thao tác cần xem cả 2 trạng thái. */
    public function findWithTrashedById(int $id): Media
    {
        return Media::withTrashed()->findOrFail($id);
    }

    public function create(array $data): Media
    {
        return Media::create($data);
    }

    public function update(Media $media, array $data): Media
    {
        $media->update($data);

        return $media->fresh();
    }

    /** Soft delete — KHÔNG đụng file vật lý/variants, đó là việc của MediaService. */
    public function delete(Media $media): bool
    {
        return (bool) $media->delete();
    }

    public function restore(Media $media): bool
    {
        return $media->restore();
    }

    /**
     * Hard delete DB record — DB tự CASCADE xoá media_variants + media_folder_items
     * liên quan. KHÔNG đụng filesystem — Service phải tự đọc path/variant path
     * TRƯỚC KHI gọi hàm này (vì sau khi forceDelete, record đã biến mất khỏi DB,
     * không còn cách nào lấy lại path).
     */
    public function forceDelete(Media $media): bool
    {
        return $media->forceDelete();
    }

    public function findActiveByHash(string $hash): ?Media
    {
        return Media::where('file_hash', $hash)->first();
    }

    public function findTrashedByHash(string $hash): ?Media
    {
        return Media::onlyTrashed()->where('file_hash', $hash)->first();
    }
}