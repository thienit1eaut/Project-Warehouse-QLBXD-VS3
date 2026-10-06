<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Permission 'stocktake.*' cho admin + manager. Idempotent. */
class StocktakePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('slug', 'stocktake')->firstOrFail();

        $permissionIds = collect([
            'view' => 'Kiểm kê - Xem',
            'create' => 'Kiểm kê - Tạo',
            'update' => 'Kiểm kê - Sửa',
            'delete' => 'Kiểm kê - Xoá',
            'post' => 'Kiểm kê - Chốt phiếu',
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