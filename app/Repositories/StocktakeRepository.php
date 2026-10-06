<?php

namespace App\Repositories;

use App\Models\Stocktake;
use App\Models\Warehouse;
use Illuminate\Pagination\LengthAwarePaginator;

/** Persistence/query only. KHÔNG chứa FIFO, stale snapshot hay inventory mutation. */
class StocktakeRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Stocktake::query()
            ->with('warehouse:id,code,name')
            ->withCount('items')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('stocktake_code', 'like', "%{$search}%")
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Stocktake
    {
        return Stocktake::with([
            'warehouse:id,code,name',
            'creator:id,name',
            'items.product:id,sku,name',
        ])->findOrFail($id);
    }

    /** Phải gọi bên trong DB::transaction() ở Service. */
    public function findForUpdate(int $id): Stocktake
    {
        return Stocktake::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Stocktake
    {
        return Stocktake::create($data);
    }

    public function update(Stocktake $stocktake, array $data): Stocktake
    {
        $stocktake->update($data);

        return $stocktake->fresh();
    }

    public function delete(Stocktake $stocktake): bool
    {
        return (bool) $stocktake->delete();
    }

    public function findWarehouse(int $id): ?Warehouse
    {
        return Warehouse::query()->find($id, ['id', 'is_active']);
    }
}