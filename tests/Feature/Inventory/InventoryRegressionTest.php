<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAllocation;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Repositories\StockMovementRepository;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PHASE G — Regression suite tổng hợp cho Inventory (Receive → Issue/FIFO →
 * StockAllocation → Adjustment) chạy liên tiếp trên cùng Product + Warehouse.
 *
 * Nguyên tắc:
 * - CHỈ test, không sửa business logic. Nếu test fail = bug thật hoặc kỳ vọng
 *   sai so với rule hiện tại -> báo cáo, KHÔNG sửa production code cho pass.
 * - Thời gian được đóng băng (travelTo) và tăng 1 phút sau mỗi thao tác để
 *   received_at của mọi StockLot (kể cả lot do Adjustment tăng, vốn dùng now())
 *   khác nhau và có thứ tự đúng với thứ tự thao tác -> FIFO deterministic.
 * - Adjustment nhận actualQuantity (TỔNG tồn thực tế), KHÔNG phải delta.
 *
 * ⚠️ Cần `.env.testing` (DB_CONNECTION=sqlite, DB_DATABASE=:memory:) — xem
 * ReceiveStockTest.php. RefreshDatabase sẽ DROP + MIGRATE DB đang cấu hình.
 *
 * Concurrency: behavior khi 2 request đồng thời phụ thuộc row locking thật của
 * DB (lockForUpdate) và KHÔNG được chứng minh đầy đủ bởi suite này (SQLite
 * in-memory, 1 connection).
 */
class InventoryRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Unit $unit;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-01-01 08:00:00'));

        $this->category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->warehouse = $this->makeWarehouse('WH-REG-A');
        $this->product = $this->makeProduct('SKU-REG-A');
    }

    // ------------------------------------------------------------------
    // Helpers (chỉ thuộc test suite)
    // ------------------------------------------------------------------

    private function makeWarehouse(string $code): Warehouse
    {
        return Warehouse::create(['code' => $code, 'name' => 'Kho ' . $code]);
    }

    private function makeProduct(string $sku): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => 'Sản phẩm ' . $sku,
            'slug' => strtolower($sku),
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);
    }

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    /** Tăng đồng hồ 1 phút để mỗi thao tác có received_at/created_at riêng, đúng thứ tự. */
    private function advance(): void
    {
        $this->travelTo(now()->addMinute());
    }

    private function receive(float $qty, ?Warehouse $w = null, ?Product $p = null): Stock
    {
        $this->advance();

        return $this->service()->receiveStock(($w ?? $this->warehouse)->id, ($p ?? $this->product)->id, $qty);
    }

    private function issue(float $qty, ?Warehouse $w = null, ?Product $p = null): Stock
    {
        $this->advance();

        return $this->service()->issueStock(($w ?? $this->warehouse)->id, ($p ?? $this->product)->id, $qty);
    }

    /** $actual là TỔNG tồn thực tế mong muốn (không phải delta). */
    private function adjust(float $actual, ?Warehouse $w = null, ?Product $p = null): Stock
    {
        $this->advance();

        return $this->service()->adjustStock(($w ?? $this->warehouse)->id, ($p ?? $this->product)->id, $actual);
    }

    private function stockOf(?Warehouse $w = null, ?Product $p = null): ?Stock
    {
        return Stock::where('warehouse_id', ($w ?? $this->warehouse)->id)
            ->where('product_id', ($p ?? $this->product)->id)
            ->first();
    }

    private function onHand(?Warehouse $w = null, ?Product $p = null): float
    {
        $stock = $this->stockOf($w, $p);

        return $stock ? (float) $stock->quantity_on_hand : 0.0;
    }

    /** Các lot của (warehouse, product) theo đúng thứ tự FIFO. */
    private function lots(?Warehouse $w = null, ?Product $p = null): Collection
    {
        return StockLot::where('warehouse_id', ($w ?? $this->warehouse)->id)
            ->where('product_id', ($p ?? $this->product)->id)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /** @return float[] quantity_remaining của từng lot theo thứ tự FIFO */
    private function remainings(?Warehouse $w = null, ?Product $p = null): array
    {
        return $this->lots($w, $p)->map(fn ($l) => (float) $l->quantity_remaining)->values()->all();
    }

    private function movementsOf(?Warehouse $w = null, ?Product $p = null): Collection
    {
        return StockMovement::where('warehouse_id', ($w ?? $this->warehouse)->id)
            ->where('product_id', ($p ?? $this->product)->id)
            ->orderBy('id')
            ->get();
    }

    private function latestMovement(string $type): StockMovement
    {
        return StockMovement::where('movement_type', $type)->latest('id')->firstOrFail();
    }

    private function allocationsOf(StockMovement $movement): Collection
    {
        return StockAllocation::where('stock_movement_id', $movement->id)->orderBy('stock_lot_id')->get();
    }

    private function snapshot(?Warehouse $w = null, ?Product $p = null): array
    {
        return [
            'stock' => $this->onHand($w, $p),
            'lots' => $this->lots($w, $p)
                ->mapWithKeys(fn ($l) => [$l->id => (float) $l->quantity_remaining])
                ->all(),
            'movements' => StockMovement::count(),
            'allocations' => StockAllocation::count(),
        ];
    }

    /**
     * Invariant cốt lõi cho 1 (warehouse, product):
     *   Stock.quantity_on_hand = SUM(StockLot.quantity_remaining)
     *   Stock >= 0; 0 <= lot.remaining <= lot.received; lot.received > 0
     */
    private function assertStockLotInvariant(?Warehouse $w = null, ?Product $p = null): void
    {
        $onHand = $this->onHand($w, $p);
        $lots = $this->lots($w, $p);
        $sum = (float) $lots->sum(fn ($l) => (float) $l->quantity_remaining);

        $this->assertEqualsWithDelta($onHand, $sum, 0.0005, 'Stock.quantity_on_hand phải = SUM(StockLot.quantity_remaining)');
        $this->assertGreaterThanOrEqual(0.0, $onHand, 'Stock không được âm');

        foreach ($lots as $lot) {
            $received = (float) $lot->quantity_received;
            $remaining = (float) $lot->quantity_remaining;

            $this->assertGreaterThan(0.0, $received, "Lot #{$lot->id}: quantity_received phải > 0");
            $this->assertGreaterThanOrEqual(0.0, $remaining, "Lot #{$lot->id}: quantity_remaining không được âm");
            $this->assertLessThanOrEqual($received + 0.0005, $remaining, "Lot #{$lot->id}: remaining không được vượt received");
        }
    }

    /** Với 1 OUT movement: SUM(allocation.quantity) = ABS(movement.quantity), mọi allocation > 0. */
    private function assertOutAllocationInvariant(StockMovement $movement): void
    {
        $this->assertSame('out', $movement->movement_type);

        $sum = (float) StockAllocation::where('stock_movement_id', $movement->id)->sum('quantity');
        $this->assertEqualsWithDelta(abs((float) $movement->quantity), $sum, 0.0005);

        $this->assertSame(
            0,
            StockAllocation::where('stock_movement_id', $movement->id)->where('quantity', '<=', 0)->count(),
            'Không được có allocation quantity <= 0'
        );
    }

    /** Allocation chỉ tồn tại cho OUT; OUT nào cũng khớp tổng; không có allocation "mồ côi". */
    private function assertAllocationRulesForAllMovements(): void
    {
        foreach (StockMovement::orderBy('id')->get() as $movement) {
            if ($movement->movement_type === 'out') {
                $this->assertOutAllocationInvariant($movement);
            } else {
                $this->assertSame(
                    0,
                    StockAllocation::where('stock_movement_id', $movement->id)->count(),
                    "Movement #{$movement->id} ({$movement->movement_type}) không được có allocation"
                );
            }
        }

        $this->assertSame(
            0,
            StockAllocation::whereHas('stockMovement', fn ($q) => $q->where('movement_type', '!=', 'out'))->count()
        );
    }

    /** Lịch sử movement của (warehouse, product): dấu, after = before + quantity, liên tục theo thứ tự. */
    private function assertMovementHistoryConsistent(?Warehouse $w = null, ?Product $p = null): void
    {
        $previousAfter = 0.0;

        foreach ($this->movementsOf($w, $p) as $movement) {
            $before = (float) $movement->quantity_before;
            $qty = (float) $movement->quantity;
            $after = (float) $movement->quantity_after;

            $this->assertEqualsWithDelta($before + $qty, $after, 0.0005, "Movement #{$movement->id}: after phải = before + quantity");
            $this->assertEqualsWithDelta($previousAfter, $before, 0.0005, "Movement #{$movement->id}: before phải = after của movement liền trước");

            match ($movement->movement_type) {
                'in' => $this->assertGreaterThan(0.0, $qty, 'IN phải dương'),
                'out' => $this->assertLessThan(0.0, $qty, 'OUT phải âm'),
                'adjustment' => $this->assertNotEquals(0.0, $qty, 'ADJUSTMENT không được có quantity = 0'),
                default => $this->fail("movement_type không hợp lệ: {$movement->movement_type}"),
            };

            $previousAfter = $after;
        }

        $this->assertEqualsWithDelta($this->onHand($w, $p), $previousAfter, 0.0005, 'quantity_after của movement cuối phải = Stock hiện tại');
    }

    // ------------------------------------------------------------------
    // Test Case 1 — Full lifecycle cơ bản
    // ------------------------------------------------------------------

    public function test_full_lifecycle_receive_issue_adjust_up_issue(): void
    {
        $this->receive(10);
        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0005);
        $this->assertEquals([10.0], $this->remainings());
        $this->assertStockLotInvariant();

        $this->receive(5);
        $this->assertEqualsWithDelta(15.0, $this->onHand(), 0.0005);
        $this->assertEquals([10.0, 5.0], $this->remainings());
        $this->assertStockLotInvariant();

        $this->issue(3);
        $this->assertEqualsWithDelta(12.0, $this->onHand(), 0.0005);
        $this->assertEquals([7.0, 5.0], $this->remainings());
        $out1 = $this->latestMovement('out');
        $this->assertEqualsWithDelta(-3.0, (float) $out1->quantity, 0.0005);
        $allocations = $this->allocationsOf($out1);
        $this->assertCount(1, $allocations);
        $this->assertSame($this->lots()[0]->id, (int) $allocations[0]->stock_lot_id);
        $this->assertEqualsWithDelta(3.0, (float) $allocations[0]->quantity, 0.0005);
        $this->assertStockLotInvariant();

        // Adjustment +2: actualQuantity = 14 (tổng), difference = +2 -> lot mới
        $this->adjust(14);
        $this->assertEqualsWithDelta(14.0, $this->onHand(), 0.0005);
        $this->assertEquals([7.0, 5.0, 2.0], $this->remainings());
        $adjustLot = $this->lots()[2];
        $this->assertEqualsWithDelta(2.0, (float) $adjustLot->quantity_received, 0.0005);
        $this->assertStockLotInvariant();

        $this->issue(4);
        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0005);
        $this->assertEquals([3.0, 5.0, 2.0], $this->remainings());
        $out2 = $this->latestMovement('out');
        $allocations = $this->allocationsOf($out2);
        $this->assertCount(1, $allocations);
        $this->assertSame($this->lots()[0]->id, (int) $allocations[0]->stock_lot_id);
        $this->assertEqualsWithDelta(4.0, (float) $allocations[0]->quantity, 0.0005);

        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements();
        $this->assertMovementHistoryConsistent();
    }

    // ------------------------------------------------------------------
    // Test Case 2 — Issue xuyên nhiều lot
    // ------------------------------------------------------------------

    public function test_issue_spanning_multiple_lots_allocates_exactly_what_fifo_consumed(): void
    {
        $this->receive(5);
        $this->receive(7);
        $this->receive(3);

        $this->issue(10);

        $this->assertEquals([0.0, 2.0, 3.0], $this->remainings());
        $this->assertEqualsWithDelta(5.0, $this->onHand(), 0.0005);

        $out = $this->latestMovement('out');
        $lots = $this->lots();
        $allocations = $this->allocationsOf($out);

        $this->assertCount(2, $allocations); // lot #3 không bị đụng -> không có allocation
        $byLot = $allocations->mapWithKeys(fn ($a) => [(int) $a->stock_lot_id => (float) $a->quantity])->all();
        $this->assertEqualsWithDelta(5.0, $byLot[$lots[0]->id], 0.0005);
        $this->assertEqualsWithDelta(5.0, $byLot[$lots[1]->id], 0.0005);
        $this->assertArrayNotHasKey($lots[2]->id, $byLot);

        $this->assertOutAllocationInvariant($out);
        $this->assertEqualsWithDelta(10.0, (float) $allocations->sum('quantity'), 0.0005);
        $this->assertStockLotInvariant();
    }

    // ------------------------------------------------------------------
    // Test Case 3 — Receive -> Issue -> Adjustment giảm
    // ------------------------------------------------------------------

    public function test_receive_issue_then_adjustment_decrease_consumes_fifo_without_allocation(): void
    {
        $this->receive(10);
        $this->receive(5);
        $this->issue(3); // Stock 12; lots [7, 5]
        $this->assertEquals([7.0, 5.0], $this->remainings());

        $allocationsBefore = StockAllocation::count();

        $this->adjust(7); // actual 7, difference = -5 -> FIFO: lot#1 7 -> 2

        $this->assertEqualsWithDelta(7.0, $this->onHand(), 0.0005);
        $this->assertEquals([2.0, 5.0], $this->remainings());
        $this->assertSame(2, $this->lots()->count()); // không tạo/xoá lot

        $adjustment = $this->latestMovement('adjustment');
        $this->assertEqualsWithDelta(-5.0, (float) $adjustment->quantity, 0.0005);
        $this->assertEqualsWithDelta(12.0, (float) $adjustment->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(7.0, (float) $adjustment->quantity_after, 0.0005);
        $this->assertSame(1, StockMovement::where('movement_type', 'adjustment')->count());

        $this->assertSame($allocationsBefore, StockAllocation::count());
        $this->assertSame(0, $adjustment->allocations()->count());

        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements();
    }

    // ------------------------------------------------------------------
    // Test Case 4 — Receive -> Adjustment tăng -> Issue
    // ------------------------------------------------------------------

    public function test_adjustment_increase_lot_is_consumed_after_older_receive_lot(): void
    {
        $this->receive(5);
        $this->adjust(8); // +3 -> lot adjustment

        $lots = $this->lots();
        $this->assertCount(2, $lots);
        $this->assertEqualsWithDelta(3.0, (float) $lots[1]->quantity_received, 0.0005);
        $this->assertEqualsWithDelta(3.0, (float) $lots[1]->quantity_remaining, 0.0005);
        $this->assertEqualsWithDelta(8.0, $this->onHand(), 0.0005);

        $this->issue(6);

        $this->assertEquals([0.0, 2.0], $this->remainings());
        $this->assertEqualsWithDelta(2.0, $this->onHand(), 0.0005);

        $out = $this->latestMovement('out');
        $byLot = $this->allocationsOf($out)->mapWithKeys(fn ($a) => [(int) $a->stock_lot_id => (float) $a->quantity])->all();
        $this->assertEqualsWithDelta(5.0, $byLot[$lots[0]->id], 0.0005);
        $this->assertEqualsWithDelta(1.0, $byLot[$lots[1]->id], 0.0005);

        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements(); // adjustment không có allocation
    }

    // ------------------------------------------------------------------
    // Test Case 5 — Workflow dài, invariant sau MỖI operation
    // ------------------------------------------------------------------

    public function test_long_workflow_keeps_invariants_after_every_operation(): void
    {
        // [op, argument, tồn kho kỳ vọng sau op]. adjust nhận TỔNG tồn thực tế.
        $steps = [
            ['receive', 10, 10.0],
            ['receive', 5, 15.0],
            ['issue', 4, 11.0],
            ['receive', 7, 18.0],
            ['adjust', 21, 21.0], // +3
            ['issue', 8, 13.0],
            ['adjust', 11, 11.0], // -2
            ['issue', 3, 8.0],
            ['receive', 6, 14.0],
        ];

        foreach ($steps as [$op, $argument, $expectedStock]) {
            match ($op) {
                'receive' => $this->receive($argument),
                'issue' => $this->issue($argument),
                'adjust' => $this->adjust($argument),
            };

            $this->assertEqualsWithDelta($expectedStock, $this->onHand(), 0.0005, "Sau {$op} {$argument}");
            $this->assertStockLotInvariant();

            if ($op === 'issue') {
                $this->assertOutAllocationInvariant($this->latestMovement('out'));
            }
        }

        // Lot 1: 10-4-6=0 | Lot 2: 5-2-2-1=0 | Lot 3: 7-2=5 | Lot 4 (adjustment +3): 3 | Lot 5: 6
        $this->assertEquals([0.0, 0.0, 5.0, 3.0, 6.0], $this->remainings());
        $this->assertSame(5, $this->lots()->count()); // không lot nào bị xoá

        $this->assertAllocationRulesForAllMovements();
        $this->assertMovementHistoryConsistent();
    }

    // ------------------------------------------------------------------
    // Test Case 6 — Lịch sử movement nhất quán
    // ------------------------------------------------------------------

    public function test_movement_history_is_consistent_and_ordered(): void
    {
        $this->receive(10);   // in  +10 : 0  -> 10
        $this->receive(5);    // in  +5  : 10 -> 15
        $this->issue(4);      // out -4  : 15 -> 11
        $this->adjust(14);    // adj +3  : 11 -> 14
        $this->issue(6);      // out -6  : 14 -> 8
        $this->adjust(5);     // adj -3  : 8  -> 5

        $movements = $this->movementsOf();

        $this->assertSame(
            ['in', 'in', 'out', 'adjustment', 'out', 'adjustment'],
            $movements->pluck('movement_type')->all()
        );

        $expected = [
            [10.0, 0.0, 10.0],
            [5.0, 10.0, 15.0],
            [-4.0, 15.0, 11.0],
            [3.0, 11.0, 14.0],
            [-6.0, 14.0, 8.0],
            [-3.0, 8.0, 5.0],
        ];

        foreach ($movements as $i => $movement) {
            [$qty, $before, $after] = $expected[$i];
            $this->assertEqualsWithDelta($qty, (float) $movement->quantity, 0.0005, "Movement #{$i} quantity");
            $this->assertEqualsWithDelta($before, (float) $movement->quantity_before, 0.0005, "Movement #{$i} before");
            $this->assertEqualsWithDelta($after, (float) $movement->quantity_after, 0.0005, "Movement #{$i} after");
        }

        $this->assertMovementHistoryConsistent();
        $this->assertStockLotInvariant();
    }

    // ------------------------------------------------------------------
    // Test Case 7 — Allocation chỉ tồn tại cho OUT
    // ------------------------------------------------------------------

    public function test_allocation_exists_only_for_out_movements(): void
    {
        $this->receive(10);   // IN
        $this->issue(3);      // OUT
        $this->adjust(9);     // ADJUSTMENT (+2, từ 7 lên 9)
        $this->receive(4);    // IN
        $this->issue(5);      // OUT
        $this->adjust(6);     // ADJUSTMENT (-2, từ 8 xuống 6)

        $this->assertSame(
            ['in', 'out', 'adjustment', 'in', 'out', 'adjustment'],
            $this->movementsOf()->pluck('movement_type')->all()
        );

        $this->assertAllocationRulesForAllMovements();

        // 2 OUT, mỗi OUT cần allocation; tổng số allocation không được rỗng.
        $this->assertGreaterThan(0, StockAllocation::count());
        $this->assertStockLotInvariant();
    }

    // ------------------------------------------------------------------
    // Test Case 8 — Lot đã về 0 vẫn được giữ; FIFO bỏ qua lot zero
    // ------------------------------------------------------------------

    public function test_zeroed_lot_is_kept_and_skipped_by_later_issue(): void
    {
        $this->receive(5);
        $this->issue(5);

        $firstLot = $this->lots()[0];
        $this->assertEqualsWithDelta(5.0, (float) $firstLot->quantity_received, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $firstLot->quantity_remaining, 0.0005);
        $this->assertSame(1, $this->lots()->count()); // không bị delete

        $this->receive(3);
        $this->issue(2);

        $lots = $this->lots();
        $this->assertCount(2, $lots);
        $this->assertEquals([0.0, 1.0], $this->remainings());

        // OUT thứ 2 chỉ được allocation vào lot mới
        $secondOut = $this->latestMovement('out');
        $allocations = $this->allocationsOf($secondOut);
        $this->assertCount(1, $allocations);
        $this->assertSame($lots[1]->id, (int) $allocations[0]->stock_lot_id);
        $this->assertEqualsWithDelta(2.0, (float) $allocations[0]->quantity, 0.0005);

        // Lot cũ chỉ có đúng 1 allocation (từ OUT đầu tiên)
        $this->assertSame(1, StockAllocation::where('stock_lot_id', $lots[0]->id)->count());

        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements();
    }

    // ------------------------------------------------------------------
    // Test Case 9 — Isolation giữa Product
    // ------------------------------------------------------------------

    public function test_issue_does_not_touch_other_product_in_same_warehouse(): void
    {
        $productA = $this->product;
        $productB = $this->makeProduct('SKU-REG-B');

        $this->receive(10, null, $productA);
        $this->receive(20, null, $productB);

        $productBLotsBefore = $this->snapshot(null, $productB);

        $this->issue(4, null, $productA);

        $this->assertEqualsWithDelta(6.0, $this->onHand(null, $productA), 0.0005);
        $this->assertEqualsWithDelta(20.0, $this->onHand(null, $productB), 0.0005);

        // Product B: lot & tồn không đổi, movement chỉ có đúng 1 (IN), không allocation nào trỏ tới lot của B
        $this->assertEquals($productBLotsBefore['lots'], $this->snapshot(null, $productB)['lots']);
        $this->assertCount(1, $this->movementsOf(null, $productB));
        $this->assertSame(
            0,
            StockAllocation::whereIn('stock_lot_id', $this->lots(null, $productB)->pluck('id'))->count()
        );

        $this->assertStockLotInvariant(null, $productA);
        $this->assertStockLotInvariant(null, $productB);
        $this->assertMovementHistoryConsistent(null, $productA);
        $this->assertMovementHistoryConsistent(null, $productB);
    }

    // ------------------------------------------------------------------
    // Test Case 10 — Isolation giữa Warehouse
    // ------------------------------------------------------------------

    public function test_issue_does_not_touch_other_warehouse_for_same_product(): void
    {
        $warehouseA = $this->warehouse;
        $warehouseB = $this->makeWarehouse('WH-REG-B');

        $this->receive(10, $warehouseA);
        $this->receive(20, $warehouseB);

        $warehouseBBefore = $this->snapshot($warehouseB);

        $this->issue(6, $warehouseA);

        $this->assertEqualsWithDelta(4.0, $this->onHand($warehouseA), 0.0005);
        $this->assertEqualsWithDelta(20.0, $this->onHand($warehouseB), 0.0005);
        $this->assertEquals($warehouseBBefore['lots'], $this->snapshot($warehouseB)['lots']);

        // Allocation của OUT phải trỏ vào lot thuộc đúng warehouse A
        $out = $this->latestMovement('out');
        $this->assertSame($warehouseA->id, (int) $out->warehouse_id);
        foreach ($this->allocationsOf($out) as $allocation) {
            $this->assertSame($warehouseA->id, (int) $allocation->stockLot->warehouse_id);
        }
        $this->assertSame(
            0,
            StockAllocation::whereIn('stock_lot_id', $this->lots($warehouseB)->pluck('id'))->count()
        );

        $this->assertStockLotInvariant($warehouseA);
        $this->assertStockLotInvariant($warehouseB);
    }

    // ------------------------------------------------------------------
    // Test Case 11 — Issue vượt tồn sau nhiều operation: không partial state
    // ------------------------------------------------------------------

    public function test_issue_exceeding_stock_after_many_operations_leaves_no_partial_state(): void
    {
        $this->receive(10);
        $this->issue(4);
        $this->adjust(4); // 6 -> 4 (giảm 2)

        $this->assertEqualsWithDelta(4.0, $this->onHand(), 0.0005);
        $this->assertStockLotInvariant();

        $before = $this->snapshot();
        $historyBefore = $this->movementsOf()->map(fn ($m) => $m->only(['id', 'movement_type', 'quantity', 'quantity_before', 'quantity_after']))->all();

        try {
            $this->issue(5);
            $this->fail('Kỳ vọng ValidationException khi issue vượt tồn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertEquals(
            $historyBefore,
            $this->movementsOf()->map(fn ($m) => $m->only(['id', 'movement_type', 'quantity', 'quantity_before', 'quantity_after']))->all()
        );
        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements();
    }

    // ------------------------------------------------------------------
    // Test Case 12 — Adjustment không hợp lệ / hợp lệ (actualQuantity, không phải delta)
    // ------------------------------------------------------------------

    public function test_adjustment_with_negative_actual_is_rejected_then_valid_actual_works(): void
    {
        $this->receive(10);
        $this->issue(4); // Stock 6
        $this->assertEqualsWithDelta(6.0, $this->onHand(), 0.0005);

        $before = $this->snapshot();

        try {
            $this->adjust(-1);
            $this->fail('Kỳ vọng ValidationException với actualQuantity âm.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertEquals($before, $this->snapshot()); // rollback hoàn toàn

        // actualQuantity hợp lệ = 3 (tổng), tức delta = -3
        $this->adjust(3);

        $this->assertEqualsWithDelta(3.0, $this->onHand(), 0.0005);
        $this->assertEquals([3.0], $this->remainings());

        $adjustment = $this->latestMovement('adjustment');
        $this->assertEqualsWithDelta(-3.0, (float) $adjustment->quantity, 0.0005);
        $this->assertEqualsWithDelta(6.0, (float) $adjustment->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(3.0, (float) $adjustment->quantity_after, 0.0005);

        $this->assertStockLotInvariant();
        $this->assertAllocationRulesForAllMovements();
    }

    // ------------------------------------------------------------------
    // Test Case 13 — Adjustment về 0
    // ------------------------------------------------------------------

    public function test_adjustment_to_zero_zeroes_all_lots_but_keeps_them(): void
    {
        $this->receive(5);
        $this->receive(7);
        $this->receive(3);
        $this->assertEqualsWithDelta(15.0, $this->onHand(), 0.0005);

        $this->adjust(0);

        $this->assertEqualsWithDelta(0.0, $this->onHand(), 0.0005);
        $this->assertEquals([0.0, 0.0, 0.0], $this->remainings());
        $this->assertSame(3, $this->lots()->count()); // giữ nguyên lịch sử lot

        $adjustment = $this->latestMovement('adjustment');
        $this->assertEqualsWithDelta(-15.0, (float) $adjustment->quantity, 0.0005);
        $this->assertEqualsWithDelta(15.0, (float) $adjustment->quantity_before, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $adjustment->quantity_after, 0.0005);

        $this->assertSame(0, StockAllocation::count());
        $this->assertStockLotInvariant();
        $this->assertMovementHistoryConsistent();
    }

    // ------------------------------------------------------------------
    // Test Case 14 — Adjustment bằng đúng tồn hiện tại (no-op theo Phase F)
    // ------------------------------------------------------------------

    public function test_adjustment_equal_to_current_stock_is_a_noop(): void
    {
        $this->receive(10);
        $this->issue(3); // Stock 7

        $before = $this->snapshot();

        $stock = $this->adjust(7);

        $this->assertEqualsWithDelta(7.0, (float) $stock->quantity_on_hand, 0.0005);
        // Behavior hiện tại (Phase F): difference = 0 -> KHÔNG tạo movement/lot/allocation.
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, StockMovement::where('movement_type', 'adjustment')->count());
        $this->assertStockLotInvariant();
    }

    // ------------------------------------------------------------------
    // Transaction rollback xuyên phase: Adjustment fail giữa chừng
    // (Issue + Allocation rollback đã có ở IssueStockTest / StockAllocationTest)
    // ------------------------------------------------------------------

    public function test_adjustment_decrease_rolls_back_lots_and_stock_if_movement_fails(): void
    {
        $this->receive(10);
        $this->receive(5);

        $before = $this->snapshot();

        $this->mock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('Giả lập lỗi ghi StockMovement'));
        });

        try {
            $this->adjust(4); // giảm 11 -> lot đã bị consume trong transaction trước khi movement fail
            $this->fail('Kỳ vọng exception được ném ra từ transaction.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertStockLotInvariant();
    }

    public function test_adjustment_increase_rolls_back_new_lot_and_stock_if_movement_fails(): void
    {
        $this->receive(10);

        $before = $this->snapshot();

        $this->mock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('Giả lập lỗi ghi StockMovement'));
        });

        try {
            $this->adjust(15); // tăng 5 -> lot mới đã được tạo trong transaction trước khi movement fail
            $this->fail('Kỳ vọng exception được ném ra từ transaction.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(1, $this->lots()->count()); // lot Adjustment đã rollback
        $this->assertStockLotInvariant();
    }
}