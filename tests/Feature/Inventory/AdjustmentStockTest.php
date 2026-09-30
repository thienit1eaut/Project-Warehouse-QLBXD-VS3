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
use App\Repositories\StockRepository;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ⚠️ Cần `.env.testing` (DB_CONNECTION=sqlite, DB_DATABASE=:memory:) — xem
 * ReceiveStockTest.php. Không chạy file này nếu chưa có, sẽ RefreshDatabase
 * thẳng vào DB dev.
 */
class AdjustmentStockTest extends TestCase
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
            'sku' => 'SKU-ADJUST-001',
            'name' => 'Sản phẩm test Adjustment',
            'slug' => 'sp-test-adjustment',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'WH-ADJUST', 'name' => 'Kho test Adjustment']);
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

    private function currentStock(): Stock
    {
        return Stock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first();
    }

    private function sumLotRemaining(): float
    {
        return (float) StockLot::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->sum('quantity_remaining');
    }

    /** Test 1 — Adjustment tăng: tạo StockLot mới */
    public function test_adjustment_increase_creates_new_stock_lot(): void
    {
        $this->receive(10);

        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 15);

        $this->assertSame('15.000', (string) $stock->quantity_on_hand);
        $this->assertSame(2, StockLot::count()); // lot Receive + lot Adjustment mới

        $newLot = StockLot::orderBy('id')->get()->last();
        $this->assertSame('5.000', (string) $newLot->quantity_received);
        $this->assertSame('5.000', (string) $newLot->quantity_remaining);

        $movement = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertSame('5.000', (string) $movement->quantity);
        $this->assertSame('10.000', (string) $movement->quantity_before);
        $this->assertSame('15.000', (string) $movement->quantity_after);

        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 2 — Adjustment giảm trong 1 lot */
    public function test_adjustment_decrease_within_single_lot(): void
    {
        $this->receive(10);

        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 6);

        $this->assertSame('6.000', (string) $stock->quantity_on_hand);
        $this->assertSame('6.000', (string) StockLot::first()->quantity_remaining);
        $this->assertSame(1, StockLot::count()); // không tạo lot mới

        $movement = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertSame('-4.000', (string) $movement->quantity);
        $this->assertSame('10.000', (string) $movement->quantity_before);
        $this->assertSame('6.000', (string) $movement->quantity_after);

        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 3 — Adjustment giảm qua nhiều Lot (FIFO) */
    public function test_adjustment_decrease_consumes_multiple_lots_in_fifo_order(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-02 08:00:00');
        $this->receive(8, '2026-09-03 08:00:00');

        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 10);

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('0.000', (string) $lots[0]->quantity_remaining);
        $this->assertSame('2.000', (string) $lots[1]->quantity_remaining);
        $this->assertSame('8.000', (string) $lots[2]->quantity_remaining);

        $this->assertSame('10.000', (string) $stock->quantity_on_hand);
        $this->assertEquals(10.0, $this->sumLotRemaining());

        $movement = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertSame('-13.000', (string) $movement->quantity);
        $this->assertSame('23.000', (string) $movement->quantity_before);
        $this->assertSame('10.000', (string) $movement->quantity_after);

        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 4 — Adjustment giảm một phần của 1 lot (chỉ đủ tiêu vào lot cũ nhất, không lan sang lot khác) */
    public function test_adjustment_decrease_partial_lot_does_not_create_new_lot(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-02 08:00:00');
        // Stock = 15, actualQuantity = 12 -> chỉ cần giảm 3, đủ để lấy hết từ Lot 1
        // (lot cũ nhất theo FIFO), KHÔNG cần đụng tới Lot 2.
        $this->service()->adjustStock($this->warehouse->id, $this->product->id, 12);

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('7.000', (string) $lots[0]->quantity_remaining); // 10 - 3
        $this->assertSame('5.000', (string) $lots[1]->quantity_remaining); // không bị đụng
        $this->assertSame(2, StockLot::count()); // không tạo lot mới
        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 5 — Adjustment về 0 */
    public function test_adjustment_to_zero_keeps_lots_but_zeroes_remaining(): void
    {
        $this->receive(10);
        $this->receive(5);

        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 0);

        $this->assertSame('0.000', (string) $stock->quantity_on_hand);
        $this->assertEquals(0.0, $this->sumLotRemaining());
        $this->assertSame(2, StockLot::count()); // không xoá lot

        $movement = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertSame('-15.000', (string) $movement->quantity);
        $this->assertSame('15.000', (string) $movement->quantity_before);
        $this->assertSame('0.000', (string) $movement->quantity_after);
    }

    /** Test 6 — Adjustment tăng nhiều lần tạo nhiều lot riêng biệt, không merge */
    public function test_adjustment_increase_multiple_times_creates_separate_lots(): void
    {
        $this->receive(10);
        $this->service()->adjustStock($this->warehouse->id, $this->product->id, 15); // +5
        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 18); // +3

        $this->assertSame('18.000', (string) $stock->quantity_on_hand);

        $lots = StockLot::orderBy('id')->get()->pluck('quantity_received');
        $this->assertSame(['10.000', '5.000', '3.000'], $lots->map(fn ($q) => (string) $q)->all());
    }

    /** Test 7 — actualQuantity âm bị reject, không đổi gì */
    public function test_adjustment_rejects_negative_actual_quantity(): void
    {
        $this->receive(10);

        try {
            $this->service()->adjustStock($this->warehouse->id, $this->product->id, -1);
            $this->fail('Kỳ vọng ValidationException với actualQuantity âm.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertSame('10.000', (string) $this->currentStock()->quantity_on_hand);
        $this->assertSame(1, StockLot::count());
        $this->assertSame(0, StockMovement::where('movement_type', 'adjustment')->count());
    }

    /** Test 8 — Adjustment (tăng + giảm) không bao giờ tạo StockAllocation */
    public function test_adjustment_never_creates_allocation(): void
    {
        $this->receive(10);
        $this->service()->adjustStock($this->warehouse->id, $this->product->id, 15); // tăng
        $this->service()->adjustStock($this->warehouse->id, $this->product->id, 8);  // giảm

        $this->assertSame(0, StockAllocation::count());
    }

    /** Test 9 — Invariant sau Adjustment tăng */
    public function test_invariant_holds_after_adjustment_increase(): void
    {
        $this->receive(10);
        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 15);

        $this->assertEquals((float) $stock->quantity_on_hand, $this->sumLotRemaining());
    }

    /** Test 10 — Invariant sau Adjustment giảm qua nhiều lot */
    public function test_invariant_holds_after_adjustment_decrease_fifo(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-02 08:00:00');
        $this->receive(8, '2026-09-03 08:00:00');

        $stock = $this->service()->adjustStock($this->warehouse->id, $this->product->id, 10);

        $this->assertEquals((float) $stock->quantity_on_hand, $this->sumLotRemaining());
    }

    /** Test 11 — Stock/StockLot lệch nhau: Adjustment giảm phải reject, rollback toàn bộ */
    public function test_adjustment_decrease_rejects_when_stock_and_lot_sum_are_inconsistent(): void
    {
        $stock = $this->receive(10); // Stock=10, Lot#1 remaining=10

        // Giả lập data lệch: bump Stock lên 20 mà KHÔNG có Lot tương ứng.
        app(StockRepository::class)->updateQuantity($stock, 20);

        try {
            // actual=5 -> cần giảm 15, nhưng lot chỉ có 10 remaining.
            $this->service()->adjustStock($this->warehouse->id, $this->product->id, 5);
            $this->fail('Kỳ vọng ValidationException vì StockLot không đủ để consume.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $freshStock = $this->currentStock();
        $this->assertSame('20.000', (string) $freshStock->quantity_on_hand); // rollback, giữ giá trị đã bump
        $this->assertSame('10.000', (string) StockLot::first()->quantity_remaining);
        $this->assertSame(0, StockMovement::where('movement_type', 'adjustment')->count());
    }
}