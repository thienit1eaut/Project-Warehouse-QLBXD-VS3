<?php

namespace Tests\Feature\Stocktake;

use App\Models\Stock;
use App\Models\StockAllocation;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockRepository;
use App\Repositories\StocktakeRepository;
use App\Services\InventoryService;
use App\Services\StocktakeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StocktakeTest extends TestCase
{
    use RefreshDatabase, StocktakeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createStocktakeFixtures();
    }

    private function draft(array $items = null, array $overrides = []): Stocktake
    {
        return $this->stocktakeService()->createDraft($this->payload($items, $overrides), $this->admin->id);
    }

    // ================================================================== A. Create

    public function test_create_draft_does_not_touch_inventory(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $before = $this->snapshotCounts();

        $stocktake = $this->draft();

        $this->assertSame(Stocktake::STATUS_DRAFT, $stocktake->status);
        $this->assertStringStartsWith('SK-', $stocktake->stocktake_code);
        $this->assertSame($this->admin->id, $stocktake->created_by);
        $this->assertNull($stocktake->posted_at);
        $this->assertCount(1, $stocktake->items);
        $this->assertSame($before, $this->snapshotCounts());
        $this->assertSame(0, $this->adjustmentMovements()->count());
    }

    public function test_snapshot_matches_current_stock(): void
    {
        $this->receive($this->whA, $this->productA, 20);

        $item = $this->draft([$this->line($this->productA, 18)])->items->first();

        $this->assertEqualsWithDelta(20.0, (float) $item->system_quantity, 0.0005);
        $this->assertEqualsWithDelta(18.0, (float) $item->actual_quantity, 0.0005);
        $this->assertEqualsWithDelta(-2.0, (float) $item->difference, 0.0005);
    }

    public function test_product_without_stock_has_zero_snapshot_and_no_stock_row_is_created(): void
    {
        $stocksBefore = Stock::count();

        $item = $this->draft([$this->line($this->productC, 5)])->items->first();

        $this->assertEqualsWithDelta(0.0, (float) $item->system_quantity, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $item->difference, 0.0005);
        $this->assertSame($stocksBefore, Stock::count());
    }

    public function test_duplicate_product_is_rejected(): void
    {
        try {
            $this->draft([$this->line($this->productA, 5), $this->line($this->productA, 6)]);
            $this->fail('Sản phẩm trùng phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.1.product_id', $e->errors());
        }

        $this->assertSame(0, Stocktake::count());
    }

    public function test_draft_validates_input_at_service_level(): void
    {
        foreach ([
            [],
            [['product_id' => $this->productA->id, 'actual_quantity' => -1]],
            [['product_id' => $this->productA->id, 'actual_quantity' => 'abc']],
            [['product_id' => $this->productA->id]],
            [['product_id' => 999999, 'actual_quantity' => 1]],
        ] as $items) {
            try {
                $this->draft($items);
                $this->fail('Phải bị từ chối.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, Stocktake::count());
    }

    public function test_inactive_warehouse_is_rejected(): void
    {
        try {
            $this->draft(null, ['warehouse_id' => $this->whInactive->id]);
            $this->fail('Kho ngừng hoạt động phải bị từ chối.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('warehouse_id', $e->errors());
        }

        $this->assertSame(0, Stocktake::count());
    }

    // ================================================================== B. Draft

    public function test_draft_can_be_edited_with_items_added_removed_and_actual_changed(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whA, $this->productB, 10);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);

        $updated = $this->stocktakeService()->updateDraft($stocktake->id, $this->payload([
            $this->line($this->productA, 19),   // đổi actual
            $this->line($this->productB, 12),   // thêm dòng
        ], ['note' => 'Đã sửa']));

        $this->assertSame($stocktake->stocktake_code, $updated->stocktake_code);
        $this->assertSame('Đã sửa', $updated->note);
        $this->assertCount(2, $updated->items);
        $this->assertEqualsWithDelta(19.0, (float) $updated->items[0]->actual_quantity, 0.0005);
        $this->assertEqualsWithDelta(-1.0, (float) $updated->items[0]->difference, 0.0005);
        $this->assertEqualsWithDelta(2.0, (float) $updated->items[1]->difference, 0.0005);

        // xoá bớt dòng
        $again = $this->stocktakeService()->updateDraft($stocktake->id, $this->payload([$this->line($this->productB, 10)]));
        $this->assertCount(1, $again->items);
        $this->assertSame(1, StocktakeItem::where('stocktake_id', $stocktake->id)->count());
    }

    public function test_draft_can_be_deleted_with_items(): void
    {
        $stocktake = $this->draft();

        $this->stocktakeService()->deleteDraft($stocktake->id);

        $this->assertDatabaseMissing('stocktakes', ['id' => $stocktake->id]);
        $this->assertSame(0, StocktakeItem::count());
    }

    public function test_draft_operations_never_create_movements(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $before = $this->snapshotCounts();

        $stocktake = $this->draft();
        $this->stocktakeService()->updateDraft($stocktake->id, $this->payload([$this->line($this->productA, 25)]));
        $this->stocktakeService()->deleteDraft($stocktake->id);

        $this->assertSame($before, $this->snapshotCounts());
    }

    // ================================================================== C. Difference

    public function test_difference_positive_negative_and_zero(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whA, $this->productB, 20);
        $this->receive($this->whA, $this->productC, 20);

        $items = $this->draft([
            $this->line($this->productA, 25),
            $this->line($this->productB, 18),
            $this->line($this->productC, 20),
        ])->items;

        $this->assertEqualsWithDelta(5.0, (float) $items[0]->difference, 0.0005);
        $this->assertEqualsWithDelta(-2.0, (float) $items[1]->difference, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $items[2]->difference, 0.0005);
    }

    // ================================================================== D. Post

    public function test_post_increase_creates_adjustment_lot_and_movement(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 25)]);

        $posted = $this->stocktakeService()->post($stocktake->id, $this->admin->id);

        $this->assertSame(Stocktake::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->posted_at);
        $this->assertEqualsWithDelta(25.0, $this->onHand($this->whA, $this->productA), 0.0005);

        $lots = $this->lots($this->whA, $this->productA);
        $this->assertCount(2, $lots);
        $this->assertEqualsWithDelta(20.0, (float) $lots[0]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $lots[1]->quantity_received, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $lots[1]->quantity_remaining, 0.0005);

        $movement = $this->adjustmentMovements()->firstOrFail();
        $this->assertSame(1, $this->adjustmentMovements()->count());
        $this->assertEqualsWithDelta(5.0, (float) $movement->quantity, 0.0005);
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(25.0, (float) $movement->quantity_after, 0.0005);
        $this->assertSame('stocktake', $movement->reference_type);
        $this->assertSame($stocktake->id, (int) $movement->reference_id);
        $this->assertSame($this->admin->id, $movement->user_id);

        $this->assertSame(0, StockAllocation::count()); // Adjustment không tạo allocation
        $this->assertStockMatchesLots($this->whA, $this->productA);
    }

    public function test_post_decrease_consumes_lots_fifo(): void
    {
        $this->receive($this->whA, $this->productA, 10, Carbon::parse('2026-09-01 08:00:00'));
        $this->receive($this->whA, $this->productA, 10, Carbon::parse('2026-09-20 08:00:00'));
        $stocktake = $this->draft([$this->line($this->productA, 18)]);

        $this->stocktakeService()->post($stocktake->id, $this->admin->id);

        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);

        $lots = $this->lots($this->whA, $this->productA);
        $this->assertCount(2, $lots);
        $this->assertEqualsWithDelta(8.0, (float) $lots[0]->quantity_remaining, 0.0005);  // lô cũ bị trừ trước
        $this->assertEqualsWithDelta(10.0, (float) $lots[1]->quantity_remaining, 0.0005);

        $movement = $this->adjustmentMovements()->firstOrFail();
        $this->assertEqualsWithDelta(-2.0, (float) $movement->quantity, 0.0005);
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(18.0, (float) $movement->quantity_after, 0.0005);
        $this->assertSame('stocktake', $movement->reference_type);

        $this->assertSame(0, StockAllocation::count());
        $this->assertStockMatchesLots($this->whA, $this->productA);
    }

    public function test_post_to_zero_keeps_zeroed_lots(): void
    {
        $this->receive($this->whA, $this->productA, 8);
        $this->receive($this->whA, $this->productA, 12);
        $stocktake = $this->draft([$this->line($this->productA, 0)]);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $lots = $this->lots($this->whA, $this->productA);
        $this->assertCount(2, $lots); // lot về 0 vẫn được giữ lại
        $this->assertEqualsWithDelta(0.0, (float) $lots[0]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $lots[1]->quantity_remaining, 0.0005);
        $this->assertStockMatchesLots($this->whA, $this->productA);
    }

    public function test_post_without_difference_creates_no_adjustment(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 20)]);
        $before = $this->snapshotCounts();

        $posted = $this->stocktakeService()->post($stocktake->id);

        $this->assertSame(Stocktake::STATUS_POSTED, $posted->status);
        $this->assertSame($before, $this->snapshotCounts());
        $this->assertSame(0, $this->adjustmentMovements()->count());
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->whA, $this->productA), 0.0005);
    }

    public function test_post_without_difference_on_product_without_stock_does_not_create_stock(): void
    {
        $stocktake = $this->draft([$this->line($this->productC, 0)]);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertSame(0, Stock::count());
        $this->assertSame(0, $this->adjustmentMovements()->count());
        $this->assertSame(Stocktake::STATUS_POSTED, $stocktake->fresh()->status);
    }

    public function test_post_on_product_without_stock_creates_stock_lot_and_movement(): void
    {
        $stocktake = $this->draft([$this->line($this->productC, 5)]);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertEqualsWithDelta(5.0, $this->onHand($this->whA, $this->productC), 0.0005);
        $movement = $this->adjustmentMovements()->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $movement->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $movement->quantity_after, 0.0005);
        $this->assertStockMatchesLots($this->whA, $this->productC);
    }

    public function test_post_multiple_items(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whA, $this->productB, 10);
        $this->receive($this->whA, $this->productC, 5);

        $stocktake = $this->draft([
            $this->line($this->productA, 18),
            $this->line($this->productB, 11),
            $this->line($this->productC, 5),
        ]);

        $this->stocktakeService()->post($stocktake->id, $this->admin->id);

        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(11.0, $this->onHand($this->whA, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(5.0, $this->onHand($this->whA, $this->productC), 0.0005);
        $this->assertSame(2, $this->adjustmentMovements()->count()); // C không chênh lệch -> không movement

        foreach ([$this->productA, $this->productB, $this->productC] as $product) {
            $this->assertStockMatchesLots($this->whA, $product);
        }
    }

    // ================================================================== E. Stale snapshot

    public function test_stale_snapshot_rejects_post_without_any_change(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]); // snapshot = 20

        $this->issue($this->whA, $this->productA, 3);                  // current = 17
        $before = $this->snapshotCounts();

        try {
            $this->stocktakeService()->post($stocktake->id);
            $this->fail('Snapshot stale phải bị từ chối.');
        } catch (ValidationException $e) {
            $message = $e->errors()['document'][0];
            $this->assertStringContainsString('HG54', $message);
            $this->assertStringContainsString('Tồn hệ thống lúc snapshot: 20', $message);
            $this->assertStringContainsString('Tồn hiện tại: 17', $message);
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertSame(0, $this->adjustmentMovements()->count());
        $this->assertEqualsWithDelta(17.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertStockMatchesLots($this->whA, $this->productA);

        $fresh = $stocktake->fresh();
        $this->assertSame(Stocktake::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->posted_at);
        // Snapshot KHÔNG bị tự cập nhật
        $this->assertEqualsWithDelta(20.0, (float) $fresh->items->first()->system_quantity, 0.0005);
    }

    public function test_stale_when_stock_increased_after_snapshot(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);

        $this->receive($this->whA, $this->productA, 5); // current = 25

        $this->expectException(ValidationException::class);

        $this->stocktakeService()->post($stocktake->id);
    }

    public function test_one_stale_item_blocks_adjustment_of_all_items(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whA, $this->productB, 10);
        $stocktake = $this->draft([
            $this->line($this->productA, 18),  // còn nguyên snapshot
            $this->line($this->productB, 8),   // sẽ stale
        ]);

        $this->issue($this->whA, $this->productB, 1);
        $before = $this->snapshotCounts();

        try {
            $this->stocktakeService()->post($stocktake->id);
            $this->fail('Phải bị từ chối vì dòng B stale.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('XP10', $e->errors()['document'][0]);
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->whA, $this->productA), 0.0005); // A không bị điều chỉnh
        $this->assertSame(0, $this->adjustmentMovements()->count());
        $this->assertSame(Stocktake::STATUS_DRAFT, $stocktake->fresh()->status);
    }

    public function test_user_can_refresh_snapshot_by_saving_draft_then_post_succeeds(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);
        $this->issue($this->whA, $this->productA, 3); // current = 17

        try {
            $this->stocktakeService()->post($stocktake->id);
            $this->fail('Phải stale.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Người dùng chủ động lưu lại phiếu => snapshot được chụp lại (system = 17, actual = 18)
        $refreshed = $this->stocktakeService()->updateDraft($stocktake->id, $this->payload([$this->line($this->productA, 18)]));
        $this->assertEqualsWithDelta(17.0, (float) $refreshed->items->first()->system_quantity, 0.0005);
        $this->assertEqualsWithDelta(1.0, (float) $refreshed->items->first()->difference, 0.0005);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(1.0, (float) $this->adjustmentMovements()->firstOrFail()->quantity, 0.0005);
    }

    public function test_detail_flags_stale_items_for_drafts(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);

        $fresh = $this->stocktakeService()->findDetail($stocktake->id);
        $this->assertFalse($fresh->has_stale_items);
        $this->assertFalse($fresh->items->first()->is_stale);

        $this->issue($this->whA, $this->productA, 3);

        $stale = $this->stocktakeService()->findDetail($stocktake->id);
        $this->assertTrue($stale->has_stale_items);
        $this->assertTrue($stale->items->first()->is_stale);
        $this->assertEqualsWithDelta(17.0, (float) $stale->items->first()->current_quantity, 0.0005);
        // chỉ đọc: snapshot không đổi
        $this->assertEqualsWithDelta(20.0, (float) $stocktake->fresh()->items->first()->system_quantity, 0.0005);
    }

    // ================================================================== F. Rollback

    public function test_failure_on_second_item_rolls_back_first_adjustment(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whA, $this->productB, 10);
        $stocktake = $this->draft([
            $this->line($this->productA, 18),
            $this->line($this->productB, 8),
        ]);
        $before = $this->snapshotCounts();

        // Dòng A (product_id nhỏ hơn, xử lý trước) ghi movement thật; dòng B ném lỗi hạ tầng.
        $this->partialMock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->once()->passthru();
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('simulated failure'));
        });
        $this->app->forgetInstance(InventoryService::class);
        $this->app->forgetInstance(StocktakeService::class);

        try {
            $this->stocktakeService()->post($stocktake->id);
            $this->fail('Phải ném exception ở dòng thứ 2.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshotCounts());
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productB), 0.0005);
        $this->assertEqualsWithDelta(20.0, $this->lotSum($this->whA, $this->productA), 0.0005);
        $this->assertSame(0, $this->adjustmentMovements()->count());

        $fresh = $stocktake->fresh();
        $this->assertSame(Stocktake::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->posted_at);
    }

    // ================================================================== G. Immutable

    public function test_cannot_post_twice(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);
        $first = $this->stocktakeService()->post($stocktake->id);
        $after = $this->snapshotCounts();

        try {
            $this->stocktakeService()->post($stocktake->id);
            $this->fail('POST lần 2 phải bị chặn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $this->assertSame($after, $this->snapshotCounts());
        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame(1, $this->adjustmentMovements()->count());
        $this->assertTrue($first->posted_at->equalTo($stocktake->fresh()->posted_at));
    }

    public function test_posted_stocktake_cannot_be_edited_or_deleted(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 18)]);
        $this->stocktakeService()->post($stocktake->id);

        try {
            $this->stocktakeService()->updateDraft($stocktake->id, $this->payload([$this->line($this->productA, 1)]));
            $this->fail('Không được sửa phiếu đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        try {
            $this->stocktakeService()->deleteDraft($stocktake->id);
            $this->fail('Không được xoá phiếu đã POST.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $fresh = $stocktake->fresh();
        $this->assertEqualsWithDelta(18.0, (float) $fresh->items->first()->actual_quantity, 0.0005);
        $this->assertSame(1, StocktakeItem::where('stocktake_id', $stocktake->id)->count());
    }

    // ================================================================== H. Warehouse isolation

    public function test_other_warehouse_is_not_used_or_affected(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whB, $this->productA, 50);

        $stocktake = $this->draft([$this->line($this->productA, 18)]); // kho A

        // Snapshot chỉ lấy tồn kho A (20), không phải 70
        $this->assertEqualsWithDelta(20.0, (float) $stocktake->items->first()->system_quantity, 0.0005);

        // Thay đổi tồn ở kho B KHÔNG làm phiếu kho A bị stale
        $this->issue($this->whB, $this->productA, 10);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(40.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertStockMatchesLots($this->whB, $this->productA);
    }

    // ================================================================== Lock

    /**
     * SQLite in-memory không mô phỏng được 2 request đồng thời thật; test này xác nhận post()
     * LUÔN lấy row lock trên phiếu (findForUpdate) — cơ chế chống double-post trên MySQL.
     */
    public function test_post_acquires_row_lock_on_stocktake(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft([$this->line($this->productA, 20)]);

        $this->partialMock(StocktakeRepository::class, function ($mock) {
            $mock->shouldReceive('findForUpdate')->once()->passthru();
        });
        $this->app->forgetInstance(StocktakeService::class);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertSame(Stocktake::STATUS_POSTED, $stocktake->fresh()->status);
    }

    public function test_stock_rows_are_locked_in_ascending_product_order(): void
    {
        $this->receive($this->whA, $this->productA, 5);
        $this->receive($this->whA, $this->productB, 5);

        // Nhập theo thứ tự B rồi A; không chênh lệch nên adjustStock không được gọi -> chỉ có 2 lần khóa.
        $stocktake = $this->draft([
            $this->line($this->productB, 5),
            $this->line($this->productA, 5),
        ]);

        $warehouseId = $this->whA->id;

        $this->partialMock(StockRepository::class, function ($mock) use ($warehouseId) {
            $mock->shouldReceive('getForUpdate')->with($warehouseId, $this->productA->id)->once()->ordered()->passthru();
            $mock->shouldReceive('getForUpdate')->with($warehouseId, $this->productB->id)->once()->ordered()->passthru();
        });
        $this->app->forgetInstance(InventoryService::class);
        $this->app->forgetInstance(StocktakeService::class);

        $this->stocktakeService()->post($stocktake->id);

        $this->assertSame(Stocktake::STATUS_POSTED, $stocktake->fresh()->status);
    }
}