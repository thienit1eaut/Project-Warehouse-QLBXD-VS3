<?php

namespace Tests\Feature\Monitoring;

use App\Models\PurchaseReceipt;
use App\Models\SalesDocument;
use App\Models\StockTransfer;
use App\Models\Stocktake;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
{
    use RefreshDatabase, MonitoringFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMonitoringFixtures();
    }

    public function test_kpis(): void
    {
        $normal = $this->makeProduct('NORMAL', ['minimum_stock' => 5]);
        $low = $this->makeProduct('LOW', ['minimum_stock' => 5]);
        $this->makeProduct('NOSTOCK', ['minimum_stock' => 5]);          // chưa từng có Stock => hết hàng
        $zero = $this->makeProduct('ZERO', ['minimum_stock' => 5]);
        $zeroMin = $this->makeProduct('ZMIN', ['minimum_stock' => 0]);  // có tồn, min = 0 => không LOW
        $this->makeProduct('INACTIVE', ['is_active' => false]);         // không đếm

        $this->receive($this->whA, $normal, 20);
        $this->receive($this->whA, $low, 3);
        $this->zeroStock($this->whA, $zero);
        $this->receive($this->whB, $zeroMin, 2);

        $kpis = $this->dashboardInventory()['kpis'];

        $this->assertSame(5, $kpis['total_products']);          // NORMAL, LOW, NOSTOCK, ZERO, ZMIN
        $this->assertSame(2, $kpis['total_warehouses']);        // A, B (kho ngừng không đếm)
        $this->assertEqualsWithDelta(25.0, $kpis['total_on_hand'], 0.0005); // 20 + 3 + 0 + 2
        $this->assertSame(2, $kpis['out_of_stock_products']);   // NOSTOCK, ZERO
        $this->assertSame(1, $kpis['low_stock_products']);      // LOW
    }

    public function test_low_stock_kpi_uses_product_total_across_warehouses(): void
    {
        $product = $this->makeProduct('SPLIT', ['minimum_stock' => 10]);
        $this->receive($this->whA, $product, 6);
        $this->receive($this->whB, $product, 6); // tổng 12 >= 10

        $this->assertSame(0, $this->dashboardInventory()['kpis']['low_stock_products']);

        // ... trong khi từng dòng kho (6 < 10) vẫn là LOW ở Inventory Overview
        $rows = $this->overview();
        $this->assertSame('low', $this->findRow($rows, 'SPLIT', 'WH-A')['status']);
        $this->assertSame('low', $this->findRow($rows, 'SPLIT', 'WH-B')['status']);
    }

    public function test_draft_document_counts(): void
    {
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'NCC', 'is_active' => true]);
        $user = $this->manager;

        PurchaseReceipt::create(['receipt_code' => 'PN-1', 'supplier_id' => $supplier->id, 'warehouse_id' => $this->whA->id, 'status' => 'draft', 'receipt_date' => '2026-10-06']);
        PurchaseReceipt::create(['receipt_code' => 'PN-2', 'supplier_id' => $supplier->id, 'warehouse_id' => $this->whA->id, 'status' => 'posted', 'receipt_date' => '2026-10-06']);

        SalesDocument::create(['document_code' => 'SD-1', 'warehouse_id' => $this->whA->id, 'status' => 'draft', 'document_date' => '2026-10-06']);
        SalesDocument::create(['document_code' => 'SD-2', 'warehouse_id' => $this->whA->id, 'status' => 'draft', 'document_date' => '2026-10-06']);

        StockTransfer::create(['transfer_code' => 'ST-1', 'from_warehouse_id' => $this->whA->id, 'to_warehouse_id' => $this->whB->id, 'status' => 'draft', 'transfer_date' => '2026-10-06', 'created_by' => $user->id]);
        StockTransfer::create(['transfer_code' => 'ST-2', 'from_warehouse_id' => $this->whA->id, 'to_warehouse_id' => $this->whB->id, 'status' => 'posted', 'transfer_date' => '2026-10-06', 'created_by' => $user->id]);

        Stocktake::create(['stocktake_code' => 'SK-1', 'warehouse_id' => $this->whA->id, 'status' => 'draft', 'stocktake_date' => '2026-10-06', 'created_by' => $user->id]);

        $drafts = $this->dashboardInventory()['drafts'];

        $this->assertSame(1, $drafts['purchase_receipts']);
        $this->assertSame(2, $drafts['sales_documents']);
        $this->assertSame(1, $drafts['stock_transfers']);
        $this->assertSame(1, $drafts['stocktakes']);
        $this->assertSame(5, $drafts['total']);
    }

    public function test_recent_movements_come_from_stock_movements(): void
    {
        $product = $this->makeProduct('A-1');
        $document = SalesDocument::create([
            'document_code' => 'SD-000012',
            'warehouse_id' => $this->whA->id,
            'status' => 'posted',
            'document_date' => '2026-10-06',
        ]);

        $inventory = app(InventoryService::class);
        $inventory->receiveStock($this->whA->id, $product->id, 20, now(), null, []);
        $inventory->issueStock($this->whA->id, $product->id, 2, [
            'reference_type' => 'sales_document',
            'reference_id' => $document->id,
        ]);
        $inventory->adjustStock($this->whA->id, $product->id, 15, []);

        $movements = $this->dashboardInventory()['recent_movements'];

        $this->assertCount(3, $movements);

        // Mới nhất trước: điều chỉnh (-3), xuất bán (-2), nhập (+20)
        $this->assertSame('Điều chỉnh', $movements[0]['type_label']);
        $this->assertEqualsWithDelta(-3.0, $movements[0]['quantity'], 0.0005);

        $this->assertSame('Xuất bán', $movements[1]['type_label']);
        $this->assertEqualsWithDelta(-2.0, $movements[1]['quantity'], 0.0005);
        $this->assertSame('SD-000012', $movements[1]['reference']['code']);
        $this->assertSame("/admin/sales/{$document->id}", $movements[1]['reference']['url']);

        $this->assertSame('Nhập kho', $movements[2]['type_label']);
        $this->assertEqualsWithDelta(20.0, $movements[2]['quantity'], 0.0005);
        $this->assertNull($movements[2]['reference']);

        $this->assertSame('A-1', $movements[2]['product']['sku']);
        $this->assertSame('WH-A', $movements[2]['warehouse']['code']);
    }

    public function test_recent_movements_are_limited_to_ten(): void
    {
        $product = $this->makeProduct('A-1');

        for ($i = 0; $i < 12; $i++) {
            $this->receive($this->whA, $product, 1);
        }

        $this->assertCount(10, $this->dashboardInventory()['recent_movements']);
    }

    public function test_total_on_hand_matches_stock_table_not_lots(): void
    {
        $product = $this->makeProduct('A-1');
        $this->receive($this->whA, $product, 10);
        $this->receive($this->whB, $product, 7);

        $this->assertEqualsWithDelta(
            (float) \App\Models\Stock::sum('quantity_on_hand'),
            $this->dashboardInventory()['kpis']['total_on_hand'],
            0.0005
        );
    }

    public function test_dashboard_is_read_only(): void
    {
        $this->receive($this->whA, $this->makeProduct('A-1'), 5);
        $before = $this->countsSnapshot();

        $this->dashboardInventory();

        $this->assertSame($before, $this->countsSnapshot());
    }

    public function test_permission(): void
    {
        // Có stock.view: thấy số liệu
        $this->assertIsArray($this->dashboardInventory($this->viewer));

        // Không có stock.view: vẫn vào được dashboard (đích redirect), nhưng KHÔNG có số liệu kho
        $this->assertNull($this->dashboardInventory($this->noAccess));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}