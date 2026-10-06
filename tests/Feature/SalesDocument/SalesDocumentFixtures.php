<?php

namespace Tests\Feature\SalesDocument;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SalesDocumentService;
use Carbon\CarbonInterface;

trait SalesDocumentFixtures
{
    protected Warehouse $wh1;
    protected Warehouse $wh2;
    protected Product $productA;
    protected Product $productB;
    protected Customer $customer;
    protected User $admin;
    protected User $viewer;
    protected User $noPost;
    protected User $noAccess;

    protected function createSalesFixtures(): void
    {
        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->wh1 = Warehouse::create(['code' => 'WH-01', 'name' => 'Kho 01']);
        $this->wh2 = Warehouse::create(['code' => 'WH-02', 'name' => 'Kho 02']);

        $this->productA = $this->makeProduct('HG54', 100000, $category, $unit);
        $this->productB = $this->makeProduct('XP10', 50000, $category, $unit);

        $this->customer = Customer::create([
            'customer_code' => 'KH001',
            'name' => 'Khách A',
            'customer_type' => Customer::TYPE_INDIVIDUAL,
        ]);

        $all = ['view', 'create', 'update', 'delete', 'post'];

        $this->admin = $this->makeUser('admin@example.com', 'sales-admin', $all);
        $this->viewer = $this->makeUser('viewer@example.com', 'sales-viewer', ['view']);
        $this->noPost = $this->makeUser('nopost@example.com', 'sales-nopost', ['view', 'create', 'update', 'delete']);
        $this->noAccess = $this->makeUser('none@example.com', 'sales-none', []);
    }

    private function makeProduct(string $sku, float $price, Category $category, Unit $unit): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => 'Sản phẩm ' . $sku,
            'slug' => strtolower($sku),
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'selling_price' => $price,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $email, string $roleSlug, array $actions): User
    {
        $module = Module::firstOrCreate(['slug' => 'sales-document'], ['name' => 'Bán hàng']);
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);

        $ids = collect($actions)->map(fn (string $action) => Permission::firstOrCreate(
            ['module_id' => $module->id, 'action' => $action],
            ['name' => 'sales-document.' . $action]
        )->id);

        $role->permissions()->attach($ids);

        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /** Resolve sau khi mock (nếu có) để Service nhận đúng dependency. */
    protected function salesService(): SalesDocumentService
    {
        return app(SalesDocumentService::class);
    }

    protected function receive(Warehouse $warehouse, Product $product, float $qty, ?CarbonInterface $at = null): void
    {
        app(InventoryService::class)->receiveStock($warehouse->id, $product->id, $qty, $at ?? now(), null, []);
    }

    protected function payload(array $items = null, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => null,
            'warehouse_id' => $this->wh1->id,
            'document_date' => '2026-10-03',
            'note' => 'Bán test',
            'items' => $items ?? [
                ['product_id' => $this->productA->id, 'quantity' => 4],
            ],
        ], $overrides);
    }

    protected function onHand(Warehouse $warehouse, Product $product): float
    {
        return (float) (Stock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)->first()?->quantity_on_hand ?? 0.0);
    }

    protected function lotSum(Warehouse $warehouse, Product $product): float
    {
        return (float) StockLot::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)->sum('quantity_remaining');
    }
}