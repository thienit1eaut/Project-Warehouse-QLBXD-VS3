<?php

namespace App\Services;

use App\Models\StockTransfer;
use App\Repositories\ProductRepository;
use App\Repositories\StockTransferItemRepository;
use App\Repositories\StockTransferRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Điều phối chứng từ chuyển kho. KHÔNG tự đụng Stock/StockLot/StockMovement/StockAllocation:
 * mọi thay đổi tồn kho đi qua InventoryService::transferStock() (boundary duy nhất).
 */
class StockTransferService
{
    public function __construct(
        protected StockTransferRepository $transfers,
        protected StockTransferItemRepository $items,
        protected ProductRepository $products,
        protected InventoryService $inventoryService,
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->transfers->paginate($filters);
    }

    public function findDetail(int $id): StockTransfer
    {
        return $this->transfers->find($id);
    }

    /** Dùng cho trang Edit: chỉ cho phép khi còn DRAFT. */
    public function findEditable(int $id): StockTransfer
    {
        $transfer = $this->transfers->find($id);

        if (! $transfer->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Chứng từ đã POST, không thể chỉnh sửa.',
            ]);
        }

        return $transfer;
    }

    /**
     * Tạo DRAFT. Không gọi InventoryService, không đổi tồn kho.
     * $data: from_warehouse_id, to_warehouse_id, transfer_date, note?, items[]{product_id, quantity}
     */
    public function createDraft(array $data, int $userId): StockTransfer
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        $this->assertWarehouses((int) $data['from_warehouse_id'], (int) $data['to_warehouse_id']);

        return DB::transaction(function () use ($data, $items, $userId) {
            // Mã tạm duy nhất rồi đổi sang mã theo id trong cùng transaction (tránh race).
            $transfer = $this->transfers->create([
                'transfer_code' => 'TMP-' . Str::uuid(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => StockTransfer::STATUS_DRAFT,
                'transfer_date' => $data['transfer_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $transfer = $this->transfers->update($transfer, [
                'transfer_code' => sprintf('ST-%06d', $transfer->id),
            ]);

            $this->items->createMany($transfer, $items);

            return $this->transfers->find($transfer->id);
        });
    }

    /** Sửa header + thay toàn bộ items — chỉ khi còn DRAFT. */
    public function updateDraft(int $id, array $data): StockTransfer
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        $this->assertWarehouses((int) $data['from_warehouse_id'], (int) $data['to_warehouse_id']);

        return DB::transaction(function () use ($id, $data, $items) {
            $transfer = $this->transfers->findForUpdate($id);

            $this->assertDraft($transfer, 'Chứng từ đã POST, không thể chỉnh sửa.');

            $this->transfers->update($transfer, [
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'note' => $data['note'] ?? null,
            ]);

            $this->items->deleteByTransfer($transfer);
            $this->items->createMany($transfer, $items);

            return $this->transfers->find($transfer->id);
        });
    }

    /** Xoá DRAFT (cùng items). POSTED không được xoá. */
    public function deleteDraft(int $id): void
    {
        DB::transaction(function () use ($id) {
            $transfer = $this->transfers->findForUpdate($id);

            $this->assertDraft($transfer, 'Chứng từ đã POST, không thể xoá.');

            $this->items->deleteByTransfer($transfer);
            $this->transfers->delete($transfer);
        });
    }

    /**
     * DRAFT -> POSTED. Một transaction duy nhất: dòng nào lỗi thì rollback TOÀN BỘ
     * (Stock/StockLot nguồn và đích, movement, allocation, status, posted_at).
     * Row lock trên chứng từ + kiểm tra status chặn POST lần hai / đồng thời.
     */
    public function post(int $id, ?int $userId = null): StockTransfer
    {
        return DB::transaction(function () use ($id, $userId) {
            $transfer = $this->transfers->findForUpdate($id);

            $this->assertDraft($transfer, 'Chứng từ này đã được POST, không thể POST lần nữa.');

            $items = $this->items->getByTransfer($transfer);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'Chứng từ chưa có sản phẩm nào để chuyển kho.',
                ]);
            }

            // Kho có thể đã bị ngừng hoạt động kể từ lúc tạo nháp -> kiểm tra lại ở thời điểm POST.
            $this->assertWarehouses((int) $transfer->from_warehouse_id, (int) $transfer->to_warehouse_id);

            foreach ($items->values() as $index => $item) {
                try {
                    // Nested transaction của InventoryService thành savepoint; exception ở dòng bất kỳ
                    // rollback cả transaction ngoài (kể cả các dòng đã chuyển trước đó).
                    $this->inventoryService->transferStock(
                        (int) $transfer->from_warehouse_id,
                        (int) $transfer->to_warehouse_id,
                        (int) $item->product_id,
                        (float) $item->quantity,
                        [
                            'reference_type' => 'stock_transfer',
                            'reference_id' => $transfer->id,
                            'user_id' => $userId,
                            'note' => 'Chuyển kho theo chứng từ ' . $transfer->transfer_code,
                        ]
                    );
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'document' => sprintf(
                            'Dòng %d (%s): %s',
                            $index + 1,
                            $item->product?->sku ?? ('#' . $item->product_id),
                            collect($e->errors())->flatten()->first()
                        ),
                    ]);
                }
            }

            $this->transfers->update($transfer, [
                'status' => StockTransfer::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $this->transfers->find($transfer->id);
        });
    }

    private function assertDraft(StockTransfer $transfer, string $message): void
    {
        if (! $transfer->isDraft()) {
            throw ValidationException::withMessages(['document' => $message]);
        }
    }

    /** Kho nguồn ≠ kho đích; cả hai tồn tại và đang hoạt động. Service tự bảo vệ, không tin FormRequest. */
    private function assertWarehouses(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Kho nguồn và kho đích phải khác nhau.',
            ]);
        }

        $warehouses = $this->transfers->warehousesById([$fromId, $toId]);

        foreach (['from_warehouse_id' => $fromId, 'to_warehouse_id' => $toId] as $field => $warehouseId) {
            $warehouse = $warehouses->get($warehouseId);

            if ($warehouse === null) {
                throw ValidationException::withMessages([$field => 'Kho không tồn tại.']);
            }

            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([$field => 'Kho đang ngừng hoạt động.']);
            }
        }
    }

    private function normalizeItems(array $items): array
    {
        $items = array_values($items);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Chứng từ chuyển kho phải có ít nhất 1 sản phẩm.',
            ]);
        }

        $productIds = [];

        foreach ($items as $i => $item) {
            if (empty($item['product_id'])) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Thiếu sản phẩm.']);
            }
            if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || (float) $item['quantity'] <= 0) {
                throw ValidationException::withMessages(["items.$i.quantity" => 'Số lượng phải lớn hơn 0.']);
            }

            $productIds[] = (int) $item['product_id'];
        }

        $existing = $this->products->existingIds(array_values(array_unique($productIds)));

        $normalized = [];

        foreach ($items as $i => $item) {
            $productId = (int) $item['product_id'];

            if (! in_array($productId, $existing, true)) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Sản phẩm không tồn tại.']);
            }

            $normalized[] = ['product_id' => $productId, 'quantity' => $item['quantity']];
        }

        return $normalized;
    }
}