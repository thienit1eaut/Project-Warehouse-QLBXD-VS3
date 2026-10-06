<?php

namespace Tests\Feature\StockTransfer;

use App\Models\Category;
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
use App\Services\StockTransferService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Assert;

trait StockTransferFixtures
{
    protected Warehouse $whA;
    protected Warehouse $whB;
    protected Warehouse $whC;
    protected Warehouse $whInactive;
    protected Product $productA;
    protected Product $productB;
    protected User $admin;
    protected User $viewer;
    protected User $noPost;
    protected User $noAccess;

    protected function createTransferFixtures(): void
    {
        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        // Tạo theo thứ tự để id: A < B < C (test lock ordering dựa vào điều này).
        $this->whA = Warehouse::create(['code' => 'WH-A', 'name' => 'Kho A']);
        $this->whB = Warehouse::create(['code' => 'WH-B', 'name' => 'Kho B']);
        $this->whC = Warehouse::create(['code' => 'WH-C', 'name' => 'Kho C']);
        $this->whInactive = Warehouse::create(['code' => 'WH-X', 'name' => 'Kho ngừng']);
        $this->whInactive->forceFill(['is_active' => false])->save();

        $this->productA = $this->makeProduct('HG54', $category, $unit);
        $this->productB = $this->makeProduct('XP10', $category, $unit);

        $all = ['view', 'create', 'update', 'delete', 'post'];

        $this->admin = $this->makeUser('admin@example.com', 'transfer-admin', $all);
        $this->viewer = $this->makeUser('viewer@example.com', 'transfer-viewer', ['view']);
        $this->noPost = $this->makeUser('nopost@example.com', 'transfer-nopost', ['view', 'create', 'update', 'delete']);
        $this->noAccess = $this->makeUser('none@example.com', 'transfer-none', []);
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
        $module = Module::firstOrCreate(['slug' => 'stock-transfer'], ['name' => 'Chuyển kho']);
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);

        $ids = collect($actions)->map(fn (string $action) => Permission::firstOrCreate(
            ['module_id' => $module->id, 'action' => $action],
            ['name' => 'stock-transfer.' . $action]
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
    protected function transferService(): StockTransferService
    {
        return app(StockTransferService::class);
    }

    protected function receive(
        Warehouse $warehouse,
        Product $product,
        float $qty,
        ?CarbonInterface $at = null,
        ?string $expiry = null
    ): void {
        app(InventoryService::class)->receiveStock($warehouse->id, $product->id, $qty, $at ?? now(), $expiry, []);
    }

    protected function ageOld(): Carbon
    {
        return Carbon::parse('2026-09-01 08:00:00');
    }

    protected function ageNew(): Carbon
    {
        return Carbon::parse('2026-09-20 08:00:00');
    }

    /** Kho A, sản phẩm A: L1 = 10 (01/09, không HSD), L2 = 5 (20/09, HSD 01/01/2027). */
    protected function seedAgedLots(): void
    {
        $this->receive($this->whA, $this->productA, 10, $this->ageOld(), null);
        $this->receive($this->whA, $this->productA, 5, $this->ageNew(), '2027-01-01');
    }

    protected function payload(array $items = null, array $overrides = []): array
    {
        return array_merge([
            'from_warehouse_id' => $this->whA->id,
            'to_warehouse_id' => $this->whB->id,
            'transfer_date' => '2026-10-03',
            'note' => 'Chuyển test',
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

    protected function lots(Warehouse $warehouse, Product $product): Collection
    {
        return StockLot::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->orderBy('received_at')->orderBy('id')->get();
    }

    protected function totalOnHand(Product $product): float
    {
        return $this->onHand($this->whA, $product) + $this->onHand($this->whB, $product);
    }

    protected function assertStockMatchesLots(Warehouse $warehouse, Product $product): void
    {
        Assert::assertEqualsWithDelta(
            $this->onHand($warehouse, $product),
            $this->lotSum($warehouse, $product),
            0.0005,
            "Stock != SUM(lot) tại {$warehouse->code}"
        );
    }

    protected function assertLot(StockLot $lot, float $received, float $remaining, Carbon $at, ?string $expiry): void
    {
        Assert::assertEqualsWithDelta($received, (float) $lot->quantity_received, 0.0005);
        Assert::assertEqualsWithDelta($remaining, (float) $lot->quantity_remaining, 0.0005);
        Assert::assertTrue(
            Carbon::parse($lot->received_at)->equalTo($at),
            'received_at phải được giữ nguyên từ lot nguồn'
        );
        Assert::assertSame(
            $expiry,
            $lot->expiry_date === null ? null : Carbon::parse($lot->expiry_date)->toDateString(),
            'expiry_date phải được giữ nguyên từ lot nguồn'
        );
    }
}