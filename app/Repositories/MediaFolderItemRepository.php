<?php

namespace App\Repositories;

use App\Models\MediaFolderItem;
use Illuminate\Database\Eloquent\Collection;

class MediaFolderItemRepository
{
    /** Kiểm tra quan hệ đã tồn tại chưa — Service dùng để quyết định graceful-fail hay tiếp tục. */
    public function exists(int $mediaId, int $folderId): bool
    {
        return MediaFolderItem::query()
            ->where('media_id', $mediaId)
            ->where('folder_id', $folderId)
            ->exists();
    }

    public function find(int $mediaId, int $folderId): ?MediaFolderItem
    {
        return MediaFolderItem::query()
            ->where('media_id', $mediaId)
            ->where('folder_id', $folderId)
            ->first();
    }

    /**
     * Thêm quan hệ — created_at tự set qua Model (const UPDATED_AT = null vẫn
     * giữ hành vi tự động set created_at khi tạo). KHÔNG kiểm tra duplicate ở
     * đây — Service đã gọi exists() trước và quyết định hành vi phù hợp; nếu
     * gọi add() khi quan hệ đã tồn tại, UNIQUE constraint DB sẽ là lớp bảo vệ
     * cuối (không phải nơi Repository tự xử lý business logic).
     */
    public function add(int $mediaId, int $folderId): MediaFolderItem
    {
        return MediaFolderItem::create([
            'media_id' => $mediaId,
            'folder_id' => $folderId,
        ]);
    }

    /**
     * Xoá quan hệ — DELETE thật (không SoftDelete, bảng không có deleted_at).
     * Trả về số dòng bị xoá (0 hoặc 1 vì có UNIQUE(media_id, folder_id)).
     */
    public function remove(int $mediaId, int $folderId): int
    {
        return MediaFolderItem::query()
            ->where('media_id', $mediaId)
            ->where('folder_id', $folderId)
            ->delete();
    }

    /** Toàn bộ media_id đang thuộc 1 folder — dùng khi Service/Controller chỉ cần danh sách ID thuần. */
    public function getMediaIdsByFolder(int $folderId): array
    {
        return MediaFolderItem::query()
            ->where('folder_id', $folderId)
            ->pluck('media_id')
            ->all();
    }

    /** Toàn bộ folder_id mà 1 media đang thuộc về. */
    public function getFolderIdsByMedia(int $mediaId): array
    {
        return MediaFolderItem::query()
            ->where('media_id', $mediaId)
            ->pluck('folder_id')
            ->all();
    }

    /** Toàn bộ pivot record của 1 Media — dùng khi Service cần duyệt qua từng quan hệ (vd Permanent Delete). */
    public function getByMedia(int $mediaId): Collection
    {
        return MediaFolderItem::query()->where('media_id', $mediaId)->get();
    }
}