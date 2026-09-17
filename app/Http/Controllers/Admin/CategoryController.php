<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        $categories = $this->categoryService->list(
            $request->only(['search', 'is_active', 'parent_id', 'sort', 'direction'])
        );

        return Inertia::render('Admin/Categories/Index', [
            'pageTitle'  => 'Quản lý danh mục',
            'categories' => $categories,
            'filters'    => $request->only(['search', 'is_active', 'parent_id']),
            // Dùng cho dropdown lọc theo danh mục cha — chỉ lấy danh mục gốc
            'parentOptions' => $this->categoryService->options()
                ->whereNull('parent_id')
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'pageTitle' => 'Thêm danh mục',
            'category'  => null,
            'parentOptions' => $this->categoryService->options(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()->route('admin.categories.index')
            ->with('success', 'Đã tạo danh mục thành công.');
    }

    /**
     * Route model binding KHÔNG đi qua CategoryRepository::findById() (vốn
     * đã eager-load media.variants) — phải tự load() ở đây, cùng pattern
     * BrandController::edit().
     */
    public function edit(Category $category): Response
    {
        $category->load('media.variants');

        return Inertia::render('Admin/Categories/Form', [
            'pageTitle' => 'Sửa danh mục: ' . $category->name,
            'category'  => array_merge(
                $category->only('id', 'parent_id', 'name', 'description', 'is_active', 'img'),
                ['media' => $category->media]
            ),
            // loại chính category đang sửa và các con/cháu trực tiếp của nó khỏi danh sách chọn cha,
            // chặn phần lớn trường hợp circular ngay tại UI (Service vẫn là lớp bảo vệ cuối cùng)
            'parentOptions' => $this->categoryService->options()
                ->reject(fn ($c) => $c->id === $category->id)
                ->values(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        try {
            $this->categoryService->update($category, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.categories.index')
            ->with('success', 'Đã cập nhật danh mục.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        try {
            $this->categoryService->delete($category);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.categories.index')
            ->with('success', 'Đã xoá danh mục.');
    }
}