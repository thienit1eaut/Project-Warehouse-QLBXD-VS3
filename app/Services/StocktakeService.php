<?php

namespace App\Services;

use App\Models\Stocktake;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Repositories\StocktakeItemRepository;
use App\Repositories\StocktakeRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chứng từ kiểm kê. KHÔNG tự đụng Stock/StockLot/StockMovement/StockAllocation:
 * mọi thay đổi tồn kho đi qua InventoryService::adjustStock() (boundary duy nhất).
 * Service chỉ ĐỌC/KHÓA Stock (qua StockRepository) để chụp snapshot và phát hiện stale.
 */
class StocktakeService
{
    private const EPSILON = 0.0005; // decimal(15,3)

    public function __construct(
        protected StocktakeRepository $stocktakes,
        protected StocktakeItemRepository $items,
        protected ProductRepository $products,
        protected StockRepository $stocks,
        protected InventoryService $inventoryService,
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->stocktakes->paginate($filters);
    }

    /**
     * Chi tiết. Với phiếu DRAFT, kèm tồn hiện tại + cờ stale cho từng dòng để UI cảnh báo sớm
     * (chỉ đọc, KHÔNG cập nhật snapshot).
     */
    public function findDetail(int $id): Stocktake
    {
        $stocktake = $this->stocktakes->find($id);

        if ($stocktake->isDraft()) {
            foreach ($stocktake->items as $item) {
                $current = $this->currentQuantity((int) $stocktake->warehouse_id, (int) $item->product_id);

                $item->setAttribute('current_quantity', $current);
                $item->setAttribute('is_stale', ! $this->sameQuantity($current, (float) $item->system_quantity));
            }
        }

        $stocktake->setAttribute(
            'has_stale_items',
            $stocktake->items->contains(fn ($item) => $item->getAttribute('is_stale') === true)
        );

        return $stocktake;
    }

