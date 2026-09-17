<?php

namespace App\Services;

use App\Models\Stock;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DUY NHẤT được sửa stocks.quantity_on_hand.
 * Mỗi method public bọc DB::transaction() và tạo đúng 1 StockMovement (ledger bất biến).
 */
class InventoryService
{
    public function __construct(
        protected StockRepository $stockRepository,
        protected StockMovementRepository $movementRepository
    ) {
    }

    /**
     * Nhập kho. quantity phải > 0. movement type=in, quantity dương.
     */
    public function increaseStock(int $warehouseId, int $productId, float $quantity, array $meta = []): Stock
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng nhập phải lớn hơn 0.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $meta) {
            $stock = $this->lockOrCreateStock($warehouseId, $productId);

            $before = (float) $stock->quantity_on_hand;
            $after = $before + $quantity;

            $this->stockRepository->updateQuantity($stock, $after);

            $this->recordMovement($warehouseId, $productId, 'in', $quantity, $before, $after, $meta);

            return $stock;
        });
    }

    /**
     * Xuất kho. quantity phải > 0. Chặn nếu tồn hiện tại < quantity yêu cầu
     * (KHÔNG tạo movement nào khi bị reject). movement type=out, quantity lưu ÂM.
     */
    public function decreaseStock(int $warehouseId, int $productId, float $quantity, array $meta = []): Stock
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng xuất phải lớn hơn 0.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $meta) {
            $stock = $this->lockOrCreateStock($warehouseId, $productId);

            $before = (float) $stock->quantity_on_hand;

            if ($before < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Không đủ tồn kho để xuất. Tồn hiện tại: {$before}, yêu cầu xuất: {$quantity}.",
                ]);
            }

            $after = $before - $quantity;

            $this->stockRepository->updateQuantity($stock, $after);

            $this->recordMovement($warehouseId, $productId, 'out', -$quantity, $before, $after, $meta);

            return $stock;
        });
    }

    /**
     * Kiểm kê / điều chỉnh. actualQuantity không âm.
     * Update thẳng = actualQuantity (KHÔNG phải +=). movement type=adjustment,
     * quantity = difference (actual - current), có thể âm hoặc dương.
     */
    public function adjustStock(int $warehouseId, int $productId, float $actualQuantity, array $meta = []): Stock
    {
        if ($actualQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng kiểm kê không được nhỏ hơn 0.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $actualQuantity, $meta) {
            $stock = $this->lockOrCreateStock($warehouseId, $productId);

            $before = (float) $stock->quantity_on_hand;
            $difference = $actualQuantity - $before;

            $this->stockRepository->updateQuantity($stock, $actualQuantity);

            $this->recordMovement($warehouseId, $productId, 'adjustment', $difference, $before, $actualQuantity, $meta);

            return $stock;
        });
    }

    private function recordMovement(
        int $warehouseId,
        int $productId,
        string $type,
        float $quantity,
        float $before,
        float $after,
        array $meta
    ): void {
        $this->movementRepository->create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'note' => $meta['note'] ?? null,
        ]);
    }

    /**
     * Lấy + lock row Stock. Nếu chưa tồn tại, tạo mới quantity_on_hand=0 rồi lock lại.
     * Bắt race condition: 2 request đồng thời cùng insert sẽ đụng UNIQUE(warehouse_id,product_id)
     * -> catch QueryException, gọi lại getForUpdate() để lấy row mà request khác đã tạo.
     */
    protected function lockOrCreateStock(int $warehouseId, int $productId): Stock
    {
        $stock = $this->stockRepository->getForUpdate($warehouseId, $productId);

        if ($stock !== null) {
            return $stock;
        }

        try {
            $this->stockRepository->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity_on_hand' => 0,
            ]);
        } catch (QueryException $e) {
            $stock = $this->stockRepository->getForUpdate($warehouseId, $productId);

            if ($stock === null) {
                // Không phải do race condition (row vẫn không tồn tại) - lỗi thật, ném lại.
                throw $e;
            }

            return $stock;
        }

        // Lock lại row vừa tạo (create() không tự lock).
        return $this->stockRepository->getForUpdate($warehouseId, $productId);
    }
}
