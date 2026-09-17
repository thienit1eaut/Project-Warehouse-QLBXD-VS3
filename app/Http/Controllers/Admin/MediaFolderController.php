<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaFolder\StoreMediaFolderRequest;
use App\Http\Requests\MediaFolder\UpdateMediaFolderRequest;
use App\Models\MediaFolder;
use App\Services\MediaFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MediaFolderController extends Controller
{
    public function __construct(
        protected MediaFolderService $folderService,
    ) {}

    /**
     * Danh sách folder gốc kèm children_count.
     * Authorization: permission:media-folder.view (route middleware).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Media/Folders/Index', [
            'pageTitle'   => 'Quản lý Thư mục Media',
            'rootFolders' => $this->folderService->getRootFolders(),
        ]);
    }

    /**
     * Folder con trực tiếp của 1 folder — JSON, phục vụ lazy-load FolderTree
     * (Phase 7 B4). Không duplicate business logic — gọi thẳng
     * MediaFolderService::getChildren() đã có sẵn từ Phase 4.
     * Authorization: permission:media-folder.view (route middleware).
     */
    public function children(MediaFolder $folder): JsonResponse
    {
        return response()->json(
            $this->folderService->getChildren($folder->id)
        );
    }

    /**
     * Folder trong Trash (Phase 7 B7) — route tĩnh /trash phải khai báo
     * TRƯỚC route động /{folder} trong web.php để không bị bắt nhầm.
     * Authorization: permission:media-folder.restore (xem Trash gắn liền quyền restore).
     */
    public function trash(): Response
    {
        return Inertia::render('Admin/Media/Folders/Trash', [
            'pageTitle'       => 'Thùng rác Thư mục Media',
            'trashedFolders'  => $this->folderService->listTrashed(),
        ]);
    }

    /**
     * Trang riêng tạo folder — truyền allFolders (mọi cấp, không chỉ root)
     * để dropdown chọn parent_id có thể chọn cả folder cấp 2 trở lên.
     * Authorization: permission:media-folder.create (route middleware).
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Media/Folders/Create', [
            'pageTitle'   => 'Tạo thư mục Media',
            'allFolders'  => $this->folderService->listAll(),
        ]);
    }

    /**
     * Trang riêng sửa/rename/move folder — truyền allFolders để dropdown
     * chọn parent_id mới có thể chọn folder ở bất kỳ cấp nào (không chỉ
     * root), theo đúng contract updateFolder() đã chốt (gộp name +
     * parent_id trong 1 request).
     * Authorization: permission:media-folder.update (route middleware).
     */
    public function edit(MediaFolder $folder): Response
    {
        return Inertia::render('Admin/Media/Folders/Edit', [
            'pageTitle'   => 'Sửa thư mục Media',
            'folder'      => [
                'id'        => $folder->id,
                'name'      => $folder->name,
                'parent_id' => $folder->parent_id,
            ],
            'allFolders'  => $this->folderService->listAll(),
        ]);
    }

    /**
     * Tạo folder mới — validate name + parent_id qua StoreMediaFolderRequest.
     * Service check duplicate name trong cùng parent.
     * Authorization: permission:media-folder.create (route middleware).
     */
    public function store(StoreMediaFolderRequest $request): RedirectResponse
    {
        try {
            $this->folderService->createFolder($request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã tạo thư mục thành công.');
    }

    /**
     * Cập nhật folder — rename và/hoặc move trong 1 request.
     * UpdateMediaFolderRequest gộp name + parent_id.
     * Service phân biệt rename/move dựa trên so sánh với giá trị hiện tại.
     * Authorization: permission:media-folder.update (route middleware).
     */
    public function update(UpdateMediaFolderRequest $request, MediaFolder $folder): RedirectResponse
    {
        try {
            $this->folderService->updateFolder($folder, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Đã cập nhật thư mục.');
    }

    /**
     * Soft delete — bị chặn nếu còn active children.
     * Không đụng Media, không đụng pivot.
     * Authorization: permission:media-folder.delete (route middleware).
     */
    public function destroy(MediaFolder $folder): RedirectResponse
    {
        try {
            $this->folderService->deleteFolder($folder);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã chuyển thư mục vào thùng rác.');
    }

    /**
     * Restore từ Trash — re-check duplicate name với siblings active hiện tại.
     * Route dùng ->withTrashed() — binding tìm được trashed record.
     * Service nhận int $id (contract hiện tại).
     * Authorization: permission:media-folder.restore (route middleware).
     */
    public function restore(MediaFolder $folder): RedirectResponse
    {
        try {
            $this->folderService->restoreFolder($folder->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã khôi phục thư mục.');
    }

    /**
     * Permanent delete — bị chặn nếu còn active children.
     * CASCADE xoá media_folder_items, KHÔNG xoá Media.
     * Route dùng ->withTrashed() — binding tìm cả active lẫn trashed.
     * Service nhận int $id (contract hiện tại).
     * Authorization: permission:media-folder.force-delete (route middleware).
     */
    public function forceDelete(MediaFolder $folder): RedirectResponse
    {
        try {
            $this->folderService->forceDeleteFolder($folder->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã xoá vĩnh viễn thư mục.');
    }
}