<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission cho module 'purchase-receipt' (module do ModuleSeeder tạo).
 * Gán cho admin + manager, KHÔNG gán staff (cùng quyết định với warehouses/stock).
 * Idempotent: chạy lại nhiều lần an toàn.
 */
class PurchaseReceiptPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('slug', 'purchase-receipt')->firstOrFail();

        $permissionIds = collect([
            'view' => 'Phiếu nhập kho - Xem',
            'create' => 'Phiếu nhập kho - Tạo',
            'post' => 'Phiếu nhập kho - Xác nhận nhập kho',
        ])->map(fn (string $name, string $action) => Permission::firstOrCreate(
            ['module_id' => $module->id, 'action' => $action],
            ['name' => $name]
        ))->pluck('id');

        foreach (['admin', 'manager'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->first();

            if ($role === null) {
                $this->command?->warn("Role '{$roleSlug}' không tồn tại - bỏ qua.");
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}