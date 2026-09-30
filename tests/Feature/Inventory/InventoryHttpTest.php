<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockAllocation;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE H — HTTP/Inertia integration test cho Inventory (Receive/Issue/
 * Adjustment) chạy qua đúng HTTP layer thật: Route -> auth/active middleware
 * -> permission middleware -> FormRequest -> Controller -> InventoryService
 * -> Database -> Inertia response. KHÔNG gọi thẳng InventoryService.
 *
 * ⚠️ Cần `.env.testing` (DB_CONNECTION=sqlite, DB_DATABASE=:memory:, APP_KEY)
 * — xem ReceiveStockTest.php. RefreshDatabase sẽ DROP + MIGRATE DB đang cấu hình.
 *
 * Test tự tạo Module/Permission/Role cần thiết (KHÔNG gọi DatabaseSeeder) để
 * độc lập với data seed thật, đúng nguyên tắc "mỗi test độc lập" của Phase G.
 */
class InventoryHttpTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Unit $unit;
    private Warehouse $warehouse;
    private Product $product;

    private User $authorizedUser;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->warehouse = Warehouse::create(['code' => 'WH-HTTP', 'name' => 'Kho test HTTP']);
        $this->product = $this->makeProduct('SKU-HTTP-001');

        $stockModule = Module::create(['name' => 'Tồn kho', 'slug' => 'stock']);

        $permissions = collect(['view', 'receive', 'issue', 'adjust'])
            ->map(fn (string $action) => Permission::create([
                'module_id' => $stockModule->id,
                'action' => $action,
                'name' => 'Tồn kho - ' . ucfirst($action),
            ]));

        $fullRole = Role::create(['name' => 'Inventory Full', 'slug' => 'inventory-full']);
        $fullRole->permissions()->attach($permissions->pluck('id'));

        $noAccessRole = Role::create(['name' => 'No Access', 'slug' => 'no-access']);
        // Không gán permission nào cho role này.

        $this->authorizedUser = User::create([
            'name' => 'Inventory Tester',
            'email' => 'inventory-tester@example.com',
            'password' => 'password',
            'role_id' => $fullRole->id,
            'is_active' => true,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'No Access User',
            'email' => 'no-access@example.com',
            'password' => 'password',
            'role_id' => $noAccessRole->id,
            'is_active' => true,
        ]);
    }

    private function makeProduct(string $sku, ?Category $category = null, ?Unit $unit = null): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => 'Sản phẩm ' . $sku,
            'slug' => strtolower($sku),
            'category_id' => ($category ?? $this->category)->id,
            'unit_id' => ($unit ?? $this->unit)->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);
    }

    private function stockOf(Warehouse $warehouse, Product $product): ?Stock
    {
        return Stock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->first();
    }

    private function onHand(Warehouse $warehouse, Product $product): float
    {
        return (float) ($this->stockOf($warehouse, $product)?->quantity_on_hand ?? 0.0);
    }

    private function lotRemainingSum(Warehouse $warehouse, Product $product): float
    {
        return (float) StockLot::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->sum('quantity_remaining');
    }

    // ------------------------------------------------------------------
    // Test 1 — Inventory index (authorized)
    // ------------------------------------------------------------------

    public function test_authorized_user_can_view_inventory_index(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get('/admin/inventory');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Inventory/Index'));
    }

    // ------------------------------------------------------------------
    // Test 2 — Unauthorized index
    // ------------------------------------------------------------------

    public function test_unauthorized_user_is_redirected_away_from_inventory_index(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get('/admin/inventory');

        // EnsureUserHasPermission: request thường (không JSON) -> redirect về
        // admin.dashboard kèm flash error, KHÔNG phải 403 cứng.
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // Test 3 — Receive success
    // ------------------------------------------------------------------

    public function test_receive_success_creates_stock_lot_and_movement_through_http(): void
    {
        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response->assertRedirect('/admin/inventory');
        $response->assertSessionHas('success');

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->warehouse, $this->product), 0.0005);
        $this->assertSame(1, StockLot::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->count());
        $this->assertSame(1, StockMovement::where('movement_type', 'in')->count());
    }

    // ------------------------------------------------------------------
    // Test 4 — Receive validation failure
    // ------------------------------------------------------------------

    public function test_receive_with_invalid_quantity_fails_validation_and_changes_nothing(): void
    {
        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);

        $response->assertSessionHasErrors('quantity');

        $this->assertNull($this->stockOf($this->warehouse, $this->product));
        $this->assertSame(0, StockLot::count());
        $this->assertSame(0, StockMovement::count());
    }

    // ------------------------------------------------------------------
    // Test 5 — Issue success
    // ------------------------------------------------------------------

    public function test_issue_success_consumes_lot_and_creates_allocation_through_http(): void
    {
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/issue', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        $response->assertRedirect('/admin/inventory');
        $response->assertSessionHas('success');

        $this->assertEqualsWithDelta(7.0, $this->onHand($this->warehouse, $this->product), 0.0005);

        $outMovement = StockMovement::where('movement_type', 'out')->latest('id')->first();
        $this->assertNotNull($outMovement);
        $this->assertEqualsWithDelta(-3.0, (float) $outMovement->quantity, 0.0005);

        $allocationSum = (float) StockAllocation::where('stock_movement_id', $outMovement->id)->sum('quantity');
        $this->assertEqualsWithDelta(3.0, $allocationSum, 0.0005);

        $this->assertEqualsWithDelta(7.0, $this->lotRemainingSum($this->warehouse, $this->product), 0.0005);
    }

    // ------------------------------------------------------------------
    // Test 6 — Issue insufficient stock
    // ------------------------------------------------------------------

    public function test_issue_with_insufficient_stock_is_rejected_through_http(): void
    {
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $movementCountBefore = StockMovement::count();

        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/issue', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response->assertSessionHasErrors('quantity');

        $this->assertEqualsWithDelta(5.0, $this->onHand($this->warehouse, $this->product), 0.0005);
        $this->assertEqualsWithDelta(5.0, $this->lotRemainingSum($this->warehouse, $this->product), 0.0005);
        $this->assertSame($movementCountBefore, StockMovement::count());
        $this->assertSame(0, StockAllocation::count());
    }

    // ------------------------------------------------------------------
    // Test 7 — Adjustment tăng
    // ------------------------------------------------------------------

    public function test_adjustment_increase_through_http(): void
    {
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/adjustment', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 15,
        ]);

        $response->assertRedirect('/admin/inventory');
        $response->assertSessionHas('success');

        $this->assertEqualsWithDelta(15.0, $this->onHand($this->warehouse, $this->product), 0.0005);

        $movement = StockMovement::where('movement_type', 'adjustment')->latest('id')->first();
        $this->assertEqualsWithDelta(5.0, (float) $movement->quantity, 0.0005);

        $this->assertEqualsWithDelta(15.0, $this->lotRemainingSum($this->warehouse, $this->product), 0.0005);
    }

    // ------------------------------------------------------------------
    // Test 8 — Adjustment giảm
    // ------------------------------------------------------------------

    public function test_adjustment_decrease_through_http(): void
    {
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/admin/inventory/adjustment', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 6,
        ]);

        $response->assertRedirect('/admin/inventory');

        $this->assertEqualsWithDelta(6.0, $this->onHand($this->warehouse, $this->product), 0.0005);

        $movement = StockMovement::where('movement_type', 'adjustment')->latest('id')->first();
        $this->assertEqualsWithDelta(-4.0, (float) $movement->quantity, 0.0005);

        $this->assertEqualsWithDelta(6.0, $this->lotRemainingSum($this->warehouse, $this->product), 0.0005);
    }

    // ------------------------------------------------------------------
    // Test 9 — Authorization cho cả 3 mutation route
    // ------------------------------------------------------------------

    public function test_unauthorized_user_cannot_mutate_inventory_through_http(): void
    {
        $payloadReceive = [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ];

        $responseReceive = $this->actingAs($this->unauthorizedUser)->post('/admin/inventory/receive', $payloadReceive);
        $responseReceive->assertRedirect(route('admin.dashboard'));

        $responseIssue = $this->actingAs($this->unauthorizedUser)->post('/admin/inventory/issue', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $responseIssue->assertRedirect(route('admin.dashboard'));

        $responseAdjustment = $this->actingAs($this->unauthorizedUser)->post('/admin/inventory/adjustment', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 5,
        ]);
        $responseAdjustment->assertRedirect(route('admin.dashboard'));

        // Không có gì được tạo ra dù gửi 3 request mutation.
        $this->assertNull($this->stockOf($this->warehouse, $this->product));
        $this->assertSame(0, StockLot::count());
        $this->assertSame(0, StockMovement::count());
    }

    // ------------------------------------------------------------------
    // Test 10 — Isolation giữa Product/Warehouse qua HTTP
    // ------------------------------------------------------------------

    public function test_mutation_through_http_does_not_affect_other_product_or_warehouse(): void
    {
        $warehouseB = Warehouse::create(['code' => 'WH-HTTP-B', 'name' => 'Kho test HTTP B']);
        $productY = $this->makeProduct('SKU-HTTP-Y');

        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 10,
        ]);
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $warehouseB->id, 'product_id' => $this->product->id, 'quantity' => 20,
        ]);
        $this->actingAs($this->authorizedUser)->post('/admin/inventory/receive', [
            'warehouse_id' => $this->warehouse->id, 'product_id' => $productY->id, 'quantity' => 30,
        ]);

        $this->actingAs($this->authorizedUser)->post('/admin/inventory/issue', [
            'warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 4,
        ]);

        $this->assertEqualsWithDelta(6.0, $this->onHand($this->warehouse, $this->product), 0.0005);
        $this->assertEqualsWithDelta(20.0, $this->onHand($warehouseB, $this->product), 0.0005);
        $this->assertEqualsWithDelta(30.0, $this->onHand($this->warehouse, $productY), 0.0005);
    }
}