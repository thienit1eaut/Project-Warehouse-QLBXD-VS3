<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        protected CategoryRepository $categoryRepository,
    ) {}

    public function list(array $filters)
    {
        return $this->categoryRepository->paginate($filters);
    }

    public function options()
    {
        return $this->categoryRepository->all();
    }

    public function find(int $id): Category
    {
        return $this->categoryRepository->findById($id);
    }

    public function create(array $data): Category
    {
        $this->assertParentValid($data['parent_id'] ?? null, currentId: null);

        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return $this->categoryRepository->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $this->assertParentValid($data['parent_id'] ?? null, currentId: $category->id);

        // Slug chỉ tạo lại khi tên thay đổi, tránh vỡ URL/liên kết cũ nếu người dùng chỉ sửa mô tả
        if (($data['name'] ?? null) !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], excludeId: $category->id);
        }

        return $this->categoryRepository->update($category, $data);
    }

    /**
     * isUsedByProduct() kích hoạt thật — Product module đã hoàn thành
     * (Phase 1). Trước đây chỉ comment placeholder vì Product chưa tồn tại,
     * khiến FK category_id (RESTRICT) throw QueryException thô 500 thay vì
     * message nghiệp vụ rõ ràng khi xoá Category đang có Product.
     */
    public function delete(Category $category): void
    {
        if ($this->categoryRepository->hasChildren($category)) {
            throw ValidationException::withMessages([
                'category' => 'Không thể xoá danh mục đang có danh mục con. Vui lòng xoá hoặc chuyển danh mục con trước.',
            ]);
        }

        if ($this->categoryRepository->isUsedByProduct($category)) {
            throw ValidationException::withMessages([
                'category' => 'Không thể xoá danh mục đang được sản phẩm sử dụng.',
            ]);
        }

        $this->categoryRepository->delete($category);
    }

    /**
     * Kiểm tra parent_id hợp lệ:
     *  - Không được là chính category hiện tại (khi update)
     *  - Không được tạo circular hierarchy (chọn 1 con/cháu của chính mình làm cha)
     */
    protected function assertParentValid(?int $parentId, ?int $currentId): void
    {
        if ($parentId === null) {
            return; // category gốc, hợp lệ
        }

        if ($currentId !== null && $parentId === $currentId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Danh mục không thể là danh mục cha của chính nó.',
            ]);
        }

        if ($currentId !== null) {
            // Đi ngược chuỗi tổ tiên của parent_id được chọn — nếu gặp lại currentId
            // nghĩa là currentId đang được chọn làm cha của 1 trong các tổ tiên đó -> vòng lặp
            $ancestorIds = $this->categoryRepository->getAncestorIds($parentId);

            if (in_array($currentId, $ancestorIds, true) || $parentId === $currentId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn danh mục con/cháu của chính nó làm danh mục cha (circular hierarchy).',
                ]);
            }
        }
    }

    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Category::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}