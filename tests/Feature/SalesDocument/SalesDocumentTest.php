<?php

namespace Tests\Feature\SalesDocument;

use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use App\Models\StockAllocation;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Repositories\SalesDocumentRepository;
use App\Repositories\StockMovementRepository;
use App\Services\CustomerService;
use App\Services\InventoryService;
use App\Services\SalesDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesDocumentTest extends TestCase
{
    use RefreshDatabase, SalesDocumentFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSalesFixtures();
    }

    private function outMovements()
    {
        return StockMovement::where('movement_type', 'out');
    }

    private function assertNoOutflow(): void
    {
        $this->assertSame(0, $this->outMovements()->count());
        $this->assertSame(0, StockAllocation::count());
    }

    // ------------------------------------------------------------------ 1-5: DRAFT

    public function test_create_draft_does_not_touch_inventory(): void
    {
        $this->receive($this->wh1, $this->productA, 10);

        $document = $this->salesService()->createDraft($this->payload(), $this->admin->id);

        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->status);
        $this->assertStringStartsWith('SD-', $document->document_code);
        $this->assertSame($this->admin->id, $document->created_by);
        $this->assertNull($document->posted_at);
        $this->assertCount(1, $document->items);

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertNoOutflow();
    }

    public function test_customer_is_nullable_for_walk_in_customer(): void
    {
        $document = $this->salesService()->createDraft($this->payload(null, ['customer_id' => null]));

        $this->assertNull($document->customer_id);
        $this->assertNull($document->customer);
    }

    public function test_customer_can_be_linked_without_any_account(): void
    {
        $document = $this->salesService()->createDraft($this->payload(null, ['customer_id' => $this->customer->id]));

        $this->assertTrue($document->customer->is($this->customer));
        $this->assertNull($this->customer->account);
    }

    public function test_item_snapshots_product_selling_price_unless_overridden(): void
    {
        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 1],                       // thiếu giá -> snapshot
            ['product_id' => $this->productB->id, 'quantity' => 2, 'unit_price' => 45000], // giá nhập tay
            ['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => 0],     // giá 0 hợp lệ
        ]));

        $prices = $document->items->pluck('unit_price')->map(fn ($p) => (float) $p)->all();

        $this->assertEqualsWithDelta(100000.0, $prices[0], 0.001);
        $this->assertEqualsWithDelta(45000.0, $prices[1], 0.001);
        $this->assertEqualsWithDelta(0.0, $prices[2], 0.001);
    }

    public function test_changing_product_price_later_does_not_change_snapshot(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->salesService()->createDraft($this->payload());

        $this->productA->update(['selling_price' => 120000]);

        $this->assertEqualsWithDelta(100000.0, (float) $document->fresh()->items->first()->unit_price, 0.001);

        $this->salesService()->post($document->id);

        $this->productA->update(['selling_price' => 150000]);
        $this->assertEqualsWithDelta(100000.0, (float) $document->fresh()->items->first()->unit_price, 0.001);
    }

    public function test_total_is_computed_from_items(): void
    {
        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 2],                         // 200000
            ['product_id' => $this->productB->id, 'quantity' => 3, 'unit_price' => 10000],  // 30000
        ]));

        $detail = $this->salesService()->findDetail($document->id);

        $this->assertEqualsWithDelta(230000.0, (float) $detail->total_amount, 0.001);
    }

    public function test_draft_validates_items_at_service_level(): void
    {
        foreach ([
            [],
            [['product_id' => $this->productA->id, 'quantity' => 0]],
            [['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => -5]],
            [['product_id' => 999999, 'quantity' => 1]],
        ] as $items) {
            try {
                $this->salesService()->createDraft($this->payload($items));
                $this->fail('Phải bị từ chối.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, SalesDocument::count());
    }

    // ------------------------------------------------------------------ 6-10: POST + FIFO

    public function test_post_issues_stock_through_inventory_service_fifo(): void
    {
        $this->receive($this->wh1, $this->productA, 10, now()->subDay());
        $this->receive($this->wh1, $this->productA, 5, now());

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 12],
        ]), $this->admin->id);

        $posted = $this->salesService()->post($document->id, $this->admin->id);

        // 6: status + posted_at
        $this->assertSame(SalesDocument::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->posted_at);

        // 8: Stock giảm đúng
        $this->assertEqualsWithDelta(3.0, $this->onHand($this->wh1, $this->productA), 0.0005);

        // 9: StockLot giảm theo FIFO (lô cũ hết trước)
        $lots = StockLot::where('warehouse_id', $this->wh1->id)->where('product_id', $this->productA->id)
            ->orderBy('received_at')->orderBy('id')->get();
        $this->assertEqualsWithDelta(0.0, (float) $lots[0]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(3.0, (float) $lots[1]->quantity_remaining, 0.0005);

        // 7: OUT movement + reference + user
        $movement = $this->outMovements()->firstOrFail();
        $this->assertSame(1, $this->outMovements()->count());
        $this->assertEqualsWithDelta(-12.0, (float) $movement->quantity, 0.0005);
        $this->assertSame('sales_document', $movement->reference_type);
        $this->assertSame($document->id, (int) $movement->reference_id);
        $this->assertSame($this->admin->id, $movement->user_id);

        // 7 + 10: allocation đúng lô, tổng = |OUT|
        $allocations = StockAllocation::where('stock_movement_id', $movement->id)->orderBy('stock_lot_id')->get();
        $this->assertCount(2, $allocations);
        $this->assertEqualsWithDelta(10.0, (float) $allocations[0]->quantity, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $allocations[1]->quantity, 0.0005);
        $this->assertEqualsWithDelta(abs((float) $movement->quantity), (float) $allocations->sum('quantity'), 0.0005);

        // Invariant Stock = SUM(lot remaining)
        $this->assertEqualsWithDelta($this->onHand($this->wh1, $this->productA), $this->lotSum($this->wh1, $this->productA), 0.0005);
    }

    // ------------------------------------------------------------------ 11-12: thiếu tồn

    public function test_post_exceeding_stock_is_rejected_without_partial_change(): void
    {
        $this->receive($this->wh1, $this->productA, 10);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 11],
        ]));

        try {
            $this->salesService()->post($document->id);
            $this->fail('Phải bị từ chối vì thiếu tồn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
            $this->assertStringContainsString('HG54', $e->errors()['document'][0]);
        }

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh1, $this->productA), 0.0005);
        $this->assertNoOutflow();
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->fresh()->status);
        $this->assertNull($document->fresh()->posted_at);
    }

    // ------------------------------------------------------------------ 13-14

    public function test_post_multiple_items_succeeds(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $this->receive($this->wh1, $this->productB, 5);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 7],
            ['product_id' => $this->productB->id, 'quantity' => 5],
        ]));

        $this->salesService()->post($document->id);

        $this->assertEqualsWithDelta(3.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(0.0, $this->onHand($this->wh1, $this->productB), 0.0005);
        $this->assertSame(2, $this->outMovements()->count());
        $this->assertSame(SalesDocument::STATUS_POSTED, $document->fresh()->status);
    }

    public function test_second_item_insufficient_rolls_back_first_item(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $this->receive($this->wh1, $this->productB, 5);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 7],
            ['product_id' => $this->productB->id, 'quantity' => 6],
        ]));

        try {
            $this->salesService()->post($document->id);
            $this->fail('Phải rollback vì dòng B thiếu tồn.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dòng 2', $e->errors()['document'][0]);
        }

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(5.0, $this->onHand($this->wh1, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh1, $this->productA), 0.0005);
        $this->assertNoOutflow();
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->fresh()->status);
        $this->assertNull($document->fresh()->posted_at);
    }

    // ------------------------------------------------------------------ 15-16

    public function test_cannot_post_twice(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->salesService()->createDraft($this->payload());
        $first = $this->salesService()->post($document->id);
        $postedAt = $first->posted_at;

        try {
            $this->salesService()->post($document->id);
            $this->fail('POST lần 2 phải bị chặn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->assertEqualsWithDelta(6.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertSame(1, $this->outMovements()->count());
        $this->assertSame(1, StockAllocation::count());
        $this->assertTrue($postedAt->equalTo($document->fresh()->posted_at));
    }

    /**
     * SQLite in-memory không mô phỏng được 2 request đồng thời thật; test này xác nhận post()
     * LUÔN lấy row lock (findForUpdate) trước khi xử lý — cơ chế chống double-post trên MySQL.
     */
    public function test_post_acquires_row_lock_on_document(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->salesService()->createDraft($this->payload());

        $this->partialMock(SalesDocumentRepository::class, function ($mock) {
            $mock->shouldReceive('findForUpdate')->once()->passthru();
        });
        $this->app->forgetInstance(SalesDocumentService::class);

        $this->salesService()->post($document->id);

        $this->assertSame(SalesDocument::STATUS_POSTED, $document->fresh()->status);
    }

    // ------------------------------------------------------------------ 17-20

    public function test_posted_document_cannot_be_edited(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->salesService()->createDraft($this->payload());
        $this->salesService()->post($document->id);

        try {
            $this->salesService()->updateDraft($document->id, $this->payload([
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ], ['warehouse_id' => $this->wh2->id, 'customer_id' => $this->customer->id]));
            $this->fail('Không được sửa chứng từ đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $fresh = $document->fresh();
        $this->assertSame($this->wh1->id, $fresh->warehouse_id);
        $this->assertNull($fresh->customer_id);
        $this->assertEqualsWithDelta(4.0, (float) $fresh->items->first()->quantity, 0.0005);
    }

    public function test_posted_document_cannot_be_deleted(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->salesService()->createDraft($this->payload());
        $this->salesService()->post($document->id);

        try {
            $this->salesService()->deleteDraft($document->id);
            $this->fail('Không được xoá chứng từ đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->assertDatabaseHas('sales_documents', ['id' => $document->id]);
        $this->assertSame(1, SalesDocumentItem::where('sales_document_id', $document->id)->count());
    }

    public function test_draft_can_be_edited(): void
    {
        $document = $this->salesService()->createDraft($this->payload());

        $updated = $this->salesService()->updateDraft($document->id, $this->payload([
            ['product_id' => $this->productB->id, 'quantity' => 9],
            ['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => 90000],
        ], ['warehouse_id' => $this->wh2->id, 'customer_id' => $this->customer->id, 'note' => 'Đã sửa']));

        $this->assertSame($this->wh2->id, $updated->warehouse_id);
        $this->assertSame($this->customer->id, $updated->customer_id);
        $this->assertSame('Đã sửa', $updated->note);
        $this->assertCount(2, $updated->items);
        $this->assertSame($document->document_code, $updated->document_code);
        $this->assertSame(2, SalesDocumentItem::where('sales_document_id', $document->id)->count());
    }

    public function test_draft_can_be_deleted_with_items(): void
    {
        $document = $this->salesService()->createDraft($this->payload());

        $this->salesService()->deleteDraft($document->id);

        $this->assertDatabaseMissing('sales_documents', ['id' => $document->id]);
        $this->assertSame(0, SalesDocumentItem::count());
    }

    // ------------------------------------------------------------------ 21-22: isolation

    public function test_other_warehouse_and_other_product_are_not_affected(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $this->receive($this->wh2, $this->productA, 10);
        $this->receive($this->wh1, $this->productB, 10);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 4],
        ]));
        $this->salesService()->post($document->id);

        $this->assertEqualsWithDelta(6.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh2, $this->productA), 0.0005);   // 21
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productB), 0.0005);   // 22
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh2, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh1, $this->productB), 0.0005);
    }

    // ------------------------------------------------------------------ 23: duplicate product lines

    public function test_duplicate_product_lines_are_issued_correctly(): void
    {
        $this->receive($this->wh1, $this->productA, 11);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 4],
            ['product_id' => $this->productA->id, 'quantity' => 7],
        ]));
        $this->salesService()->post($document->id);

        $this->assertEqualsWithDelta(0.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertSame(2, $this->outMovements()->count());
        $this->assertEqualsWithDelta(11.0, (float) StockAllocation::sum('quantity'), 0.0005);
        $this->assertEqualsWithDelta($this->onHand($this->wh1, $this->productA), $this->lotSum($this->wh1, $this->productA), 0.0005);
    }

    public function test_duplicate_lines_exceeding_total_stock_roll_back_everything(): void
    {
        $this->receive($this->wh1, $this->productA, 10);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 4],
            ['product_id' => $this->productA->id, 'quantity' => 7],
        ]));

        try {
            $this->salesService()->post($document->id);
            $this->fail('4 + 7 > 10 phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dòng 2', $e->errors()['document'][0]);
        }

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh1, $this->productA), 0.0005);
        $this->assertNoOutflow();
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->fresh()->status);
    }

    // ------------------------------------------------------------------ 24: rollback khi infrastructure lỗi

    public function test_failure_on_second_item_rolls_back_first_items_stock_lot_movement_allocation(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $this->receive($this->wh1, $this->productB, 10);

        $document = $this->salesService()->createDraft($this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 3],
            ['product_id' => $this->productB->id, 'quantity' => 3],
        ]));

        // Dòng 1 ghi movement thật (và allocation), dòng 2 ném lỗi hạ tầng -> phải rollback cả dòng 1.
        $this->partialMock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->once()->passthru();
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('simulated failure'));
        });
        $this->app->forgetInstance(InventoryService::class);
        $this->app->forgetInstance(SalesDocumentService::class);

        try {
            $this->salesService()->post($document->id);
            $this->fail('Phải ném exception ở dòng thứ 2.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->wh1, $this->productA), 0.0005);
        $this->assertNoOutflow();
        $this->assertSame(SalesDocument::STATUS_DRAFT, $document->fresh()->status);
        $this->assertNull($document->fresh()->posted_at);
    }

    // ------------------------------------------------------------------ Customer guard (Phase J)

    public function test_customer_with_sales_document_cannot_be_deleted(): void
    {
        $this->salesService()->createDraft($this->payload(null, ['customer_id' => $this->customer->id]));

        try {
            app(CustomerService::class)->delete($this->customer);
            $this->fail('Không được xoá khách hàng đã có chứng từ bán.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer', $e->errors());
        }

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
    }
}