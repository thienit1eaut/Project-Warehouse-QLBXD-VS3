<?php

namespace App\Repositories;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** Persistence/query only. KHÔNG chứa FIFO hay inventory mutation. */
class StockTransferRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with(['fromWarehouse:id,code,name', 'toWarehouse:id,code,name'])
            ->withCount('items')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('transfer_code', 'like', "%{$search}%")
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): StockTransfer
    {
        return StockTransfer::with([
            'fromWarehouse:id,code,name',
            'toWarehouse:id,code,name',
            'creator:id,name',
            'items.product:id,sku,name',
        ])->findOrFail($id);
    }

    /** Phải gọi bên trong DB::transaction() ở Service. */
    public function findForUpdate(int $id): StockTransfer
    {
        return StockTransfer::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): StockTransfer
    {
        return StockTransfer::create($data);
    }

    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        $transfer->update($data);

        return $transfer->fresh();
    }

    public function delete(StockTransfer $transfer): bool
    {
        return (bool) $transfer->delete();
    }

    /** Dùng để Service kiểm tra tồn tại/is_active của kho nguồn và kho đích. */
    public function warehousesById(array $ids): Collection
    {
        return Warehouse::query()
            ->whereIn('id', $ids)
            ->get(['id', 'is_active'])
            ->keyBy('id');
    }
}