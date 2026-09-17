<?php

namespace App\Repositories;

use App\Models\Stock;
use Illuminate\Pagination\LengthAwarePaginator;

class StockRepository
{
    /**
     * Danh sách stock có filter theo warehouse_id / search (theo tên hoặc SKU sản phẩm).
     * Eager-load warehouse + product + unit để hiển thị, KHÔNG duplicate dữ liệu vào Stock.
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Stock::query()
            ->with([
                'warehouse:id,code,name',
                'product:id,sku,name,unit_id',
                'product.unit:id,name,code',
            ]);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Stock
    {
        return Stock::with(['warehouse', 'product.unit'])->find($id);
    }

    public function findByWarehouseAndProduct(int $warehouseId, int $productId): ?Stock
    {
        return Stock::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Lấy + LOCK row Stock (SELECT ... FOR UPDATE).
     * PHẢI được gọi bên trong DB::transaction() ở Service - Repository không tự mở transaction.
     */
    public function getForUpdate(int $warehouseId, int $productId): ?Stock
    {
        return Stock::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $data): Stock
    {
        return Stock::create($data);
    }

    public function updateQuantity(Stock $stock, float $quantity): Stock
    {
        $stock->quantity_on_hand = $quantity;
        $stock->save();

        return $stock;
    }
}
