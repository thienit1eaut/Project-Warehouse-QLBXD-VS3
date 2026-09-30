<?php

namespace App\Repositories;

use App\Models\StockLot;
use Illuminate\Database\Eloquent\Collection;

/**
 * Phase B (foundation): CHỈ persistence/query. Không quyết định business rule
 * (đủ tồn hay không, xuất bao nhiêu, consume bao nhiêu từ mỗi lot, tạo
 * allocation, tạo movement) — những quyết định đó thuộc InventoryService ở
 * Phase D/E (FIFO issue + StockAllocation), KHÔNG nằm ở đây.
 */
class StockLotRepository
{
    public function create(array $data): StockLot
    {
        return StockLot::create($data);
    }

    public function find(int $id): ?StockLot
    {
        return StockLot::with(['stock', 'warehouse', 'product'])->find($id);
    }

    public function getByStock(int $stockId): Collection
    {
        return StockLot::where('stock_id', $stockId)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    public function getByWarehouseAndProduct(int $warehouseId, int $productId): Collection
    {
        return StockLot::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Danh sách lot còn khả dụng (quantity_remaining > 0) cho 1 warehouse+product,
     * SẮP XẾP đúng thứ tự FIFO (received_at ASC, id ASC). Đây là query thuần —
     * KHÔNG lock, KHÔNG kiểm tra đủ tồn hay không, KHÔNG quyết định consume bao
     * nhiêu. InventoryService (Phase D/E) sẽ dùng kết quả này để tự làm FIFO
     * allocation trong transaction của nó.
     */
    public function getEligibleForIssue(int $warehouseId, int $productId): Collection
    {
        return StockLot::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Giống getEligibleForIssue() nhưng có lockForUpdate() — CHUẨN BỊ cho
     * Phase D/E, chưa được gọi ở đâu trong Phase B. PHẢI được gọi trong
     * DB::transaction() của InventoryService khi dùng thật (giống cách
     * StockRepository::getForUpdate() đang được dùng) — repository không tự
     * mở transaction.
     */
    public function lockEligibleForIssue(int $warehouseId, int $productId): Collection
    {
        return StockLot::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function sumRemainingByStock(int $stockId): string
    {
        return (string) StockLot::where('stock_id', $stockId)->sum('quantity_remaining');
    }

    /**
     * Trừ quantity_remaining của 1 lot theo số lượng $amount đã được QUYẾT ĐỊNH
     * sẵn bởi InventoryService (FIFO consume ở Phase D). Đây chỉ là persistence
     * thuần (giống StockRepository::updateQuantity()) — không tự tính toán, không
     * kiểm tra đủ tồn, không biết gì về FIFO/thứ tự lot.
     */
    public function decrementRemaining(StockLot $lot, float $amount): StockLot
    {
        $lot->quantity_remaining = (float) $lot->quantity_remaining - $amount;
        $lot->save();

        return $lot;
    }
}
