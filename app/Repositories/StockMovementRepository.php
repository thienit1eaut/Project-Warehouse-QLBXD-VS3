<?php

namespace App\Repositories;

use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMovementRepository
{
    /**
     * Ledger bất biến: KHÔNG có update/delete.
     */
    public function create(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    public function getByStock(int $warehouseId, int $productId, int $perPage = 20): LengthAwarePaginator
    {
        return StockMovement::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
