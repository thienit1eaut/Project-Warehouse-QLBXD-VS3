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
use App\Repositories\StockAllocationRepository;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ⚠️ Cần `.env.testing` (DB_CONNECTION=sqlite, DB_DATABASE=:memory:) — xem
 * ReceiveStockTest.php. Không chạy file này nếu chưa có, sẽ RefreshDatabase
 * thẳng vào DB dev.
 */
class StockAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->product = Product::create([
            'sku' => 'SKU-ALLOC-001',
            'name' => 'Sản phẩm test Allocation',
            'slug' => 'sp-test-allocation',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'WH-ALLOC', 'name' => 'Kho test Allocation']);
    }

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    private function receive(float $quantity, ?string $receivedAt = null): Stock
    {
        return $this->service()->receiveStock(
            $this->warehouse->id,
            $this->product->id,
            $quantity,
            $receivedAt
        );
    }

    private function latestOutMovement(): StockMovement
    {
        return StockMovement::where('movement_type', 'out')->latest('id')->first();
    }

    /** Test 1 — Issue một Lot */
    public function test_issue_from_single_lot_creates_one_allocation(): void
    {
        $this->receive(10);
        $lot = StockLot::first();

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 3);

        $this->assertSame(1, StockAllocation::count());

        $allocation = StockAllocation::first();
        $this->assertSame('3.000', (string) $allocation->quantity);
        $this->assertSame($lot->id, $allocation->stock_lot_id);
        $this->assertSame($this->latestOutMovement()->id, $allocation->stock_movement_id);
    }

    /** Test 2 — FIFO qua nhiều Lot */
    public function test_issue_across_two_lots_creates_matching_allocations(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-05 08:00:00');

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 12);

        $lots = StockLot::orderBy('id')->get();
        $movement = $this->latestOutMovement();

        $allocations = StockAllocation::where('stock_movement_id', $movement->id)
            ->orderBy('stock_lot_id')
            ->get();

        $this->assertSame(2, $allocations->count());
        $this->assertSame($lots[0]->id, $allocations[0]->stock_lot_id);
        $this->assertSame('10.000', (string) $allocations[0]->quantity);
        $this->assertSame($lots[1]->id, $allocations[1]->stock_lot_id);
        $this->assertSame('2.000', (string) $allocations[1]->quantity);

        $sum = (float) $allocations->sum('quantity');
        $this->assertEquals(abs((float) $movement->quantity), $sum);
    }

    /** Test 3 — Ba Lot: Lot không bị đụng KHÔNG được tạo allocation quantity=0 */
    public function test_issue_does_not_create_zero_quantity_allocation_for_untouched_lot(): void
    {
        $this->receive(5, '2026-09-01 08:00:00');
        $this->receive(7, '2026-09-02 08:00:00');
        $this->receive(10, '2026-09-03 08:00:00');

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 6);

        $lots = StockLot::orderBy('id')->get();
        $movement = $this->latestOutMovement();

        $allocations = StockAllocation::where('stock_movement_id', $movement->id)
            ->orderBy('stock_lot_id')
            ->get();

        $this->assertSame(2, $allocations->count());
        $this->assertSame($lots[0]->id, $allocations[0]->stock_lot_id);
        $this->assertSame('5.000', (string) $allocations[0]->quantity);
        $this->assertSame($lots[1]->id, $allocations[1]->stock_lot_id);
        $this->assertSame('1.000', (string) $allocations[1]->quantity);

        $this->assertFalse(
            StockAllocation::where('stock_lot_id', $lots[2]->id)->exists()
        );
    }

    /** Test 4 — Issue toàn bộ tồn: allocation phản ánh đúng từng lot */
    public function test_issue_exact_total_stock_creates_allocation_per_lot(): void
    {
        $this->receive(10);
        $this->receive(5);

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 15);

        $lots = StockLot::orderBy('id')->get();
        $movement = $this->latestOutMovement();

        $allocations = StockAllocation::where('stock_movement_id', $movement->id)
            ->orderBy('stock_lot_id')
            ->get();

        $this->assertSame(2, $allocations->count());
        $this->assertSame('10.000', (string) $allocations[0]->quantity);
        $this->assertSame('5.000', (string) $allocations[1]->quantity);
    }

    /** Test 5 — Issue vượt tồn: không tạo OUT movement, không tạo allocation */
    public function test_issue_exceeding_stock_creates_no_movement_and_no_allocation(): void
    {
        $this->receive(10);
        $this->receive(5);

        try {
            $this->service()->issueStock($this->warehouse->id, $this->product->id, 16);
            $this->fail('Kỳ vọng ValidationException khi issue vượt tồn.');
        } catch (ValidationException $e) {
            // expected
        }

        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 6 — Receive KHÔNG tạo Allocation */
    public function test_receive_does_not_create_allocation(): void
    {
        $this->receive(10);

        $this->assertSame(1, StockMovement::where('movement_type', 'in')->count());
        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 7 — Allocation invariant: SUM(allocation.quantity) = ABS(OUT.quantity) */
    public function test_allocation_sum_matches_out_movement_quantity(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-05 08:00:00');
        $this->receive(20, '2026-09-10 08:00:00');

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 23);

        $movement = $this->latestOutMovement();
        $sum = (float) StockAllocation::where('stock_movement_id', $movement->id)->sum('quantity');

        $this->assertEquals(abs((float) $movement->quantity), $sum);
    }

    /** Test 8 — Relationship */
    public function test_relationships_resolve_correctly(): void
    {
        $this->receive(10);
        $this->service()->issueStock($this->warehouse->id, $this->product->id, 4);

        $movement = $this->latestOutMovement();
        $lot = StockLot::first();

        $this->assertCount(1, $movement->allocations);

        $allocation = $movement->allocations->first();
        $this->assertTrue($allocation->stockMovement->is($movement));
        $this->assertTrue($allocation->stockLot->is($lot));

        $this->assertCount(1, $lot->allocations);
        $this->assertTrue($lot->allocations->first()->is($allocation));
    }

    /** Test 9 — Transaction rollback khi tạo Allocation thất bại */
    public function test_issue_rolls_back_everything_if_allocation_creation_fails(): void
    {
        $this->receive(10);

        $this->mock(StockAllocationRepository::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('Giả lập lỗi ghi StockAllocation'));
        });

        try {
            $this->service()->issueStock($this->warehouse->id, $this->product->id, 4);
            $this->fail('Kỳ vọng exception được ném ra từ transaction.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $stock = Stock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertSame('10.000', (string) $stock->quantity_on_hand);
        $this->assertSame('10.000', (string) StockLot::first()->quantity_remaining);
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
        $this->assertSame(0, StockAllocation::count());
    }
}