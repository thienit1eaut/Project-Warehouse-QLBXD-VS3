<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * Eager-load category/brand/supplier/unit (chỉ cột cần cho hiển thị,
     * tránh N+1 và dư dữ liệu) + media.variants (thumbnail cho Index).
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'supplier:id,name',
                'unit:id,name,code',
                'media.variants',
            ])
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Eager-load đầy đủ — dùng cho trang Edit/Show. */
    public function findById(int $id): Product
    {
        return Product::with(['category', 'brand', 'supplier', 'unit', 'media.variants'])
            ->findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }
}