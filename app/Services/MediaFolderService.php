<?php

namespace App\Services;

use App\Models\MediaFolder;
use App\Repositories\MediaFolderRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MediaFolderService
{
    public function __construct(
        protected MediaFolderRepository $folderRepository,
    ) {}

    public function getRootFolders(): Collection
    {
        return $this->folderRepository->getRootFolders();
    }

    /**
     * Toàn bộ Folder active mọi cấp (Phase 7 fix) — dùng cho dropdown chọn
     * parent_id / folder đích, cần thấy được cả folder cấp 2 trở lên.
     */
    public function listAll(): Collection
    {
        return $this->folderRepository->getAllActive();
    }

    public function getChildren(int $parentId): Collection
    {
        return $this->folderRepository->getChildren($parentId);
    }

    /** Toàn bộ Folder trong Trash (Phase 7 B7) — không phân trang, cùng lý do getRootFolders(). */
    public function listTrashed(): Collection
    {
        return $this->folderRepository->getTrashed();
    }

    public function find(int $id): MediaFolder
    {
        return $this->folderRepository->findById($id);
    }

    public function createFolder(array $data): MediaFolder
    {
        $parentId = $data['parent_id'] ?? null;
        $name = $data['name'];

        $this->assertNameNotDuplicate($name, $parentId);

        return $this->folderRepository->create([
            'name' => $name,
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Xử lý CẢ rename lẫn move trong 1 method (phương án A đã chốt với người
     * dùng) — UpdateMediaFolderRequest gộp name + parent_id vào 1 payload
     * duy nhất (Phase 2), Service tự so sánh với giá trị hiện tại của
     * $folder để biết cái nào thực sự đổi, chỉ chạy circular-check +
     * transaction khi parent_id đổi thật sự.
     */
    public function updateFolder(MediaFolder $folder, array $data): MediaFolder
    {
        $newName = $data['name'];

        // parent_id có thể chủ động là null (move về root) -> phải dùng
        // array_key_exists, không dùng ?? (null là giá trị hợp lệ của caller,
        // không phải "không truyền").
        $newParentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $folder->parent_id;

        $parentChanged = $newParentId !== $folder->parent_id;

        if ($parentChanged) {
            $this->assertMoveValid($folder, $newParentId);
        }

        // Duplicate-name LUÔN check với parent ĐÍCH ($newParentId) — dù chỉ
        // rename, chỉ move, hay cả 2, "trùng tên" luôn phụ thuộc parent đích
        // chứ không phụ thuộc parent cũ.
        $this->assertNameNotDuplicate($newName, $newParentId, excludeId: $folder->id);

        if (! $parentChanged) {
            // Rename thuần (hoặc không đổi gì) -> 1 write duy nhất, không cần transaction.
            return $this->folderRepository->update($folder, ['name' => $newName]);
        }

        return DB::transaction(function () use ($folder, $newName, $newParentId) {
            return $this->folderRepository->update($folder, [
                'name' => $newName,
                'parent_id' => $newParentId,
            ]);
        });
    }

    /**
     * Soft delete — KHÔNG đụng Media, KHÔNG đụng media_folder_items (mục 4.5).
     * Chặn nếu còn folder con ACTIVE, Service tự check trước, không phụ
     * thuộc exception DB (FK parent_id chỉ là lớp bảo vệ cuối).
     */
    public function deleteFolder(MediaFolder $folder): void
    {
        if ($this->folderRepository->hasActiveChildren($folder)) {
            throw ValidationException::withMessages([
                'folder' => 'Không thể xoá thư mục đang có thư mục con. Vui lòng xoá hoặc di chuyển thư mục con trước.',
            ]);
        }

        $this->folderRepository->delete($folder);
    }

    /**
     * Restore — phải re-check duplicate tên với sibling ACTIVE hiện tại
     * (không phải tại thời điểm bị trash), vì có thể đã có folder khác cùng
     * tên được tạo mới trong lúc bản này nằm trong Trash. Nhận int $id
     * (không phải Model) vì folder đang trashed không thể lấy qua route
     * model binding mặc định — cùng convention với
     * MediaFolderRepository::findTrashedById().
     */
    public function restoreFolder(int $id): MediaFolder
    {
        $folder = $this->folderRepository->findTrashedById($id);

        $this->assertNameNotDuplicate($folder->name, $folder->parent_id);

        $this->folderRepository->restore($folder);

        return $folder->fresh();
    }

    /**
     * Permanent Delete — CASCADE tự xoá media_folder_items liên quan, KHÔNG
     * BAO GIỜ đụng Media (mục 4.5). Chặn nếu còn folder con (active lẫn
     * trashed — FK parent_id RESTRICT không phân biệt trạng thái, nên chặn
     * ở cả 2 trường hợp để trả lỗi nghiệp vụ rõ ràng thay vì để DB tự chặn).
     */
    public function forceDeleteFolder(int $id): void
    {
        $folder = $this->folderRepository->findWithTrashedById($id);

        if ($this->folderRepository->hasActiveChildren($folder)) {
            throw ValidationException::withMessages([
                'folder' => 'Không thể xoá vĩnh viễn thư mục đang có thư mục con.',
            ]);
        }

        $this->folderRepository->forceDelete($folder);
    }

    /**
     * Circular reference check — thuật toán y hệt
     * CategoryService::assertParentValid() (đi ngược ancestor chain), dùng
     * lại MediaFolderRepository::getAncestorIds() như đã chốt ở mục 4.5,
     * KHÔNG viết thuật toán khác cho Folder.
     */
    protected function assertMoveValid(MediaFolder $folder, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return; // move về root luôn hợp lệ
        }

        if ($newParentId === $folder->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Thư mục không thể là thư mục cha của chính nó.',
            ]);
        }

        // Đi ngược chuỗi tổ tiên của parent_id được chọn — nếu gặp lại chính
        // $folder->id nghĩa là $folder đang được chọn làm cha của 1 trong
        // các tổ tiên đó -> vòng lặp (move vào chính con/cháu của nó).
        $ancestorIds = $this->folderRepository->getAncestorIds($newParentId);

        if (in_array($folder->id, $ancestorIds, true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Không thể chọn thư mục con/cháu của chính nó làm thư mục cha (circular hierarchy).',
            ]);
        }
    }

    /**
     * Duplicate-name chỉ xét sibling ACTIVE trong CÙNG parent — Repository
     * tự loại trừ trashed qua SoftDeletes global scope (không cần thêm điều
     * kiện ở đây).
     */
    protected function assertNameNotDuplicate(string $name, ?int $parentId, ?int $excludeId = null): void
    {
        if ($this->folderRepository->existsByNameAndParent($name, $parentId, $excludeId)) {
            throw ValidationException::withMessages([
                'name' => 'Đã tồn tại thư mục cùng tên trong thư mục cha này.',
            ]);
        }
    }
}