<?php

namespace App\Services;

use App\Models\PurchaseReceipt;
use App\Repositories\PurchaseReceiptItemRepository;
use App\Repositories\PurchaseReceiptRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nghiệp vụ chứng từ nhập kho. KHÔNG tự đụng Stock/StockLot/StockMovement:
 * mọi thay đổi tồn kho đi qua InventoryService::receiveStock() (boundary duy nhất).
 */
class PurchaseReceiptService
{
    public function __construct(
        protected PurchaseReceiptRepository $receipts,
        protected PurchaseReceiptItemRepository $items,
        protected InventoryService $inventoryService,
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->receipts->paginate($filters);
    }

    public function find(int $id): PurchaseReceipt
    {
        return $this->receipts->find($id);
    }

    /**
     * Tạo phiếu DRAFT. Không thay đổi inventory.
     * $data: supplier_id, warehouse_id, receipt_date, note?, items[]{product_id, quantity, unit_price?, expiry_date?}
     */
    public function createDraft(array $data, ?int $userId = null): PurchaseReceipt
    {
        $items = $this->assertValidItems($data['items'] ?? []);

        return DB::transaction(function () use ($data, $items, $userId) {
            // Mã tạm duy nhất, đổi ngay sang mã theo id trong cùng transaction
            // (tránh race khi 2 người tạo phiếu cùng lúc).
            $receipt = $this->receipts->create([
                'receipt_code' => 'TMP-' . Str::uuid(),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => PurchaseReceipt::STATUS_DRAFT,
                'receipt_date' => $data['receipt_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $receipt = $this->receipts->update($receipt, [
                'receipt_code' => sprintf('PN-%06d', $receipt->id),
            ]);

            $this->items->createMany($receipt, $items);

            return $this->receipts->find($receipt->id);
        });
    }

    /** Sửa phiếu — chỉ khi còn DRAFT. Thay toàn bộ items. */
    public function updateDraft(int $receiptId, array $data): PurchaseReceipt
    {
        $items = $this->assertValidItems($data['items'] ?? []);

        return DB::transaction(function () use ($receiptId, $data, $items) {
            $receipt = $this->receipts->findForUpdate($receiptId);

            if (! $receipt->isDraft()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu đã nhập kho, không thể chỉnh sửa.',
                ]);
            }

            $this->receipts->update($receipt, [
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'receipt_date' => $data['receipt_date'],
                'note' => $data['note'] ?? null,
            ]);

            $this->items->deleteByReceipt($receipt);
            $this->items->createMany($receipt, $items);

            return $this->receipts->find($receipt->id);
        });
    }

    /**
     * DRAFT -> POSTED. Atomic: 1 item lỗi => rollback toàn bộ (kể cả status).
     * Chặn POST lần 2 bằng row lock + kiểm tra status trong transaction.
     */
    public function post(int $receiptId, ?int $userId = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($receiptId, $userId) {
            $receipt = $this->receipts->findForUpdate($receiptId);

            if (! $receipt->isDraft()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu này đã được nhập kho, không thể POST lần nữa.',
                ]);
            }

            $items = $this->items->getByReceipt($receipt);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Phiếu chưa có sản phẩm nào để nhập kho.',
                ]);
            }

            foreach ($items as $item) {
                // Nested transaction của InventoryService thành savepoint;
                // exception ở item bất kỳ sẽ rollback cả transaction ngoài.
                $this->inventoryService->receiveStock(
                    (int) $receipt->warehouse_id,
                    (int) $item->product_id,
                    (float) $item->quantity,
                    now(),
                    $item->expiry_date,
                    [
                        'reference_type' => 'purchase_receipt',
                        'reference_id' => $receipt->id,
                        'user_id' => $userId,
                        'note' => 'Nhập kho theo phiếu ' . $receipt->receipt_code,
                    ]
                );
            }

            $this->receipts->update($receipt, [
                'status' => PurchaseReceipt::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $this->receipts->find($receipt->id);
        });
    }

    /**
     * Bảo vệ invariant ở tầng domain (không phụ thuộc FormRequest).
     */
    private function assertValidItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Phiếu nhập phải có ít nhất 1 sản phẩm.',
            ]);
        }

        foreach ($items as $i => $item) {
            if (empty($item['product_id'])) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Thiếu sản phẩm.']);
            }
            if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || (float) $item['quantity'] <= 0) {
                throw ValidationException::withMessages(["items.$i.quantity" => 'Số lượng phải lớn hơn 0.']);
            }
            if (isset($item['unit_price']) && $item['unit_price'] !== '' && (! is_numeric($item['unit_price']) || (float) $item['unit_price'] < 0)) {
                throw ValidationException::withMessages(["items.$i.unit_price" => 'Đơn giá không hợp lệ.']);
            }
        }

        return array_values($items);
    }
}