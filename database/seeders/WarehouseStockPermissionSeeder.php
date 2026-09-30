<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * FIX (Phase A - foundation correction):
 * - Bản trước dùng cột 'slug' cho Permission — schema thật của bảng `permissions`
 *   là module_id + action + name (KHÔNG có cột slug, xem PermissionSeeder.php làm
 *   mẫu chuẩn). Đã sửa lại đúng cột thật.
 * - Module 'warehouses'/'stock' giờ do ModuleSeeder tạo (single source of truth
 *   cho module, cùng chỗ với category/brand/supplier/unit/media/media-folder) —
 *   seeder này chỉ fetch lại bằng where('slug', ...)->firstOrFail(), không tự
 *   tạo Module nữa để tránh duplicate logic giữa 2 seeder.
 *
 * Permission tạo ở đây:
 *   warehouses.view / warehouses.create / warehouses.update / warehouses.delete
 *   stock.view
 * Gán cho Role admin + manager (syncWithoutDetaching, idempotent). Role staff
 * KHÔNG được cấp mặc định — đúng quyết định nghiệp vụ đã chốt trước đó.
 *
 * Được gọi từ DatabaseSeeder SAU RolePermissionSeeder (không đưa logic gán
 * permission cho warehouses/stock vào RolePermissionSeeder để giữ tách biệt
 * theo đúng convention "1 seeder phụ trách 1 phần domain mới" mà project đang dùng).
 */
class WarehouseStockPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseModule = Module::where('slug', 'warehouses')->firstOrFail();
        $stockModule = Module::where('slug', 'stock')->firstOrFail();

        $warehousePermissions = collect(['view', 'create', 'update', 'delete'])
            ->map(function (string $action) use ($warehouseModule) {
                return Permission::firstOrCreate(
                    ['module_id' => $warehouseModule->id, 'action' => $action],
                    ['name' => 'Kho hàng - ' . ucfirst($action)]
                );
            });

            $stockPermissions = collect([
                'view' => 'Xem tồn kho',
                'receive' => 'Nhập kho',
                'issue' => 'Xuất kho',
                'adjust' => 'Điều chỉnh tồn kho',
            ])
                ->map(function (string $name, string $action) use ($stockModule) {
                    return Permission::firstOrCreate(
                        ['module_id' => $stockModule->id, 'action' => $action],
                        ['name' => $name]
                    );
                });

        $allPermissionIds = $warehousePermissions
            ->merge($stockPermissions)
            ->pluck('id');

        // Gán cho admin + manager, KHÔNG gán cho staff.
        foreach (['admin', 'manager'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->first();

            if ($role === null) {
                $this->command?->warn("Role '{$roleSlug}' không tồn tại - bỏ qua gán permission.");
                continue;
            }

            $role->permissions()->syncWithoutDetaching($allPermissionIds);
        }
    }
}
