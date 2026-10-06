<?php

namespace App\Services;

use App\Models\SalesDocument;
use App\Repositories\ProductRepository;
use App\Repositories\SalesDocumentItemRepository;
use App\Repositories\SalesDocumentRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nghiệp vụ chứng từ bán hàng. KHÔNG tự đụng Stock/StockLot/StockMovement/StockAllocation:
 * mọi thay đổi tồn kho đi qua InventoryService::issueStock() (boundary duy nhất, giữ nguyên FIFO).
 */
class SalesDocumentService
{
    public function __construct(
        protected SalesDocumentRepository $documents,
        protected SalesDocumentItemRepository $items,
        protected ProductRepository $products,
        protected InventoryService $inventoryService,
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->documents->paginate($filters);
    }

    /** Chi tiết + tổng tiền (tính từ items, không lưu cột total riêng). */
    public function findDetail(int $id): SalesDocument
    {
        $document = $this->documents->find($id);

        $total = 0.0;
        foreach ($document->items as $item) {
            $line = round((float) $item->quantity * (float) $item->unit_price, 2);
            $item->setAttribute('line_total', $line);
            $total += $line;
        }
        $document->setAttribute('total_amount', round($total, 2));

        return $document;
    }

    /** Dùng cho trang Edit: chỉ cho phép khi còn DRAFT. */
    public function findEditable(int $id): SalesDocument
    {
        $document = $this->findDetail($id);

        if (! $document->isDraft()) {
            throw ValidationException::withMessages([
                'document' => 'Chứng từ đã POST, không thể chỉnh sửa.',
            ]);
        }

        return $document;
    }

    /**
     * Tạo DRAFT. Không gọi InventoryService, không đổi tồn kho.
     * $data: customer_id?, warehouse_id, document_date, note?, items[]{product_id, quantity, unit_price?}
     */
    public function createDraft(array $data, ?int $userId = null): SalesDocument
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        return DB::transaction(function () use ($data, $items, $userId) {
            // Mã tạm duy nhất rồi đổi sang mã theo id trong cùng transaction (tránh race).
            $document = $this->documents->create([
                'document_code' => 'TMP-' . Str::uuid(),
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'status' => SalesDocument::STATUS_DRAFT,
                'document_date' => $data['document_date'],
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $document = $this->documents->update($document, [
                'document_code' => sprintf('SD-%06d', $document->id),
            ]);

            $this->items->createMany($document, $items);

            return $this->documents->find($document->id);
        });
    }

    /** Sửa header + thay toàn bộ items — chỉ khi còn DRAFT. */
    public function updateDraft(int $id, array $data): SalesDocument
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        return DB::transaction(function () use ($id, $data, $items) {
            $document = $this->documents->findForUpdate($id);

            $this->assertDraft($document, 'Chứng từ đã POST, không thể chỉnh sửa.');

            $this->documents->update($document, [
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'document_date' => $data['document_date'],
                'note' => $data['note'] ?? null,
            ]);

            $this->items->deleteByDocument($document);
            $this->items->createMany($document, $items);

            return $this->documents->find($document->id);
        });
    }

    /** Xoá DRAFT (cùng items). POSTED không được xoá. */
    public function deleteDraft(int $id): void
    {
        DB::transaction(function () use ($id) {
            $document = $this->documents->findForUpdate($id);

            $this->assertDraft($document, 'Chứng từ đã POST, không thể xoá.');

            $this->items->deleteByDocument($document);
            $this->documents->delete($document);
        });
    }

    /**
     * DRAFT -> POSTED. Atomic: 1 dòng lỗi => rollback TOÀN BỘ (tồn kho, lot, movement,
     * allocation, status, posted_at). Row lock + kiểm tra status chặn POST lần hai / đồng thời.
     */
    public function post(int $id, ?int $userId = null): SalesDocument
    {
        return DB::transaction(function () use ($id, $userId) {
            $document = $this->documents->findForUpdate($id);

            $this->assertDraft($document, 'Chứng từ này đã được POST, không thể POST lần nữa.');

            $items = $this->items->getByDocument($document);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'Chứng từ chưa có sản phẩm nào để xuất kho.',
                ]);
            }

            foreach ($items->values() as $index => $item) {
                try {
                    // Nested transaction của InventoryService thành savepoint; exception ở dòng
                    // bất kỳ sẽ rollback cả transaction ngoài (kể cả các dòng đã xuất trước đó).
                    $this->inventoryService->issueStock(
                        (int) $document->warehouse_id,
                        (int) $item->product_id,
                        (float) $item->quantity,
                        [
                            'reference_type' => 'sales_document',
                            'reference_id' => $document->id,
                            'user_id' => $userId,
                            'note' => 'Xuất kho theo chứng từ ' . $document->document_code,
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

            $this->documents->update($document, [
                'status' => SalesDocument::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $this->documents->find($document->id);
        });
    }

    private function assertDraft(SalesDocument $document, string $message): void
    {
        if (! $document->isDraft()) {
            throw ValidationException::withMessages(['document' => $message]);
        }
    }

    /**
     * Bảo vệ business invariant ở tầng domain (không phụ thuộc FormRequest) và
     * SNAPSHOT giá: dòng không truyền unit_price sẽ lấy Product.selling_price tại thời điểm này.
     */
    private function normalizeItems(array $items): array
    {
        $items = array_values($items);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Chứng từ bán phải có ít nhất 1 sản phẩm.',
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
            if ($this->hasPrice($item) && (! is_numeric($item['unit_price']) || (float) $item['unit_price'] < 0)) {
                throw ValidationException::withMessages(["items.$i.unit_price" => 'Đơn giá không hợp lệ.']);
            }

            $productIds[] = (int) $item['product_id'];
        }

        $prices = $this->products->sellingPrices(array_values(array_unique($productIds)));

        $normalized = [];

        foreach ($items as $i => $item) {
            $productId = (int) $item['product_id'];

            if (! array_key_exists($productId, $prices)) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'Sản phẩm không tồn tại.']);
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $item['quantity'],
                'unit_price' => $this->hasPrice($item) ? $item['unit_price'] : $prices[$productId],
            ];
        }

        return $normalized;
    }

    private function hasPrice(array $item): bool
    {
        return isset($item['unit_price']) && $item['unit_price'] !== '';
    }
}