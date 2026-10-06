<?php

namespace Tests\Feature\StockTransfer;

use App\Models\StockAllocation;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockRepository;
use App\Repositories\StockTransferRepository;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase, StockTransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTransferFixtures();
    }

    private function snapshotCounts(): array
    {
        return [
            'movements' => StockMovement::count(),
            'lots' => StockLot::count(),
            'allocations' => StockAllocation::count(),
            'stocks' => Stock::count(),
        ];
    }

    private function draft(array $items = null, array $overrides = []): StockTransfer
    {
        return $this->transferService()->createDraft($this->payload($items, $overrides), $this->admin->id);
    }

    // ------------------------------------------------------------------ 1: draft

    public function test_create_draft_does_not_change_inventory(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $before = $this->snapshotCounts();

        $transfer = $this->draft();

        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->status);
        $this->assertStringStartsWith('ST-', $transfer->transfer_code);
        $this->assertSame($this->admin->id, $transfer->created_by);
        $this->assertNull($transfer->posted_at);
        $this->assertCount(1, $transfer->items);
        $this->assertTrue($transfer->fromWarehouse->is($this->whA));
        $this->assertTrue($transfer->toWarehouse->is($this->whB));

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
    }

    public function test_draft_validates_items_at_service_level(): void
    {
        foreach ([
            [],
            [['product_id' => $this->productA->id, 'quantity' => 0]],
            [['product_id' => 999999, 'quantity' => 1]],
        ] as $items) {
            try {
                $this->draft($items);
                $this->fail('Phải bị từ chối.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, StockTransfer::count());
    }

    // ------------------------------------------------------------------ 2: basic

    public function test_post_basic_transfer_moves_stock_between_warehouses(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();

        $posted = $this->transferService()->post($transfer->id, $this->admin->id);

        $this->assertSame(StockTransfer::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->posted_at);
        $this->assertEqualsWithDelta(6.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(4.0, $this->onHand($this->whB, $this->productA), 0.0005);
    }

    // ------------------------------------------------------------------ 3-6, 8-10: FIFO + lot age + invariants

    public function test_post_consumes_source_fifo_and_preserves_lot_age_in_destination(): void
    {
        $this->seedAgedLots();
        $totalBefore = $this->totalOnHand($this->productA);

        $transfer = $this->draft([['product_id' => $this->productA->id, 'quantity' => 12]]);
        $this->transferService()->post($transfer->id, $this->admin->id);

        // 3: FIFO ở nguồn — lô cũ hết trước
        $source = $this->lots($this->whA, $this->productA);
        $this->assertLot($source[0], 10, 0, $this->ageOld(), null);
        $this->assertLot($source[1], 5, 3, $this->ageNew(), '2027-01-01');

        // 4+5+6: kho đích có 2 lot riêng, giữ received_at + expiry_date, không gộp, không dùng now()
        $dest = $this->lots($this->whB, $this->productA);
        $this->assertCount(2, $dest);
        $this->assertLot($dest[0], 10, 10, $this->ageOld(), null);
        $this->assertLot($dest[1], 2, 2, $this->ageNew(), '2027-01-01');

        // OUT movement ở nguồn
        $out = StockMovement::where('movement_type', 'out')->where('warehouse_id', $this->whA->id)->firstOrFail();
        $this->assertEqualsWithDelta(-12.0, (float) $out->quantity, 0.0005);
        $this->assertEqualsWithDelta(15.0, (float) $out->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(3.0, (float) $out->quantity_after, 0.0005);
        $this->assertSame('stock_transfer', $out->reference_type);
        $this->assertSame($transfer->id, (int) $out->reference_id);
        $this->assertSame($this->admin->id, $out->user_id);

        // 10: allocation đúng lô, tổng = |OUT|
        $allocations = StockAllocation::where('stock_movement_id', $out->id)->orderBy('stock_lot_id')->get();
        $this->assertCount(2, $allocations);
        $this->assertEqualsWithDelta(10.0, (float) $allocations[0]->quantity, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $allocations[1]->quantity, 0.0005);
        $this->assertEqualsWithDelta(abs((float) $out->quantity), (float) $allocations->sum('quantity'), 0.0005);

        // IN movement ở đích: 1 movement cho mỗi lot, before/after liên tục
        $ins = StockMovement::where('movement_type', 'in')
            ->where('warehouse_id', $this->whB->id)->orderBy('id')->get();
        $this->assertCount(2, $ins);
        $this->assertEqualsWithDelta(10.0, (float) $ins[0]->quantity, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $ins[0]->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(10.0, (float) $ins[0]->quantity_after, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $ins[1]->quantity, 0.0005);
        $this->assertEqualsWithDelta(10.0, (float) $ins[1]->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(12.0, (float) $ins[1]->quantity_after, 0.0005);
        foreach ($ins as $in) {
            $this->assertSame('stock_transfer', $in->reference_type);
            $this->assertSame($transfer->id, (int) $in->reference_id);
            $this->assertSame(0, StockAllocation::where('stock_movement_id', $in->id)->count()); // IN không có allocation
        }

        // 8: Stock = SUM(lot) ở cả hai kho
        $this->assertStockMatchesLots($this->whA, $this->productA);
        $this->assertStockMatchesLots($this->whB, $this->productA);

        // 9: tổng tồn không đổi
        $this->assertEqualsWithDelta($totalBefore, $this->totalOnHand($this->productA), 0.0005);
        $this->assertEqualsWithDelta(15.0, $this->totalOnHand($this->productA), 0.0005);
    }

    // ------------------------------------------------------------------ 7: FIFO ở đích sau transfer

    public function test_destination_fifo_respects_transferred_lot_age(): void
    {
        $this->seedAgedLots();
        // Kho B đã có sẵn lot D0 = 4 nhập 10/09 (giữa hai lô được chuyển sang).
        $this->receive($this->whB, $this->productA, 4, \Carbon\Carbon::parse('2026-09-10 08:00:00'));

        $transfer = $this->draft([['product_id' => $this->productA->id, 'quantity' => 12]]);
        $this->transferService()->post($transfer->id);

        // Thứ tự FIFO ở kho B: B1 (01/09, 10), D0 (10/09, 4), B2 (20/09, 2)
        $before = $this->lots($this->whB, $this->productA);
        $this->assertCount(3, $before);
        $this->assertEqualsWithDelta(10.0, (float) $before[0]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(4.0, (float) $before[1]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $before[2]->quantity_remaining, 0.0005);

        // Xuất 12 tại kho B: phải lấy hết B1 (10) rồi D0 (2); B2 (lô mới nhất) không bị đụng.
        app(InventoryService::class)->issueStock($this->whB->id, $this->productA->id, 12);

        $after = $this->lots($this->whB, $this->productA);
        $this->assertEqualsWithDelta(0.0, (float) $after[0]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $after[1]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $after[2]->quantity_remaining, 0.0005);
        $this->assertStockMatchesLots($this->whB, $this->productA);
    }

    // ------------------------------------------------------------------ 11-12: thiếu tồn

    public function test_insufficient_stock_is_rejected_without_partial_change(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft([['product_id' => $this->productA->id, 'quantity' => 11]]);
        $before = $this->snapshotCounts();

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('Phải bị từ chối vì thiếu tồn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
            $this->assertStringContainsString('HG54', $e->errors()['document'][0]);
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->posted_at);
    }

    public function test_second_line_insufficient_rolls_back_first_line_everywhere(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $this->receive($this->whA, $this->productB, 5);
        $before = $this->snapshotCounts();

        $transfer = $this->draft([
            ['product_id' => $this->productA->id, 'quantity' => 7],
            ['product_id' => $this->productB->id, 'quantity' => 6],
        ]);

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('Dòng 2 thiếu tồn phải rollback toàn bộ.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dòng 2', $e->errors()['document'][0]);
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(5.0, $this->onHand($this->whA, $this->productB), 0.0005);
        $this->assertSame(0, Stock::where('warehouse_id', $this->whB->id)->count());
        $this->assertSame(0, StockLot::where('warehouse_id', $this->whB->id)->count());
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
    }

    // ------------------------------------------------------------------ 13-15: kho

    public function test_same_warehouse_is_rejected(): void
    {
        try {
            $this->draft(null, ['to_warehouse_id' => $this->whA->id]);
            $this->fail('Kho nguồn = kho đích phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_warehouse_id', $e->errors());
        }

        $this->assertSame(0, StockTransfer::count());
    }

    public function test_inventory_service_rejects_same_warehouse_and_non_positive_quantity(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $before = $this->snapshotCounts();

        foreach ([
            [$this->whA->id, $this->whA->id, 1.0],
            [$this->whA->id, $this->whB->id, 0.0],
            [$this->whA->id, $this->whB->id, -2.0],
        ] as [$from, $to, $qty]) {
            try {
                app(InventoryService::class)->transferStock($from, $to, $this->productA->id, $qty);
                $this->fail('Phải bị từ chối.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame($before, $this->snapshotCounts());
    }

    public function test_inactive_source_is_rejected(): void
    {
        try {
            $this->draft(null, ['from_warehouse_id' => $this->whInactive->id]);
            $this->fail('Kho nguồn ngừng hoạt động phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('from_warehouse_id', $e->errors());
        }

        $this->assertSame(0, StockTransfer::count());
    }

    public function test_inactive_destination_is_rejected(): void
    {
        try {
            $this->draft(null, ['to_warehouse_id' => $this->whInactive->id]);
            $this->fail('Kho đích ngừng hoạt động phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_warehouse_id', $e->errors());
        }

        $this->assertSame(0, StockTransfer::count());
    }

    public function test_warehouse_deactivated_after_draft_is_rejected_at_post(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();
        $before = $this->snapshotCounts();

        $this->whB->forceFill(['is_active' => false])->save();

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('Kho đích đã ngừng hoạt động phải bị từ chối khi POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_warehouse_id', $e->errors());
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
    }

    // ------------------------------------------------------------------ 16-17

    public function test_cannot_post_twice(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();
        $first = $this->transferService()->post($transfer->id);
        $postedAt = $first->posted_at;
        $after = $this->snapshotCounts();

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('POST lần 2 phải bị chặn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->assertSame($after, $this->snapshotCounts());
        $this->assertEqualsWithDelta(6.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(4.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertTrue($postedAt->equalTo($transfer->fresh()->posted_at));
    }

    public function test_posted_transfer_is_immutable(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();
        $this->transferService()->post($transfer->id);

        try {
            $this->transferService()->updateDraft($transfer->id, $this->payload([
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ], ['to_warehouse_id' => $this->whC->id]));
            $this->fail('Không được sửa chứng từ đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        try {
            $this->transferService()->deleteDraft($transfer->id);
            $this->fail('Không được xoá chứng từ đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $fresh = $transfer->fresh();
        $this->assertSame($this->whB->id, $fresh->to_warehouse_id);
        $this->assertEqualsWithDelta(4.0, (float) $fresh->items->first()->quantity, 0.0005);
        $this->assertSame(1, StockTransferItem::where('stock_transfer_id', $transfer->id)->count());
    }

    public function test_draft_can_be_edited_and_deleted(): void
    {
        $transfer = $this->draft();

        $updated = $this->transferService()->updateDraft($transfer->id, $this->payload([
            ['product_id' => $this->productB->id, 'quantity' => 9],
            ['product_id' => $this->productA->id, 'quantity' => 1],
        ], ['to_warehouse_id' => $this->whC->id, 'note' => 'Đã sửa']));

        $this->assertSame($this->whC->id, $updated->to_warehouse_id);
        $this->assertSame('Đã sửa', $updated->note);
        $this->assertCount(2, $updated->items);
        $this->assertSame($transfer->transfer_code, $updated->transfer_code);

        $this->transferService()->deleteDraft($transfer->id);

        $this->assertDatabaseMissing('stock_transfers', ['id' => $transfer->id]);
        $this->assertSame(0, StockTransferItem::count());
    }

    // ------------------------------------------------------------------ 18: nhiều dòng cùng Product

    public function test_duplicate_product_lines_are_transferred_correctly(): void
    {
        $this->receive($this->whA, $this->productA, 11);

        $transfer = $this->draft([
            ['product_id' => $this->productA->id, 'quantity' => 4],
            ['product_id' => $this->productA->id, 'quantity' => 7],
        ]);
        $this->transferService()->post($transfer->id);

        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(11.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertSame(2, StockMovement::where('movement_type', 'out')->count());
        $this->assertEqualsWithDelta(11.0, (float) StockAllocation::sum('quantity'), 0.0005);
        $this->assertStockMatchesLots($this->whA, $this->productA);
        $this->assertStockMatchesLots($this->whB, $this->productA);
    }

    public function test_duplicate_lines_exceeding_total_stock_roll_back_everything(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $before = $this->snapshotCounts();

        $transfer = $this->draft([
            ['product_id' => $this->productA->id, 'quantity' => 4],
            ['product_id' => $this->productA->id, 'quantity' => 7],
        ]);

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('4 + 7 > 10 phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dòng 2', $e->errors()['document'][0]);
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
    }

    // ------------------------------------------------------------------ 19-20: isolation

    public function test_other_product_and_third_warehouse_are_not_affected(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $this->receive($this->whA, $this->productB, 10);
        $this->receive($this->whC, $this->productA, 10);

        $transfer = $this->draft([['product_id' => $this->productA->id, 'quantity' => 4]]);
        $this->transferService()->post($transfer->id);

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productB), 0.0005);   // sản phẩm khác
        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whB, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whC, $this->productA), 0.0005);   // kho thứ ba
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->whC, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->lotSum($this->whA, $this->productB), 0.0005);
    }

    // ------------------------------------------------------------------ 21: chuyển ngược

    public function test_transfer_back_keeps_lot_age(): void
    {
        $this->seedAgedLots();

        $forward = $this->draft([['product_id' => $this->productA->id, 'quantity' => 12]]);
        $this->transferService()->post($forward->id);

        $back = $this->draft(
            [['product_id' => $this->productA->id, 'quantity' => 12]],
            ['from_warehouse_id' => $this->whB->id, 'to_warehouse_id' => $this->whA->id]
        );
        $this->transferService()->post($back->id);

        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(15.0, $this->onHand($this->whA, $this->productA), 0.0005);

        // Kho A: 2 lot gốc (L1 = 0, L2 = 3) + 2 lot nhận lại (10 @ 01/09, 2 @ 20/09 + HSD) — tuổi vẫn đúng.
        $lotsA = $this->lots($this->whA, $this->productA);
        $this->assertCount(4, $lotsA);

        $returnedOld = $lotsA->first(fn ($l) => (float) $l->quantity_received === 10.0 && $l->id > 2);
        $returnedNew = $lotsA->first(fn ($l) => (float) $l->quantity_received === 2.0);

        $this->assertLot($returnedOld, 10, 10, $this->ageOld(), null);
        $this->assertLot($returnedNew, 2, 2, $this->ageNew(), '2027-01-01');

        $this->assertStockMatchesLots($this->whA, $this->productA);
        $this->assertStockMatchesLots($this->whB, $this->productA);
        $this->assertEqualsWithDelta(15.0, $this->totalOnHand($this->productA), 0.0005);
    }

    // ------------------------------------------------------------------ 22: rollback khi hạ tầng lỗi

    public function test_infrastructure_failure_on_second_line_rolls_back_first_line(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $this->receive($this->whA, $this->productB, 10);

        $transfer = $this->draft([
            ['product_id' => $this->productA->id, 'quantity' => 3],
            ['product_id' => $this->productB->id, 'quantity' => 3],
        ]);
        $before = $this->snapshotCounts();

        // Dòng 1 ghi thật 2 movement (OUT + IN, vì 1 lot nguồn), dòng 2 ném lỗi ở movement OUT.
        $this->partialMock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->twice()->passthru();
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('simulated failure'));
        });
        $this->app->forgetInstance(InventoryService::class);
        $this->app->forgetInstance(StockTransferService::class);

        try {
            $this->transferService()->post($transfer->id);
            $this->fail('Phải ném exception ở dòng thứ 2.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->posted_at);
    }

    // ------------------------------------------------------------------ 23-24: lock

    /**
     * SQLite in-memory không mô phỏng được 2 request đồng thời thật; test này xác nhận post()
     * LUÔN lấy row lock trên chứng từ (findForUpdate) — cơ chế chống double-post trên MySQL.
     */
    public function test_post_acquires_row_lock_on_transfer(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();

        $this->partialMock(StockTransferRepository::class, function ($mock) {
            $mock->shouldReceive('findForUpdate')->once()->passthru();
        });
        $this->app->forgetInstance(StockTransferService::class);

        $this->transferService()->post($transfer->id);

        $this->assertSame(StockTransfer::STATUS_POSTED, $transfer->fresh()->status);
    }

    public function test_stock_rows_are_locked_in_ascending_warehouse_order_for_forward_transfer(): void
    {
        $this->assertStockLockOrder($this->whA, $this->whB); // A(id nhỏ) -> B(id lớn)
    }

    public function test_stock_rows_are_locked_in_ascending_warehouse_order_for_reverse_transfer(): void
    {
        $this->assertStockLockOrder($this->whB, $this->whA); // B(id lớn) -> A(id nhỏ): vẫn khóa A trước
    }

    private function assertStockLockOrder($from, $to): void
    {
        // Cả hai Stock đã tồn tại -> lockOrCreateStock chỉ gọi getForUpdate đúng 1 lần cho mỗi kho.
        $this->receive($this->whA, $this->productA, 10);
        $this->receive($this->whB, $this->productA, 10);

        $low = min($from->id, $to->id);
        $high = max($from->id, $to->id);
        $productId = $this->productA->id;

        $this->partialMock(StockRepository::class, function ($mock) use ($low, $high, $productId) {
            $mock->shouldReceive('getForUpdate')->with($low, $productId)->once()->ordered()->passthru();
            $mock->shouldReceive('getForUpdate')->with($high, $productId)->once()->ordered()->passthru();
        });
        $this->app->forgetInstance(InventoryService::class);

        app(InventoryService::class)->transferStock($from->id, $to->id, $productId, 3);

        $this->assertEqualsWithDelta(7.0, $this->onHand($from, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(13.0, $this->onHand($to, $this->productA), 0.0005);
    }
}