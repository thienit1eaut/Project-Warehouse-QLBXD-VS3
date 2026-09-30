<?php

namespace App\Repositories;

use App\Models\StockAllocation;
use Illuminate\Database\Eloquent\Collection;

/**
 * CHỈ persistence/query. Không quyết định consume bao nhiêu từ lot nào —
 * quyết định đó thuộc InventoryService (kết quả của FIFO ở Phase D), method
 * create() ở đây chỉ ghi lại đúng những gì Service đã quyết định.
 */
class StockAllocationRepository
{
    public function create(array $data): StockAllocation
    {
        return StockAllocation::create($data);
    }

    public function find(int $id): ?StockAllocation
    {
        return StockAllocation::with(['stockMovement', 'stockLot'])->find($id);
    }

    public function getByMovement(int $stockMovementId): Collection
    {
        return StockAllocation::where('stock_movement_id', $stockMovementId)->get();
    }

    public function getByLot(int $stockLotId): Collection
    {
        return StockAllocation::where('stock_lot_id', $stockLotId)->get();
    }
}