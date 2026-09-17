<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * !!! QUAN TRỌNG - ĐỌC TRƯỚC KHI CHẠY !!!
 *
 * File này được viết mà KHÔNG có schema thật của bảng modules/permissions/roles/role_permission
 * (spec chỉ nói "4 bảng ... Model đã tồn tại" nhưng không cho biết tên cột chính xác).
 * Migration/Model Module, Permission, Role của bạn CÓ THỂ dùng tên cột khác (ví dụ 'code' thay vì
 * 'slug', hoặc quan hệ many-to-many đặt tên khác 'permissions()'). HÃY đối chiếu với migration/Model
 * thật rồi sửa lại các dòng có đánh dấu (*) dưới đây trước khi chạy.
 *
 * Chạy: php artisan db:seed --class=WarehouseStockPermissionSeeder
 */
class WarehouseStockPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // (*) Nếu Module dùng cột 'code' thay vì 'slug', đổi 'slug' => 'code' ở dưới.
        $warehouseModule = Module::firstOrCreate(
            ['slug' => 'warehouses'],
            ['name' => 'Kho hàng']
        );

        $stockModule = Module::firstOrCreate(
            ['slug' => 'stock'],
            ['name' => 'Tồn kho']
        );

        $warehousePermissions = collect(['view', 'create', 'update', 'delete'])
            ->map(function (string $action) use ($warehouseModule) {
                // (*) Đổi tên cột 'slug' / 'module_id' nếu Permission model dùng tên khác.
                return Permission::firstOrCreate(
                    [
                        'slug' => "warehouses.{$action}",
                        'module_id' => $warehouseModule->id,
                    ],
                    ['name' => 'Kho hàng - ' . ucfirst($action)]
                );
            });

        $stockPermissions = collect(['view'])
            ->map(function (string $action) use ($stockModule) {
                return Permission::firstOrCreate(
                    [
                        'slug' => "stock.{$action}",
                        'module_id' => $stockModule->id,
                    ],
                    ['name' => 'Tồn kho - ' . ucfirst($action)]
                );
            });

        $allPermissionIds = $warehousePermissions
            ->merge($stockPermissions)
            ->pluck('id');

        // Gán cho admin + manager, KHÔNG gán cho staff.
        foreach (['admin', 'manager'] as $roleSlug) {
            // (*) Đổi 'slug' nếu Role model dùng tên cột khác (ví dụ 'code' hoặc 'name').
            $role = Role::where('slug', $roleSlug)->first();

            if ($role === null) {
                $this->command?->warn("Role '{$roleSlug}' không tồn tại - bỏ qua gán permission.");
                continue;
            }

            // (*) Đổi 'permissions()' nếu quan hệ many-to-many trên Role model đặt tên khác.
            $role->permissions()->syncWithoutDetaching($allPermissionIds);
        }
    }
}