    /** Dùng cho trang Edit: chỉ cho phép khi còn DRAFT. */
    public function findEditable(int $id): Stocktake
    {
        $stocktake = $this->stocktakes->find($id);

        if (! $stocktake->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Phiếu kiểm kê đã POST, không thể chỉnh sửa.',
            ]);
        }

        return $stocktake;
    }

    /**
     * Tạo DRAFT + chụp snapshot tồn hệ thống cho từng dòng. Không gọi InventoryService.
     * $data: warehouse_id, stocktake_date, note?, items[]{product_id, actual_quantity}
     */
    public function createDraft(array $data, int $userId): Stocktake
    {
        $lines = $this->normalizeLines($data['items'] ?? []);

        $this->assertWarehouse((int) $data['warehouse_id']);

        return DB::transaction(function () use ($data, $lines, $userId) {
            // Mã tạm duy nhất rồi đổi sang mã theo id trong cùng transaction (tránh race).
            $stocktake = $this->stocktakes->create([
                'stocktake_code' => 'TMP-' . Str::uuid(),
                'warehouse_id' => $data['warehouse_id'],
                'status' => Stocktake::STATUS_DRAFT,
                'stocktake_date' => $data['stocktake_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $stocktake = $this->stocktakes->update($stocktake, [
                'stocktake_code' => sprintf('SK-%06d', $stocktake->id),
            ]);

            $this->items->createMany($stocktake, $this->buildRows((int) $data['warehouse_id'], $lines));

            return $this->stocktakes->find($stocktake->id);
        });
    }

    /**
     * Sửa DRAFT: thay toàn bộ dòng và CHỤP LẠI snapshot theo tồn hiện tại tại thời điểm lưu.
     * Đây là hành động chủ động của người dùng (khác với việc tự cập nhật snapshot lúc POST — bị cấm).
     */
    public function updateDraft(int $id, array $data): Stocktake
    {
        $lines = $this->normalizeLines($data['items'] ?? []);

        $this->assertWarehouse((int) $data['warehouse_id']);

        return DB::transaction(function () use ($id, $data, $lines) {
            $stocktake = $this->stocktakes->findForUpdate($id);

            $this->assertDraft($stocktake, 'Phiếu kiểm kê đã POST, không thể chỉnh sửa.');

            $this->stocktakes->update($stocktake, [
                'warehouse_id' => $data['warehouse_id'],
                'stocktake_date' => $data['stocktake_date'],
                'note' => $data['note'] ?? null,
            ]);

            $this->items->deleteByStocktake($stocktake);
            $this->items->createMany($stocktake, $this->buildRows((int) $data['warehouse_id'], $lines));

            return $this->stocktakes->find($stocktake->id);
        });
    }

    /** Xoá DRAFT (cùng dòng). POSTED không được xoá. */
    public function deleteDraft(int $id): void
    {
        DB::transaction(function () use ($id) {
            $stocktake = $this->stocktakes->findForUpdate($id);

            $this->assertDraft($stocktake, 'Phiếu kiểm kê đã POST, không thể xoá.');

            $this->items->deleteByStocktake($stocktake);
            $this->stocktakes->delete($stocktake);
        });
    }

    /**
     * DRAFT -> POSTED. Một transaction duy nhất:
     *   1. Khóa phiếu, kiểm tra DRAFT.
     *   2. Khóa Stock các dòng theo product_id ASC và kiểm tra STALE cho TẤT CẢ dòng
     *      (current Stock != system_quantity => REJECT, không tự cập nhật snapshot, chưa điều chỉnh gì).
     *   3. Với dòng có chênh lệch: InventoryService::adjustStock(actual). Dòng không chênh lệch: bỏ qua.
     *   4. Đánh dấu POSTED. Bất kỳ lỗi nào => rollback toàn bộ.
     */
    public function post(int $id, ?int $userId = null): Stocktake
    {
        return DB::transaction(function () use ($id, $userId) {
            $stocktake = $this->stocktakes->findForUpdate($id);

            $this->assertDraft($stocktake, 'Phiếu kiểm kê này đã được POST, không thể POST lần nữa.');

            $items = $this->items->getByStocktake($stocktake)->sortBy('product_id')->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'Phiếu kiểm kê chưa có sản phẩm nào để chốt.',
                ]);
            }

            $warehouseId = (int) $stocktake->warehouse_id;

            // --- Bước 2: khóa + kiểm tra stale TOÀN BỘ trước khi điều chỉnh bất kỳ dòng nào ---
            $stale = [];

            foreach ($items as $item) {
                $stock = $this->stocks->getForUpdate($warehouseId, (int) $item->product_id);
                $current = $stock !== null ? (float) $stock->quantity_on_hand : 0.0;

                if (! $this->sameQuantity($current, (float) $item->system_quantity)) {
                    $stale[] = [
                        'sku' => $item->product?->sku ?? ('#' . $item->product_id),
                        'snapshot' => (float) $item->system_quantity,
                        'current' => $current,
                    ];
                }
            }

            if ($stale !== []) {
                throw ValidationException::withMessages(['document' => $this->staleMessage($stale)]);
            }

            // --- Bước 3: điều chỉnh qua InventoryService ---
            foreach ($items as $item) {
                if ($this->sameQuantity((float) $item->actual_quantity, (float) $item->system_quantity)) {
                    continue; // không chênh lệch: không gọi adjustStock, không tạo movement 0
                }

                try {
                    $this->inventoryService->adjustStock(
                        $warehouseId,
                        (int) $item->product_id,
                        (float) $item->actual_quantity,
                        [
                            'reference_type' => 'stocktake',
                            'reference_id' => $stocktake->id,
                            'user_id' => $userId,
                            'note' => 'Kiểm kê theo phiếu ' . $stocktake->stocktake_code,
                        ]
                    );
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'document' => sprintf(
                            '%s: %s',
                            $item->product?->sku ?? ('#' . $item->product_id),
                            collect($e->errors())->flatten()->first()
                        ),
                    ]);
                }
            }

            $this->stocktakes->update($stocktake, [
                'status' => Stocktake::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $this->stocktakes->find($stocktake->id);
        });
    }

    private function assertDraft(Stocktake $stocktake, string $message): void
    {
        if (! $stocktake->isDraft()) {
            throw ValidationException::withMessages(['document' => $message]);
        }
    }

    private function assertWarehouse(int $warehouseId): void
    {
        $warehouse = $this->stocktakes->findWarehouse($warehouseId);

        if ($warehouse === null) {
            throw ValidationException::withMessages(['warehouse_id' => 'Kho không tồn tại.']);
        }

        if (! $warehouse->is_active) {
            throw ValidationException::withMessages(['warehouse_id' => 'Kho đang ngừng hoạt động.']);
        }
    }

    /** Snapshot tồn hệ thống: đọc Stock hiện tại của ĐÚNG kho; chưa có Stock => 0 (không tạo Stock). */
    private function buildRows(int $warehouseId, array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $system = $this->currentQuantity($warehouseId, $line['product_id']);
            $actual = (float) $line['actual_quantity'];

            $rows[] = [
                'product_id' => $line['product_id'],
                'system_quantity' => $system,
                'actual_quantity' => $actual,
                'difference' => round($actual - $system, 3), // difference = actual - system
            ];
        }

        return $rows;
    }

    private function currentQuantity(int $warehouseId, int $productId): float
    {
        $stock = $this->stocks->findByWarehouseAndProduct($warehouseId, $productId);

        return $stock !== null ? (float) $stock->quantity_on_hand : 0.0;
    }

    private function sameQuantity(float $a, float $b): bool
    {
        return abs($a - $b) < self::EPSILON;
    }

    private function staleMessage(array $stale): string
    {
        $first = $stale[0];

        $message = sprintf(
            'Không thể chốt phiếu kiểm kê. Tồn kho của %s đã thay đổi kể từ khi phiếu được tạo. '
            . 'Tồn hệ thống lúc snapshot: %s. Tồn hiện tại: %s.',
            $first['sku'],
            $this->formatQuantity($first['snapshot']),
            $this->formatQuantity($first['current'])
        );

        if (count($stale) > 1) {
            $message .= sprintf(' (Và %d sản phẩm khác cũng đã thay đổi.)', count($stale) - 1);
        }

        return $message . ' Vui lòng kiểm tra lại số đếm, bấm Sửa rồi Lưu phiếu để chụp lại tồn hệ thống trước khi chốt.';
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function normalizeLines(array $items): array
    {
        $items = array_values($items);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Phiếu kiểm kê phải có ít nhất 1 sản phẩm.',
            ]);
        }

        $seen = [];
        $productIds = [];

        foreach ($items as $i => $item) {
            if (empty($item['product_id'])) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Thiếu sản phẩm.']);
            }

            if (! isset($item['actual_quantity']) || $item['actual_quantity'] === '' || ! is_numeric($item['actual_quantity'])) {
                throw ValidationException::withMessages(["items.$i.actual_quantity" => 'Số lượng thực tế không hợp lệ.']);
            }

            if ((float) $item['actual_quantity'] < 0) {
                throw ValidationException::withMessages(["items.$i.actual_quantity" => 'Số lượng thực tế không được nhỏ hơn 0.']);
            }

            $productId = (int) $item['product_id'];

            if (isset($seen[$productId])) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Sản phẩm bị trùng trong phiếu kiểm kê.']);
            }

            $seen[$productId] = true;
            $productIds[] = $productId;
        }

        $existing = $this->products->existingIds($productIds);

        $lines = [];

        foreach ($items as $i => $item) {
            $productId = (int) $item['product_id'];

            if (! in_array($productId, $existing, true)) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Sản phẩm không tồn tại.']);
            }

            $lines[] = ['product_id' => $productId, 'actual_quantity' => $item['actual_quantity']];
        }

        return $lines;
    }
}