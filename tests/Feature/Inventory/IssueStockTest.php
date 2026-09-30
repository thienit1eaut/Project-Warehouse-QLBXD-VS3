<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Repositories\StockMovementRepository;
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
class IssueStockTest extends TestCase
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
            'sku' => 'SKU-ISSUE-001',
            'name' => 'Sản phẩm test Issue',
            'slug' => 'sp-test-issue',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'WH-ISSUE', 'name' => 'Kho test Issue']);
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

    /** Test 1 — Issue từ một Lot */
    public function test_issue_from_a_single_lot(): void
    {
        $this->receive(10);

        $stock = $this->service()->issueStock($this->warehouse->id, $this->product->id, 3);

        $this->assertSame('7.000', (string) $stock->quantity_on_hand);
        $this->assertSame('7.000', (string) StockLot::first()->quantity_remaining);

        $movement = StockMovement::where('movement_type', 'out')->first();
        $this->assertNotNull($movement);
        $this->assertSame('-3.000', (string) $movement->quantity);
        $this->assertSame('10.000', (string) $movement->quantity_before);
        $this->assertSame('7.000', (string) $movement->quantity_after);
    }

    /** Test 2 — FIFO qua nhiều Lot */
    public function test_issue_consumes_multiple_lots_in_fifo_order(): void
    {
        $this->receive(10, '2026-09-01 08:00:00');
        $this->receive(5, '2026-09-05 08:00:00');

        $stock = $this->service()->issueStock($this->warehouse->id, $this->product->id, 12);

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('0.000', (string) $lots[0]->quantity_remaining);
        $this->assertSame('3.000', (string) $lots[1]->quantity_remaining);
        $this->assertSame('3.000', (string) $stock->quantity_on_hand);

        $sumRemaining = $lots->sum(fn ($l) => (float) $l->quantity_remaining);
        $this->assertEquals((float) $stock->quantity_on_hand, $sumRemaining);
    }

    /** Test 3 — Không consume Lot mới trước Lot cũ */
    public function test_issue_does_not_consume_newer_lot_before_older_lot(): void
    {
        $this->receive(5, '2026-09-01 08:00:00');
        $this->receive(7, '2026-09-02 08:00:00');
        $this->receive(10, '2026-09-03 08:00:00');

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 6);

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('0.000', (string) $lots[0]->quantity_remaining);
        $this->assertSame('6.000', (string) $lots[1]->quantity_remaining);
        $this->assertSame('10.000', (string) $lots[2]->quantity_remaining);
    }

    /** Test 4 — Cùng received_at, khác id: phải consume id nhỏ hơn trước */
    public function test_issue_uses_id_as_tiebreaker_when_received_at_is_equal(): void
    {
        $sameTimestamp = '2026-09-10 10:00:00';
        $this->receive(5, $sameTimestamp);
        $this->receive(5, $sameTimestamp);

        $this->service()->issueStock($this->warehouse->id, $this->product->id, 3);

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('2.000', (string) $lots[0]->quantity_remaining);
        $this->assertSame('5.000', (string) $lots[1]->quantity_remaining);
    }

    /** Test 5 — Issue đúng bằng toàn bộ tồn: Lot về 0 nhưng KHÔNG bị xoá */
    public function test_issue_exact_total_stock_zeroes_out_lots_without_deleting(): void
    {
        $this->receive(10);
        $this->receive(5);

        $stock = $this->service()->issueStock($this->warehouse->id, $this->product->id, 15);

        $this->assertSame('0.000', (string) $stock->quantity_on_hand);
        $this->assertSame(2, StockLot::count());
        $this->assertSame('0.000', (string) StockLot::orderBy('id')->get()[0]->quantity_remaining);
        $this->assertSame('0.000', (string) StockLot::orderBy('id')->get()[1]->quantity_remaining);
    }

    /** Test 6 — Issue vượt tồn: reject toàn bộ, không đụng gì */
    public function test_issue_exceeding_stock_is_rejected_without_any_partial_change(): void
    {
        $this->receive(10);
        $this->receive(5);

        $lotsBefore = StockLot::orderBy('id')->pluck('quantity_remaining', 'id')->toArray();

        try {
            $this->service()->issueStock($this->warehouse->id, $this->product->id, 16);
            $this->fail('Kỳ vọng ValidationException khi issue vượt tồn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $stock = Stock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertSame('15.000', (string) $stock->quantity_on_hand);

        $lotsAfter = StockLot::orderBy('id')->pluck('quantity_remaining', 'id')->toArray();
        $this->assertEquals($lotsBefore, $lotsAfter);

        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
    }

    /** Test 7 — Stock nói đủ nhưng StockLot thực tế không đủ -> reject, không bypass FIFO */
    public function test_issue_rejects_when_stock_and_lot_sum_are_inconsistent(): void
    {
        $stock = $this->receive(10);

        // Giả lập dữ liệu cũ/lệch: bump Stock lên 20 mà KHÔNG tạo Lot tương ứng.
        app(StockRepository::class)->updateQuantity($stock, 20);

        try {
            $this->service()->issueStock($this->warehouse->id, $this->product->id, 15);
            $this->fail('Kỳ vọng exception vì StockLot không đủ để consume dù Stock báo đủ.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $freshStock = Stock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertSame('20.000', (string) $freshStock->quantity_on_hand);
        $this->assertSame('10.000', (string) StockLot::first()->quantity_remaining);
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
    }

    /** Test 8 — Transaction rollback khi lỗi xảy ra sau khi Lot đã được consume trong transaction */
    public function test_issue_rolls_back_lot_and_stock_changes_if_movement_creation_fails(): void
    {
        $this->receive(10);

        $this->mock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('Giả lập lỗi ghi StockMovement'));
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
    }
}