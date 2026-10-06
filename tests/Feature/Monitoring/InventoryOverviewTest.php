<?php

namespace Tests\Feature\Monitoring;

use App\Support\StockStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOverviewTest extends TestCase
{
    use RefreshDatabase, MonitoringFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMonitoringFixtures();
    }

    public function test_status_resolver_rules(): void
    {
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::resolve(0, 5));
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::resolve(0, 0));
        $this->assertSame(StockStatus::LOW, StockStatus::resolve(3, 5));
        $this->assertSame(StockStatus::NORMAL, StockStatus::resolve(5, 5));   // = minimum => NORMAL
        $this->assertSame(StockStatus::NORMAL, StockStatus::resolve(8, 5));
        $this->assertSame(StockStatus::NORMAL, StockStatus::resolve(2, 0));   // minimum = 0 không làm LOW
    }

    public function test_rows_are_product_by_warehouse_with_totals_and_lots(): void
    {
        $a = $this->makeProduct('A-1');
        $b = $this->makeProduct('B-1', ['minimum_stock' => 5]);
        $c = $this->makeProduct('C-1');

        $this->receive($this->whA, $a, 10);
        $this->receive($this->whA, $a, 5);   // 2 lô ở kho A
        $this->receive($this->whB, $a, 5);
        $this->receive($this->whA, $b, 3);

        $rows = $this->overview();

        $this->assertSame(4, $rows['total']); // A@A, A@B, B@A, C (chưa có tồn)

        $a1 = $this->findRow($rows, 'A-1', 'WH-A');
        $this->assertEqualsWithDelta(15.0, $a1['on_hand'], 0.0005);
        $this->assertEqualsWithDelta(20.0, $a1['product_total'], 0.0005);
        $this->assertSame(2, $a1['lots_count']);
        $this->assertNotNull($a1['stock_id']);

        $a2 = $this->findRow($rows, 'A-1', 'WH-B');
        $this->assertEqualsWithDelta(5.0, $a2['on_hand'], 0.0005);
        $this->assertEqualsWithDelta(20.0, $a2['product_total'], 0.0005);
        $this->assertSame(1, $a2['lots_count']);

        $cRow = $this->findRow($rows, 'C-1');
        $this->assertNull($cRow['warehouse']);
        $this->assertNull($cRow['stock_id']);
        $this->assertEqualsWithDelta(0.0, $cRow['on_hand'], 0.0005);
        $this->assertSame(StockStatus::OUT_OF_STOCK, $cRow['status']);
        $this->assertSame(0, $cRow['lots_count']);
    }

    public function test_status_per_row_follows_minimum_stock_rules(): void
    {
        $low = $this->makeProduct('LOW', ['minimum_stock' => 5]);
        $equal = $this->makeProduct('EQ', ['minimum_stock' => 5]);
        $high = $this->makeProduct('HIGH', ['minimum_stock' => 5]);
        $zeroMin = $this->makeProduct('ZMIN', ['minimum_stock' => 0]);
        $out = $this->makeProduct('OUT', ['minimum_stock' => 5]);
        $outZeroMin = $this->makeProduct('OUT0', ['minimum_stock' => 0]);

        $this->receive($this->whA, $low, 3);
        $this->receive($this->whA, $equal, 5);
        $this->receive($this->whA, $high, 9);
        $this->receive($this->whA, $zeroMin, 2);
        $this->zeroStock($this->whA, $out);
        $this->zeroStock($this->whA, $outZeroMin);

        $rows = $this->overview();

        $this->assertSame(StockStatus::LOW, $this->findRow($rows, 'LOW')['status']);
        $this->assertSame(StockStatus::NORMAL, $this->findRow($rows, 'EQ')['status']);
        $this->assertSame(StockStatus::NORMAL, $this->findRow($rows, 'HIGH')['status']);
        $this->assertSame(StockStatus::NORMAL, $this->findRow($rows, 'ZMIN')['status']);
        $this->assertSame(StockStatus::OUT_OF_STOCK, $this->findRow($rows, 'OUT')['status']);
        $this->assertSame(StockStatus::OUT_OF_STOCK, $this->findRow($rows, 'OUT0')['status']);
    }

    public function test_status_filter_matches_resolver_for_every_status(): void
    {
        $this->receive($this->whA, $this->makeProduct('LOW', ['minimum_stock' => 5]), 3);
        $this->receive($this->whA, $this->makeProduct('EQ', ['minimum_stock' => 5]), 5);
        $this->receive($this->whA, $this->makeProduct('ZMIN', ['minimum_stock' => 0]), 2);
        $this->zeroStock($this->whA, $this->makeProduct('OUT', ['minimum_stock' => 5]));
        $this->makeProduct('NOSTOCK');

        $all = $this->overview();

        foreach ([StockStatus::OUT_OF_STOCK, StockStatus::LOW, StockStatus::NORMAL] as $status) {
            $filtered = $this->overview(['status' => $status]);

            $expected = collect($all['data'])->where('status', $status)->pluck('sku')->sort()->values()->all();
            $actual = collect($filtered['data'])->pluck('sku')->sort()->values()->all();

            $this->assertSame($expected, $actual, "Filter '{$status}' không khớp StockStatus::resolve()");
            $this->assertSame(count($expected), $filtered['total']);
        }

        $this->assertSame(['LOW'], collect($this->overview(['status' => 'low'])['data'])->pluck('sku')->all());
        $this->assertEqualsWithDelta(
            0,
            count(array_diff(['OUT', 'NOSTOCK'], collect($this->overview(['status' => 'out_of_stock'])['data'])->pluck('sku')->all())),
            0
        );
    }

    public function test_unknown_status_is_ignored(): void
    {
        $this->receive($this->whA, $this->makeProduct('A-1'), 3);

        $this->assertSame(1, $this->overview(['status' => 'bogus'])['total']);
    }

    public function test_search_by_sku_and_name(): void
    {
        $this->receive($this->whA, $this->makeProduct('BIKE-001', ['name' => 'Xe đạp địa hình']), 5);
        $this->receive($this->whA, $this->makeProduct('PART-021', ['name' => 'Phanh đĩa']), 5);

        $bySku = $this->overview(['search' => 'BIKE']);
        $this->assertSame(['BIKE-001'], collect($bySku['data'])->pluck('sku')->all());

        $byName = $this->overview(['search' => 'Phanh']);
        $this->assertSame(['PART-021'], collect($byName['data'])->pluck('sku')->all());
    }

    public function test_warehouse_filter_lists_all_active_products_with_zero_for_missing_stock(): void
    {
        $a = $this->makeProduct('A-1', ['minimum_stock' => 5]);
        $b = $this->makeProduct('B-1');

        $this->receive($this->whA, $a, 10);
        $this->receive($this->whB, $b, 4);

        $rows = $this->overview(['warehouse_id' => $this->whA->id]);

        $this->assertSame(2, $rows['total']);

        $aRow = $this->findRow($rows, 'A-1');
        $this->assertSame('WH-A', $aRow['warehouse']['code']);
        $this->assertEqualsWithDelta(10.0, $aRow['on_hand'], 0.0005);
        $this->assertSame(StockStatus::NORMAL, $aRow['status']);

        // B không có tồn ở kho A => hiện 0 + Hết hàng, gắn đúng kho đang lọc
        $bRow = $this->findRow($rows, 'B-1');
        $this->assertSame('WH-A', $bRow['warehouse']['code']);
        $this->assertEqualsWithDelta(0.0, $bRow['on_hand'], 0.0005);
        $this->assertEqualsWithDelta(4.0, $bRow['product_total'], 0.0005); // tổng tồn vẫn tính trên mọi kho
        $this->assertSame(StockStatus::OUT_OF_STOCK, $bRow['status']);
        $this->assertNull($bRow['stock_id']);
    }

    public function test_category_and_brand_filters_combine_with_others(): void
    {
        $bike = $this->makeProduct('BIKE', ['category_id' => $this->catRoad->id, 'brand_id' => $this->brandX->id, 'minimum_stock' => 5]);
        $brake = $this->makeProduct('BRAKE', ['category_id' => $this->catPart->id, 'brand_id' => $this->brandY->id, 'minimum_stock' => 5]);
        $chain = $this->makeProduct('CHAIN', ['category_id' => $this->catPart->id, 'brand_id' => $this->brandX->id, 'minimum_stock' => 5]);

        $this->receive($this->whA, $bike, 2);
        $this->receive($this->whA, $brake, 2);
        $this->receive($this->whB, $chain, 2);

        $skus = fn (array $q) => collect($this->overview($q)['data'])->pluck('sku')->sort()->values()->all();

        $this->assertSame(['BRAKE', 'CHAIN'], $skus(['category_id' => $this->catPart->id]));
        $this->assertSame(['BIKE', 'CHAIN'], $skus(['brand_id' => $this->brandX->id]));
        $this->assertSame(['CHAIN'], $skus(['category_id' => $this->catPart->id, 'brand_id' => $this->brandX->id]));
        $this->assertSame(['BRAKE'], $skus(['category_id' => $this->catPart->id, 'warehouse_id' => $this->whA->id, 'status' => 'low', 'search' => 'BRA']));
        $this->assertSame([], $skus(['category_id' => $this->catRoad->id, 'status' => 'normal']));
    }

    public function test_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->receive($this->whA, $this->makeProduct(sprintf('P-%02d', $i)), 1);
        }

        $page1 = $this->overview();
        $this->assertSame(25, $page1['total']);
        $this->assertCount(20, $page1['data']);

        $page2 = $this->overview(['page' => 2]);
        $this->assertCount(5, $page2['data']);

        $this->assertSame([], array_intersect(
            collect($page1['data'])->pluck('sku')->all(),
            collect($page2['data'])->pluck('sku')->all()
        ));
    }

    public function test_inactive_products_are_hidden_unless_they_still_have_stock(): void
    {
        $inactiveEmpty = $this->makeProduct('INACT-EMPTY', ['is_active' => false]);
        $inactiveStock = $this->makeProduct('INACT-STOCK', ['is_active' => false]);
        $this->receive($this->whA, $inactiveStock, 4);

        $skus = collect($this->overview()['data'])->pluck('sku')->all();

        $this->assertNotContains('INACT-EMPTY', $skus);
        $this->assertContains('INACT-STOCK', $skus);
    }

    public function test_overview_is_read_only(): void
    {
        $this->receive($this->whA, $this->makeProduct('A-1'), 5);
        $before = $this->countsSnapshot();

        $this->overview();
        $this->overview(['warehouse_id' => $this->whB->id, 'status' => 'out_of_stock']);

        $this->assertSame($before, $this->countsSnapshot());
    }

    public function test_permission(): void
    {
        $this->actingAs($this->noAccess)->get('/admin/stock')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->get('/admin/stock')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/stock')->assertRedirect(route('login'));
    }
}