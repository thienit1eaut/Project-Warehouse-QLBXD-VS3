<?php

namespace Tests\Feature\PurchaseReceipt;

use App\Models\Category;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\StockMovementRepository;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PHASE I — Purchase Receipt. Domain tests (qua PurchaseReceiptService) + HTTP tests
 * (Route -> permission -> FormRequest -> Controller -> Service -> InventoryService -> DB).
 * Test tự tạo Module/Permission/Role, không gọi DatabaseSeeder.
 */
class PurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $productX;
    private Product $productY;
    private User $fullUser;
    private User $viewOnlyUser;
    private User $noAccessUser;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->supplier = Supplier::create(['code' => 'SUP-001', 'name' => 'NCC A', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['code' => 'WH-01', 'name' => 'Kho 01']);

        $this->productX = $this->makeProduct('HG54', $category, $unit);
        $this->productY = $this->makeProduct('XP10', $category, $unit);

        $module = Module::create(['name' => 'Phiếu nhập kho', 'slug' => 'purchase-receipt']);
        $perms = collect(['view', 'create', 'post'])->mapWithKeys(fn (string $a) => [
            $a => Permission::create(['module_id' => $module->id, 'action' => $a, 'name' => 'PR - ' . $a]),
        ]);

        $fullRole = Role::create(['name' => 'PR Full', 'slug' => 'pr-full']);
        $fullRole->permissions()->attach($perms->pluck('id'));

        $viewRole = Role::create(['name' => 'PR View', 'slug' => 'pr-view']);
        $viewRole->permissions()->attach([$perms['view']->id]);

        $noRole = Role::create(['name' => 'PR None', 'slug' => 'pr-none']);

        $this->fullUser = $this->makeUser('full@example.com', $fullRole);
        $this->viewOnlyUser = $this->makeUser('view@example.com', $viewRole);
        $this->noAccessUser = $this->makeUser('none@example.com', $noRole);
    }

    // ------------------------------------------------------------------ helpers

    private function makeProduct(string $sku, Category $category, Unit $unit): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => 'Sản phẩm ' . $sku,
            'slug' => strtolower($sku),
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $email, Role $role): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /** Resolve sau khi mock (nếu có) để Service nhận đúng dependency. */
    private function service(): PurchaseReceiptService
    {
        return app(PurchaseReceiptService::class);
    }

    private function payload(?array $items = null): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-10-01',
            'note' => 'Nhập test',
            'items' => $items ?? [
                ['product_id' => $this->productX->id, 'quantity' => 20, 'unit_price' => 50000],
            ],
        ];
    }

    private function onHand(Product $product): float
    {
        return (float) (Stock::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)->first()?->quantity_on_hand ?? 0.0);
    }

    private function lotSum(Product $product): float
    {
        return (float) StockLot::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)->sum('quantity_remaining');
    }

    private function assertInventoryEmpty(): void
    {
        $this->assertSame(0, Stock::count());
        $this->assertSame(0, StockLot::count());
        $this->assertSame(0, StockMovement::count());
    }

    // ------------------------------------------------------------------ Test 1 + 8

    public function test_create_draft_does_not_change_inventory(): void
    {
        $receipt = $this->service()->createDraft($this->payload(), $this->fullUser->id);

        $this->assertSame(PurchaseReceipt::STATUS_DRAFT, $receipt->status);
        $this->assertStringStartsWith('PN-', $receipt->receipt_code);
        $this->assertSame($this->fullUser->id, $receipt->created_by);
        $this->assertNull($receipt->posted_at);
        $this->assertInventoryEmpty();
    }

    public function test_multiple_drafts_still_do_not_affect_inventory(): void
    {
        $this->service()->createDraft($this->payload(), $this->fullUser->id);
        $this->service()->createDraft($this->payload(), $this->fullUser->id);

        $this->assertSame(2, PurchaseReceipt::count());
        $this->assertInventoryEmpty();
    }

    // ------------------------------------------------------------------ Test 2

    public function test_receipt_can_have_multiple_items(): void
    {
        $receipt = $this->service()->createDraft($this->payload([
            ['product_id' => $this->productX->id, 'quantity' => 10],
            ['product_id' => $this->productY->id, 'quantity' => 5, 'unit_price' => 1000, 'expiry_date' => '2027-01-01'],
        ]));

        $this->assertCount(2, $receipt->items);
        $this->assertSame('2027-01-01', $receipt->items->firstWhere('product_id', $this->productY->id)->expiry_date->toDateString());
    }

    public function test_draft_requires_valid_items_at_service_level(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->createDraft($this->payload([
            ['product_id' => $this->productX->id, 'quantity' => 0],
        ]));
    }

    // ------------------------------------------------------------------ Test 3

    public function test_post_receipt_creates_stock_lot_and_movement(): void
    {
        $receipt = $this->service()->createDraft($this->payload(), $this->fullUser->id);

        $posted = $this->service()->post($receipt->id, $this->fullUser->id);

        $this->assertSame(PurchaseReceipt::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->posted_at);

        $this->assertEqualsWithDelta(20.0, $this->onHand($this->productX), 0.0005);
        $this->assertSame(1, StockLot::count());
        $this->assertEqualsWithDelta(20.0, (float) StockLot::first()->quantity_remaining, 0.0005);

        $movement = StockMovement::first();
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('in', $movement->movement_type);
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity, 0.0005);
        $this->assertSame('purchase_receipt', $movement->reference_type);
        $this->assertSame($receipt->id, (int) $movement->reference_id);
        $this->assertSame($this->fullUser->id, $movement->user_id);

        $this->assertEqualsWithDelta($this->onHand($this->productX), $this->lotSum($this->productX), 0.0005);
    }

    // ------------------------------------------------------------------ Test 4

    public function test_post_multiple_products_updates_each_product(): void
    {
        $receipt = $this->service()->createDraft($this->payload([
            ['product_id' => $this->productX->id, 'quantity' => 10],
            ['product_id' => $this->productY->id, 'quantity' => 5],
        ]));

        $this->service()->post($receipt->id);

        $this->assertEqualsWithDelta(10.0, $this->onHand($this->productX), 0.0005);
        $this->assertEqualsWithDelta(5.0, $this->onHand($this->productY), 0.0005);
        $this->assertSame(2, StockLot::count());
        $this->assertSame(2, StockMovement::where('movement_type', 'in')->count());

        foreach ([$this->productX, $this->productY] as $product) {
            $this->assertEqualsWithDelta($this->onHand($product), $this->lotSum($product), 0.0005);
        }
    }

    // ------------------------------------------------------------------ Test 5 + 6

    public function test_receipt_belongs_to_supplier_and_warehouse(): void
    {
        $receipt = $this->service()->createDraft($this->payload());

        $this->assertTrue($receipt->supplier->is($this->supplier));
        $this->assertTrue($receipt->warehouse->is($this->warehouse));
        $this->assertSame(1, $this->supplier->purchaseReceipts()->count());
    }

    // ------------------------------------------------------------------ Test 7

    public function test_cannot_post_twice(): void
    {
        $receipt = $this->service()->createDraft($this->payload());
        $this->service()->post($receipt->id);

        try {
            $this->service()->post($receipt->id);
            $this->fail('POST lần 2 phải bị chặn.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receipt', $e->errors());
        }

        $this->assertEqualsWithDelta(20.0, $this->onHand($this->productX), 0.0005);
        $this->assertSame(1, StockLot::count());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_cannot_update_receipt_after_posted(): void
    {
        $receipt = $this->service()->createDraft($this->payload());
        $this->service()->post($receipt->id);

        try {
            $this->service()->updateDraft($receipt->id, $this->payload([
                ['product_id' => $this->productX->id, 'quantity' => 999],
            ]));
            $this->fail('Không được sửa phiếu đã POSTED.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receipt', $e->errors());
        }

        $this->assertEqualsWithDelta(20.0, (float) $receipt->fresh()->items->first()->quantity, 0.0005);
    }

    // ------------------------------------------------------------------ Test 9

    public function test_failure_on_second_item_rolls_back_entire_post(): void
    {
        $receipt = $this->service()->createDraft($this->payload([
            ['product_id' => $this->productX->id, 'quantity' => 10],
            ['product_id' => $this->productY->id, 'quantity' => 5],
        ]));

        // Item 1 ghi movement thật, item 2 ném lỗi -> phải rollback cả item 1.
        $this->partialMock(StockMovementRepository::class, function ($mock) {
            $mock->shouldReceive('create')->once()->passthru();
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('simulated failure'));
        });

        try {
            $this->service()->post($receipt->id);
            $this->fail('Phải ném exception ở item thứ 2.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        $this->assertInventoryEmpty();
        $this->assertSame(PurchaseReceipt::STATUS_DRAFT, $receipt->fresh()->status);
        $this->assertNull($receipt->fresh()->posted_at);
    }

    // ------------------------------------------------------------------ Test 10

    public function test_user_without_permission_cannot_access_or_post(): void
    {
        $receipt = $this->service()->createDraft($this->payload());

        $this->actingAs($this->noAccessUser)->get('/admin/purchase-receipts')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->noAccessUser)->post('/admin/purchase-receipts', $this->payload())
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->viewOnlyUser)->get('/admin/purchase-receipts')->assertOk();

        $this->actingAs($this->viewOnlyUser)->post("/admin/purchase-receipts/{$receipt->id}/post")
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(PurchaseReceipt::STATUS_DRAFT, $receipt->fresh()->status);
        $this->assertInventoryEmpty();
    }

    // ------------------------------------------------------------------ Test 11

    public function test_full_http_flow_create_show_post(): void
    {
        $this->actingAs($this->fullUser)->get('/admin/purchase-receipts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/PurchaseReceipts/Index'));

        $this->actingAs($this->fullUser)->get('/admin/purchase-receipts/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/PurchaseReceipts/Create'));

        $store = $this->actingAs($this->fullUser)->post('/admin/purchase-receipts', $this->payload());
        $receipt = PurchaseReceipt::firstOrFail();

        $store->assertRedirect("/admin/purchase-receipts/{$receipt->id}");
        $store->assertSessionHas('success');
        $this->assertInventoryEmpty();

        $this->actingAs($this->fullUser)->get("/admin/purchase-receipts/{$receipt->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/PurchaseReceipts/Show'));

        $post = $this->actingAs($this->fullUser)->post("/admin/purchase-receipts/{$receipt->id}/post");
        $post->assertRedirect("/admin/purchase-receipts/{$receipt->id}");
        $post->assertSessionHas('success');

        $this->assertSame(PurchaseReceipt::STATUS_POSTED, $receipt->fresh()->status);
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->productX), 0.0005);
        $this->assertSame(1, StockLot::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'in')->count());

        // POST lần 2 qua HTTP: bị chặn, tồn kho không đổi.
        $this->actingAs($this->fullUser)->post("/admin/purchase-receipts/{$receipt->id}/post")
            ->assertSessionHasErrors('receipt');

        $this->assertEqualsWithDelta(20.0, $this->onHand($this->productX), 0.0005);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_store_validation_rejects_invalid_input_and_creates_nothing(): void
    {
        $response = $this->actingAs($this->fullUser)->post('/admin/purchase-receipts', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-10-01',
            'items' => [['product_id' => $this->productX->id, 'quantity' => 0]],
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertSame(0, PurchaseReceipt::count());
        $this->assertInventoryEmpty();
    }
}