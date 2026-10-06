<?php

namespace Tests\Feature\Monitoring;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

trait MonitoringFixtures
{
    protected Warehouse $whA;
    protected Warehouse $whB;
    protected Warehouse $whInactive;
    protected Category $catRoad;
    protected Category $catPart;
    protected Brand $brandX;
    protected Brand $brandY;
    protected Unit $unit;
    protected User $viewer;
    protected User $noAccess;
    protected User $manager;

    protected function createMonitoringFixtures(): void
    {
        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);
        $this->catRoad = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $this->catPart = Category::create(['name' => 'Phụ tùng', 'slug' => 'phu-tung', 'is_active' => true]);
        $this->brandX = Brand::create(['name' => 'Giant', 'slug' => 'giant', 'is_active' => true]);
        $this->brandY = Brand::create(['name' => 'Shimano', 'slug' => 'shimano', 'is_active' => true]);

        $this->whA = Warehouse::create(['code' => 'WH-A', 'name' => 'Kho A']);
        $this->whB = Warehouse::create(['code' => 'WH-B', 'name' => 'Kho B']);
        $this->whInactive = Warehouse::create(['code' => 'WH-X', 'name' => 'Kho ngừng']);
        $this->whInactive->forceFill(['is_active' => false])->save();

        $module = Module::firstOrCreate(['slug' => 'stock'], ['name' => 'Tồn kho']);
        $permission = Permission::firstOrCreate(
            ['module_id' => $module->id, 'action' => 'view'],
            ['name' => 'stock.view']
        );

        $viewerRole = Role::create(['name' => 'mon-viewer', 'slug' => 'mon-viewer']);
        $viewerRole->permissions()->attach([$permission->id]);
        $this->viewer = $this->makeUser('viewer@example.com', $viewerRole);

        $this->noAccess = $this->makeUser('none@example.com', Role::create(['name' => 'mon-none', 'slug' => 'mon-none']));

        // Route Product dùng middleware 'manager' (role slug admin/manager). Role này đã được seed sẵn
        // bởi migration, nên dùng firstOrCreate thay vì create để không trùng slug.
        $this->manager = $this->makeUser(
            'manager@example.com',
            Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager'])
        );
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

    protected function makeProduct(string $sku, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'sku' => $sku,
            'name' => 'Sản phẩm ' . $sku,
            'slug' => strtolower($sku),
            'category_id' => $this->catRoad->id,
            'brand_id' => $this->brandX->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 100000,
            'minimum_stock' => 0,
            'is_active' => true,
        ], $overrides));
    }

    /** Nhập tồn thật qua InventoryService (không ghi thẳng bảng). */
    protected function receive(Warehouse $warehouse, Product $product, float $qty): void
    {
        app(InventoryService::class)->receiveStock($warehouse->id, $product->id, $qty, now(), null, []);
    }

    /** Tạo dòng Stock với tồn = 0 (nhập rồi xuất hết). */
    protected function zeroStock(Warehouse $warehouse, Product $product): void
    {
        $this->receive($warehouse, $product, 1);
        app(InventoryService::class)->issueStock($warehouse->id, $product->id, 1);
    }

    protected function countsSnapshot(): array
    {
        return [
            'stocks' => Stock::count(),
            'lots' => StockLot::count(),
            'movements' => StockMovement::count(),
            'quantity' => (float) Stock::sum('quantity_on_hand'),
        ];
    }

    /** Props của trang Inertia, đọc trực tiếp từ response (không phụ thuộc cấu trúc AssertableInertia). */
    protected function inertiaProps(\Illuminate\Testing\TestResponse $response): array
    {
        return json_decode(json_encode($response->viewData('page')), true)['props'];
    }

    /** @return array{data: array[], total: int} props.rows của trang Overview */
    protected function overview(array $query = [], ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->viewer)
            ->get('/admin/stock' . ($query ? '?' . http_build_query($query) : ''));

        $response->assertOk();

        return $this->inertiaProps($response)['rows'];
    }

    protected function dashboardInventory(?User $user = null): ?array
    {
        $response = $this->actingAs($user ?? $this->viewer)->get('/admin/dashboard');

        $response->assertOk();

        return $this->inertiaProps($response)['inventory'];
    }

    /** Tìm dòng theo SKU (+ mã kho nếu có) trong kết quả Overview. */
    protected function findRow(array $rows, string $sku, ?string $warehouseCode = null): ?array
    {
        foreach ($rows['data'] as $row) {
            if ($row['sku'] === $sku && ($warehouseCode === null || ($row['warehouse']['code'] ?? null) === $warehouseCode)) {
                return $row;
            }
        }

        return null;
    }
}