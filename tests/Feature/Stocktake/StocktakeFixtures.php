<?php

namespace Tests\Feature\Stocktake;

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
use App\Services\InventoryService;
use App\Services\StocktakeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Assert;

trait StocktakeFixtures
{
    protected Warehouse $whA;
    protected Warehouse $whB;
    protected Warehouse $whInactive;
    protected Product $productA;
    protected Product $productB;
    protected Product $productC;
    protected User $admin;
    protected User $viewer;
    protected User $noPost;
    protected User $noAccess;

    protected function createStocktakeFixtures(): void
    {
        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->whA = Warehouse::create(['code' => 'WH-A', 'name' => 'Kho A']);
        $this->whB = Warehouse::create(['code' => 'WH-B', 'name' => 'Kho B']);
        $this->whInactive = Warehouse::create(['code' => 'WH-X', 'name' => 'Kho ngừng']);
        $this->whInactive->forceFill(['is_active' => false])->save();

        // Tạo theo thứ tự để id: A < B < C (test lock ordering dựa vào điều này).
        $this->productA = $this->makeProduct('HG54', $category, $unit);
        $this->productB = $this->makeProduct('XP10', $category, $unit);
        $this->productC = $this->makeProduct('PK01', $category, $unit);

        $all = ['view', 'create', 'update', 'delete', 'post'];

        $this->admin = $this->makeUser('admin@example.com', 'stocktake-admin', $all);
        $this->viewer = $this->makeUser('viewer@example.com', 'stocktake-viewer', ['view']);
        $this->noPost = $this->makeUser('nopost@example.com', 'stocktake-nopost', ['view', 'create', 'update', 'delete']);
        $this->noAccess = $this->makeUser('none@example.com', 'stocktake-none', []);
    }

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

    private function makeUser(string $email, string $roleSlug, array $actions): User
    {
        $module = Module::firstOrCreate(['slug' => 'stocktake'], ['name' => 'Phiếu kiểm kê']);
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);

        $ids = collect($actions)->map(fn (string $action) => Permission::firstOrCreate(
            ['module_id' => $module->id, 'action' => $action],
            ['name' => 'stocktake.' . $action]
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
    protected function stocktakeService(): StocktakeService
    {
        return app(StocktakeService::class);
    }

    protected function receive(Warehouse $warehouse, Product $product, float $qty, ?CarbonInterface $at = null): void
    {
        app(InventoryService::class)->receiveStock($warehouse->id, $product->id, $qty, $at ?? now(), null, []);
    }

    protected function issue(Warehouse $warehouse, Product $product, float $qty): void
    {
        app(InventoryService::class)->issueStock($warehouse->id, $product->id, $qty);
    }

    protected function line(Product $product, float $actual): array
    {
        return ['product_id' => $product->id, 'actual_quantity' => $actual];
    }

    protected function payload(array $items = null, array $overrides = []): array
    {
        return array_merge([
            'warehouse_id' => $this->whA->id,
            'stocktake_date' => '2026-10-03',
            'note' => 'Kiểm kê test',
            'items' => $items ?? [$this->line($this->productA, 18)],
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

    protected function lots(Warehouse $warehouse, Product $product): Collection
    {
        return StockLot::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->orderBy('received_at')->orderBy('id')->get();
    }

    protected function adjustmentMovements()
    {
        return StockMovement::where('movement_type', 'adjustment');
    }

    protected function snapshotCounts(): array
    {
        return [
            'movements' => StockMovement::count(),
            'lots' => StockLot::count(),
            'allocations' => StockAllocation::count(),
            'stocks' => Stock::count(),
        ];
    }

    protected function assertStockMatchesLots(Warehouse $warehouse, Product $product): void
    {
        Assert::assertEqualsWithDelta(
            $this->onHand($warehouse, $product),
            $this->lotSum($warehouse, $product),
            0.0005,
            "Stock != SUM(lot) tại {$warehouse->code} / {$product->sku}"
        );
    }
}