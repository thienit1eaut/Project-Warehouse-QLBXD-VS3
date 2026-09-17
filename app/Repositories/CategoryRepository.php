<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    /**
     * Danh sách category có phân trang, hỗ trợ keyword/status/parent/sort.
     * Không lấy toàn bộ dữ liệu nếu không cần thiết — luôn paginate.
     *
     * Eager-load 'media.variants' — tránh N+1 khi mỗi hàng cần truy cập
     * media->url/variants (Index UI hiện chưa render cột ảnh, nhưng JSON
     * response phải sẵn dữ liệu đầy đủ cho Web/App khác dùng trực tiếp).
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Category::query()
            ->with(['parent:id,name', 'media.variants']) // chỉ lấy cột cần cho hiển thị, tránh N+1 và dư dữ liệu
            ->withCount('children')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
            )
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->when(
                array_key_exists('parent_id', $filters) && $filters['parent_id'] !== '',
                function ($q) use ($filters) {
                    // parent_id = 'root' -> chỉ lấy category gốc (không có cha)
                    return $filters['parent_id'] === 'root'
                        ? $q->whereNull('parent_id')
                        : $q->where('parent_id', $filters['parent_id']);
                }
            )
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Toàn bộ category — dùng cho dropdown chọn parent trong form (không phân trang vì cần đủ cây). */
    public function all(): Collection
    {
        return Category::query()->orderBy('name')->get(['id', 'parent_id', 'name']);
    }

    /** Eager-load media.variants — dùng cho trang Edit cần hiển thị ảnh hiện tại. */
    public function findById(int $id): Category
    {
        return Category::with('media.variants')->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }

    public function hasChildren(Category $category): bool
    {
        return $category->children()->exists();
    }

    /**
     * Kiểm tra category có đang được Product sử dụng không — Product module
     * đã hoàn thành (Phase 1), kích hoạt check thật thay vì placeholder.
     */
    public function isUsedByProduct(Category $category): bool
    {
        return $category->products()->exists();
    }

    /**
     * Lấy toàn bộ chuỗi id tổ tiên (ancestor chain) của 1 category, dùng để
     * kiểm tra circular hierarchy ở Service. Trả về mảng id từ cha gần nhất -> gốc.
     */
    public function getAncestorIds(?int $categoryId): array
    {
        $ancestors = [];
        $current = $categoryId ? Category::find($categoryId) : null;

        while ($current && $current->parent_id) {
            $ancestors[] = $current->parent_id;
            $current = $current->parent; // dùng relation, tận dụng Eloquent lazy load
        }

        return $ancestors;
    }
}