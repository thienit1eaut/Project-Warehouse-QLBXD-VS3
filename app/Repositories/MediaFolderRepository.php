<?php

namespace App\Repositories;

use App\Models\MediaFolder;
use Illuminate\Database\Eloquent\Collection;

class MediaFolderRepository
{
    /**
     * Toàn bộ Folder active, MỌI cấp (không chỉ root) — dùng cho dropdown
     * chọn parent_id ở Create/Edit Folder, và dropdown chọn folder đích khi
     * bulk add-to-folder Media. Không phân trang (số lượng folder thực tế
     * nhỏ, cùng lý do các method khác trong class này).
     * Trả kèm parent_id để frontend tự build cây lồng cấp (indent) — không
     * cần Repository/Service tự đệ quy dựng cây, giữ đơn giản.
     */
    public function getAllActive(): Collection
    {
        return MediaFolder::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }

    /**
     * Folder gốc (không có cha) — dùng để render cấp đầu tiên của cây.
     * Không phân trang: số lượng folder gốc trong 1 Admin Media Library
     * thực tế nhỏ (Products, Brands, Banners...), không cần paginate như Media.
     */
    public function getRootFolders(): Collection
    {
        return MediaFolder::query()
            ->whereNull('parent_id')
            ->withCount('children') // để UI biết folder nào có thể expand
            ->orderBy('name')
            ->get();
    }

    /** Folder con trực tiếp của 1 folder — dùng khi UI lazy-load 1 node trong cây. */
    public function getChildren(int $parentId): Collection
    {
        return MediaFolder::query()
            ->where('parent_id', $parentId)
            ->withCount('children')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toàn bộ Folder đã soft-delete (Phase 7 B7) — cùng lý do không phân trang
     * như getRootFolders(): số lượng Folder trong Trash thực tế nhỏ, không cần
     * paginate như Media (vốn có số lượng file lớn hơn nhiều).
     */
    public function getTrashed(): Collection
    {
        return MediaFolder::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    /**
     * Active only (SoftDeletes tự loại trừ trashed) — đây là cách "findActiveById"
     * được yêu cầu trong đặc tả thực hiện: KHÔNG tạo thêm alias riêng, vì
     * findById() trong toàn bộ project vốn đã luôn ngầm định nghĩa "active"
     * (Category/Brand/Supplier/Unit không có SoftDeletes nên chưa cần phân biệt
     * rõ, nhưng ý nghĩa tên hàm giữ nguyên) — thêm 1 tên hàm khác trỏ cùng ý
     * nghĩa sẽ gây trùng lặp không cần thiết trong cùng 1 class.
     */
    public function findById(int $id): MediaFolder
    {
        return MediaFolder::findOrFail($id);
    }

    public function findTrashedById(int $id): MediaFolder
    {
        return MediaFolder::onlyTrashed()->findOrFail($id);
    }

    /** Lấy Folder bất kể active hay trashed — dùng khi thao tác cần xem cả 2 trạng thái. */
    public function findWithTrashedById(int $id): MediaFolder
    {
        return MediaFolder::withTrashed()->findOrFail($id);
    }

    public function create(array $data): MediaFolder
    {
        return MediaFolder::create($data);
    }

    public function update(MediaFolder $folder, array $data): MediaFolder
    {
        $folder->update($data);

        return $folder->fresh();
    }

    /** Soft delete — KHÔNG đụng Media/media_folder_items, đó là bất biến đã chốt. */
    public function delete(MediaFolder $folder): bool
    {
        return (bool) $folder->delete();
    }

    public function restore(MediaFolder $folder): bool
    {
        return $folder->restore();
    }

    /**
     * Hard delete DB record — DB tự CASCADE xoá media_folder_items liên quan.
     * FK parent_id là RESTRICT nên nếu còn folder con (bất kể active hay
     * trashed — RESTRICT không phân biệt trạng thái soft-delete), MySQL sẽ tự
     * chặn bằng exception. Nhưng Service PHẢI tự check hasActiveChildren()
     * trước để trả lỗi nghiệp vụ rõ ràng, không phụ thuộc vào bắt exception DB.
     */
    public function forceDelete(MediaFolder $folder): bool
    {
        return $folder->forceDelete();
    }

    /**
     * Chỉ đếm folder con ACTIVE (relationship children() tự áp SoftDeletes
     * global scope) — dùng ở Service để chặn Delete/Permanent Delete khi còn
     * con đang hoạt động. KHÔNG tính folder con đã trashed.
     */
    public function hasActiveChildren(MediaFolder $folder): bool
    {
        return $folder->children()->exists();
    }

    /**
     * Kiểm tra trùng tên trong CÙNG 1 parent, CHỈ xét Folder active — SoftDeletes
     * tự thêm whereNull('deleted_at'), nên Folder đã trash KHÔNG chiếm tên,
     * đúng yêu cầu "Products/Shirts (trashed) rồi tạo lại Shirts phải hợp lệ".
     * Repository CHỈ trả bool, không tự throw exception — Service quyết định
     * xử lý message/exception.
     */
    public function existsByNameAndParent(string $name, ?int $parentId, ?int $excludeId = null): bool
    {
        return MediaFolder::query()
            ->where('name', $name)
            ->where('parent_id', $parentId) // Laravel tự dịch thành whereNull khi $parentId = null
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * Chuỗi id tổ tiên (ancestor chain) của 1 folder — dùng ở Service để kiểm
     * tra circular reference khi Move (đi ngược lên, nếu gặp lại chính id đang
     * move thì đó là move-vào-descendant). Cùng thuật toán CategoryRepository
     * đã dùng, KHÔNG viết cách khác cho Folder.
     */
    public function getAncestorIds(?int $folderId): array
    {
        $ancestors = [];
        $current = $folderId ? MediaFolder::find($folderId) : null;

        while ($current && $current->parent_id) {
            $ancestors[] = $current->parent_id;
            $current = $current->parent;
        }

        return $ancestors;
    }
}