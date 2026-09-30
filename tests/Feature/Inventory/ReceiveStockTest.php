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
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ⚠️ CHẠY TEST NÀY CẦN `.env.testing` TRỎ SANG DB RIÊNG (khuyến nghị sqlite
 * `:memory:`) — RefreshDatabase sẽ DROP + MIGRATE lại toàn bộ schema mỗi lần
 * chạy. Nếu `phpunit.xml`/`.env` hiện tại chưa cách ly khỏi DB dev (MySQL
 * warehouse_qlbx_sys), chạy `php artisan test` sẽ XÓA SẠCH data dev.
 * → Tạo file `.env.testing` với `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`
 *   trước khi chạy file test này. Xem lại audit trước đó nếu chưa làm.
 */
class ReceiveStockTest extends TestCase
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
            'sku' => 'SKU-TEST-001',
            'name' => 'Sản phẩm test',
            'slug' => 'san-pham-test',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create(['code' => 'WH-TEST', 'name' => 'Kho test']);
    }

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    /** Test 1 — Receive lần đầu */
    public function test_receive_first_time_creates_stock_lot_and_movement(): void
    {
        $stock = $this->service()->receiveStock($this->warehouse->id, $this->product->id, 10);

        $this->assertSame('10.000', (string) $stock->quantity_on_hand);
        $this->assertSame(1, StockLot::count());

        $lot = StockLot::first();
        $this->assertSame('10.000', (string) $lot->quantity_received);
        $this->assertSame('10.000', (string) $lot->quantity_remaining);
        $this->assertSame($stock->id, $lot->stock_id);
        $this->assertSame($this->warehouse->id, $lot->warehouse_id);
        $this->assertSame($this->product->id, $lot->product_id);

        $this->assertSame(1, StockMovement::count());
        $movement = StockMovement::first();
        $this->assertSame('in', $movement->movement_type);
        $this->assertSame('10.000', (string) $movement->quantity);
        $this->assertSame('0.000', (string) $movement->quantity_before);
        $this->assertSame('10.000', (string) $movement->quantity_after);
    }

    /** Test 2 — Receive lần thứ hai: phải tạo lot RIÊNG, không gộp */
    public function test_receive_twice_creates_two_separate_lots_and_keeps_invariant(): void
    {
        $this->service()->receiveStock($this->warehouse->id, $this->product->id, 10);
        $stock = $this->service()->receiveStock($this->warehouse->id, $this->product->id, 5);

        $this->assertSame('15.000', (string) $stock->quantity_on_hand);
        $this->assertSame(2, StockLot::count());

        $lots = StockLot::orderBy('id')->get();
        $this->assertSame('10.000', (string) $lots[0]->quantity_remaining);
        $this->assertSame('5.000', (string) $lots[1]->quantity_remaining);

        // Invariant bắt buộc: Stock.quantity_on_hand = SUM(StockLot.quantity_remaining)
        $sumRemaining = (string) $lots->sum(fn ($lot) => (float) $lot->quantity_remaining);
        $this->assertEquals((float) $stock->quantity_on_hand, (float) $sumRemaining);
    }

    /** Test 3 — Receive với expiry_date, và không có expiry_date (nullable) */
    public function test_receive_stores_expiry_date_when_given_and_allows_null(): void
    {
        $stock1 = $this->service()->receiveStock(
            $this->warehouse->id,
            $this->product->id,
            10,
            expiryDate: '2027-06-30'
        );

        $lotWithExpiry = StockLot::where('stock_id', $stock1->id)->first();
        $this->assertNotNull($lotWithExpiry->expiry_date);
        $this->assertSame('2027-06-30', $lotWithExpiry->expiry_date->toDateString());

        // Không truyền expiry_date -> phải là null, không bị ép buộc
        $this->service()->receiveStock($this->warehouse->id, $this->product->id, 5);
        $lotWithoutExpiry = StockLot::orderBy('id')->get()->last();
        $this->assertNull($lotWithoutExpiry->expiry_date);
    }

    /** Test 4 — Receive không hợp lệ (quantity <= 0) phải bị reject, không tạo gì cả */
    public function test_receive_rejects_zero_or_negative_quantity(): void
    {
        foreach ([0, -5] as $invalidQuantity) {
            try {
                $this->service()->receiveStock($this->warehouse->id, $this->product->id, $invalidQuantity);
                $this->fail("Kỳ vọng ValidationException với quantity={$invalidQuantity}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('quantity', $e->errors());
            }
        }

        $this->assertSame(0, Stock::count());
        $this->assertSame(0, StockLot::count());
        $this->assertSame(0, StockMovement::count());
    }

    /** Test 5 — Transaction integrity: 1 bước fail thì rollback toàn bộ, không để lại state dở dang */
    public function test_receive_rolls_back_everything_if_movement_creation_fails(): void
    {
        $this->mock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('Giả lập lỗi ghi StockMovement'));
        });

        try {
            $this->service()->receiveStock($this->warehouse->id, $this->product->id, 10);
            $this->fail('Kỳ vọng exception được ném ra từ transaction.');
        } catch (\RuntimeException $e) {
            // expected
        }

        // Không được để lại Stock/StockLot dở dang dù transaction fail ở bước cuối.
        $this->assertSame(0, Stock::count());
        $this->assertSame(0, StockLot::count());
        $this->assertSame(0, StockMovement::count());
    }
}