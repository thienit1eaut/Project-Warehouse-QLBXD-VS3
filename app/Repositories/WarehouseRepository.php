<?php

namespace App\Repositories;

use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class WarehouseRepository
{
    /**
     * withCount('stocks') = "số sản phẩm đang có tồn tại kho này" — mỗi
     * Stock row = đúng 1 Product trong kho đó (unique constraint), nên
     * đếm số Stock row = đếm số sản phẩm đã từng ghi nhận tồn (kể cả = 0).
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Warehouse::query()
            ->withCount('stocks')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
            )
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Warehouse
    {
        return Warehouse::findOrFail($id);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse->fresh();
    }

    public function delete(Warehouse $warehouse): bool
    {
        return (bool) $warehouse->delete();
    }

    /** Dropdown chọn kho — chỉ kho đang active. */
    public function options(): Collection
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Chặn xoá Warehouse đã có lịch sử Stock/StockMovement — dùng ở
     * WarehouseService trước khi delete(), cùng pattern isUsedByProduct()
     * đã áp dụng cho Category/Unit.
     */
    public function hasStockOrMovement(Warehouse $warehouse): bool
    {
        return $warehouse->stocks()->exists() || $warehouse->stockMovements()->exists();
    }
}