<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandRepository
{
    /**
     * Eager-load 'media.variants' để hiển thị thumbnail trong Index — tránh
     * N+1 khi mỗi hàng trong bảng cần truy cập media->url và media->variants.
     * variants đã tự giới hạn cột cần thiết ở tầng Media model/accessor,
     * không cần select() thêm ở đây.
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Brand::query()
            ->with('media.variants')
            // ->when(
            //     class_exists(Product::class),
            //     fn ($q) => $q->withCount('products') // chỉ đếm khi model Product đã tồn tại
            // )
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
            )
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
    }

    /** Eager-load media.variants — dùng cho trang Edit cần hiển thị ảnh hiện tại. */
    public function findById(int $id): Brand
    {
        return Brand::with('media.variants')->findOrFail($id);
    }

    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->fresh();
    }

    public function delete(Brand $brand): bool
    {
        return (bool) $brand->delete();
    }

    public function isUsedByProduct(Brand $brand): bool
    {
        if (! class_exists(Product::class)) {
            return false;
        }
    
        return $brand->products()->exists();
    }
}