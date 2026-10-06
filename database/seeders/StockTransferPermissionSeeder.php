<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Permission 'stock-transfer.*' cho admin + manager. Idempotent. */
class StockTransferPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('slug', 'stock-transfer')->firstOrFail();

        $permissionIds = collect([
            'view' => 'Chuyển kho - Xem',
            'create' => 'Chuyển kho - Tạo',
            'update' => 'Chuyển kho - Sửa',
            'delete' => 'Chuyển kho - Xoá',
            'post' => 'Chuyển kho - Xác nhận chuyển kho',
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