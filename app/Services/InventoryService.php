<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Repositories\StockAllocationRepository;
use App\Repositories\StockLotRepository;
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
        protected StockMovementRepository $movementRepository,
        protected StockLotRepository $stockLotRepository,
        protected StockAllocationRepository $stockAllocationRepository
    ) {
    }

    /**
     * Nhập kho (Phase C) — flow chính thức, DUY NHẤT cho nghiệp vụ Receive, có
     * tạo StockLot. increaseStock() (bên dưới) giờ chỉ là alias gọi lại method
     * này để backward-compat signature, không còn là business path riêng.
     *
     * quantity phải > 0. Mỗi lần Receive luôn tạo 1 StockLot MỚI (không gộp vào
     * lot cũ) với quantity_received = quantity_remaining = quantity nhập.
     * received_at mặc định = thời điểm hiện tại nếu không truyền vào.
     * expiry_date nullable — Phase C chỉ lưu dữ liệu, chưa có business logic
     * FEFO/hết hạn nào dùng tới field này.
     */
    public function receiveStock(
        int $warehouseId,
        int $productId,
        float $quantity,
        \DateTimeInterface|string|null $receivedAt = null,
        \DateTimeInterface|string|null $expiryDate = null,
        array $meta = []
    ): Stock {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng nhập phải lớn hơn 0.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $receivedAt, $expiryDate, $meta) {
            $stock = $this->lockOrCreateStock($warehouseId, $productId);

            $before = (float) $stock->quantity_on_hand;
            $after = $before + $quantity;

            $this->stockLotRepository->create([
                'stock_id' => $stock->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'received_at' => $receivedAt ?? now(),
                'expiry_date' => $expiryDate,
            ]);

            $this->stockRepository->updateQuantity($stock, $after);

            $this->recordMovement($warehouseId, $productId, 'in', $quantity, $before, $after, $meta);

            return $stock;
        });
    }

    /**
     * @deprecated Giữ lại CHỈ để backward-compat signature (đã verify ở Phase A).
     * Từ Phase C, đây là alias gọi thẳng receiveStock() — đảm bảo MỌI đường nhập
     * kho đều tạo StockLot, không còn 2 business path tạo Stock khác nhau mà chỉ
     * 1 trong 2 tạo lot (rủi ro đã nêu ở prompt Phase C mục 14). Code mới nên gọi
     * receiveStock() trực tiếp để có receivedAt/expiryDate.
     */
    public function increaseStock(int $warehouseId, int $productId, float $quantity, array $meta = []): Stock
    {
        return $this->receiveStock($warehouseId, $productId, $quantity, null, null, $meta);
    }

    /**
     * Xuất kho (Phase D) — flow chính thức, DUY NHẤT cho nghiệp vụ Issue, dùng
     * FIFO qua StockLot. decreaseStock() (bên dưới) giờ chỉ là alias gọi lại
     * method này để backward-compat signature (đã grep xác nhận không có
     * Controller/Service/test nào khác gọi decreaseStock() nên đổi an toàn,
     * tránh 2 business path xuất kho song song — 1 dùng FIFO, 1 giảm Stock
     * trực tiếp).
     *
     * Flow trong 1 transaction:
     *   1. Lock Stock (lockOrCreateStock - tái dùng như Phase A/C)
     *   2. Check đủ tồn theo Stock.quantity_on_hand — reject sớm, KHÔNG đụng Lot
     *      nào nếu không đủ (không partial success)
     *   3. Lock các StockLot khả dụng theo FIFO (received_at ASC, id ASC)
     *   4. Consume tuần tự: consume = min(lot.remaining, remainingToIssue)
     *   5. Nếu sau khi consume hết các lot mà remainingToIssue > 0 (Stock nói đủ
     *      nhưng tổng StockLot.remaining thực tế không đủ — ví dụ data cũ từ
     *      trước Phase C chưa có Lot) -> reject toàn bộ, KHÔNG bypass FIFO bằng
     *      cách trừ thẳng Stock
     *   6. Update Stock.quantity_on_hand
     *   7. Ghi 1 StockMovement(out, quantity ÂM)
     *
     * KHÔNG delete StockLot dù quantity_remaining về 0 (giữ lại làm lịch sử/audit).
     *
     * Phase E: sau khi tạo StockMovement OUT, tạo 1 StockAllocation cho MỖI lot
     * thực sự bị consume ở bước FIFO trên (đúng theo consumedLots đã ghi nhận
     * trong lúc chạy vòng lặp — KHÔNG tính lại/suy đoán), phản ánh chính xác
     * FIFO đã thực hiện. Không tạo Allocation cho IN/ADJUSTMENT.
     */
    public function issueStock(int $warehouseId, int $productId, float $quantity, array $meta = []): Stock
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

            $lots = $this->stockLotRepository->lockEligibleForIssue($warehouseId, $productId);

            $remainingToIssue = $quantity;
            // [lot_id => consumed_quantity] - đúng những gì FIFO đã thực hiện,
            // dùng lại để tạo StockAllocation, KHÔNG tính lại.
            $consumedLots = [];

            foreach ($lots as $lot) {
                if ($remainingToIssue <= 0) {
                    break;
                }

                $available = (float) $lot->quantity_remaining;
                $consume = min($available, $remainingToIssue);

                if ($consume > 0) {
                    $this->stockLotRepository->decrementRemaining($lot, $consume);
                    $consumedLots[$lot->id] = $consume;
                    $remainingToIssue -= $consume;
                }
            }

            if ($remainingToIssue > 0) {
                // Stock.quantity_on_hand nói đủ nhưng tổng StockLot.quantity_remaining
                // thực tế KHÔNG đủ để consume hết yêu cầu (dữ liệu Stock/Lot lệch
                // nhau — ví dụ Stock cũ trước Phase C chưa có Lot tương ứng).
                // KHÔNG bypass FIFO bằng cách trừ thẳng Stock. Rollback toàn bộ.
                throw ValidationException::withMessages([
                    'quantity' => "Không đủ StockLot khả dụng để xuất đủ {$quantity} (thiếu {$remainingToIssue}). Vui lòng kiểm tra dữ liệu tồn kho (Stock/StockLot không khớp).",
                ]);
            }

            $after = $before - $quantity;

            $this->stockRepository->updateQuantity($stock, $after);

            $movement = $this->recordMovement($warehouseId, $productId, 'out', -$quantity, $before, $after, $meta);

            foreach ($consumedLots as $lotId => $consumedQuantity) {
                $this->stockAllocationRepository->create([
                    'stock_movement_id' => $movement->id,
                    'stock_lot_id' => $lotId,
                    'quantity' => $consumedQuantity,
                ]);
            }

            return $stock;
        });
    }

    /**
     * @deprecated Giữ lại CHỈ để backward-compat signature (đã verify ở Phase A).
     * Từ Phase D, đây là alias gọi thẳng issueStock() — đảm bảo MỌI đường xuất
     * kho đều đi qua FIFO + consume StockLot, không còn business path riêng
     * giảm Stock trực tiếp không qua Lot.
     */
    public function decreaseStock(int $warehouseId, int $productId, float $quantity, array $meta = []): Stock
    {
        return $this->issueStock($warehouseId, $productId, $quantity, $meta);
    }

    private function recordMovement(
        int $warehouseId,
        int $productId,
        string $type,
        float $quantity,
        float $before,
        float $after,
        array $meta
    ): StockMovement {
        return $this->movementRepository->create([
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

    /**
     * Kiểm kê / điều chỉnh (Phase F) — đồng bộ với StockLot để giữ invariant
     * Stock.quantity_on_hand = SUM(StockLot.quantity_remaining).
     * Public API giữ nguyên: nhận actualQuantity (không phải delta).
     *
     * difference = actualQuantity - before:
     *   = 0  -> không làm gì (không tạo movement/lot)
     *   > 0  -> TĂNG: tạo 1 StockLot MỚI (received=remaining=difference),
     *           KHÔNG cộng vào lot cũ, KHÔNG dùng FIFO
     *   < 0  -> GIẢM: consume FIFO qua các lot khả dụng (tái dùng
     *           lockEligibleForIssue()/decrementRemaining() của Phase D,
     *           KHÔNG sửa issueStock()), KHÔNG tạo StockAllocation
     *
     * Nếu Stock/StockLot lệch nhau không đủ để consume phần giảm -> reject,
     * rollback toàn bộ, KHÔNG bypass bằng cách trừ thẳng Stock (giống Phase D).
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

            if ($difference == 0.0) {
                // Không có gì thay đổi - không tạo movement/lot/allocation.
                return $stock;
            }

            if ($difference > 0) {
                // TĂNG: tạo lot mới, KHÔNG cộng vào lot cũ.
                $this->stockLotRepository->create([
                    'stock_id' => $stock->id,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity_received' => $difference,
                    'quantity_remaining' => $difference,
                    'received_at' => now(),
                    'expiry_date' => null,
                ]);
            } else {
                // GIẢM: consume FIFO qua các lot khả dụng, KHÔNG tạo Allocation.
                $toConsume = abs($difference);

                $lots = $this->stockLotRepository->lockEligibleForIssue($warehouseId, $productId);

                $remainingToConsume = $toConsume;

                foreach ($lots as $lot) {
                    if ($remainingToConsume <= 0) {
                        break;
                    }

                    $available = (float) $lot->quantity_remaining;
                    $consume = min($available, $remainingToConsume);

                    if ($consume > 0) {
                        $this->stockLotRepository->decrementRemaining($lot, $consume);
                        $remainingToConsume -= $consume;
                    }
                }

                if ($remainingToConsume > 0) {
                    // Stock/StockLot lệch nhau (ví dụ data cũ) - không đủ lot để
                    // consume hết phần giảm. KHÔNG trừ thẳng Stock. Rollback.
                    throw ValidationException::withMessages([
                        'quantity' => "Không đủ StockLot khả dụng để điều chỉnh giảm {$toConsume} (thiếu {$remainingToConsume}). Vui lòng kiểm tra dữ liệu tồn kho (Stock/StockLot không khớp).",
                    ]);
                }
            }

            $this->stockRepository->updateQuantity($stock, $actualQuantity);

            $this->recordMovement($warehouseId, $productId, 'adjustment', $difference, $before, $actualQuantity, $meta);

            return $stock;
        });
    }
}