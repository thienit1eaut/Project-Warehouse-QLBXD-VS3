<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Permission 'sales-document.*' cho admin + manager. Idempotent. */
class SalesDocumentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('slug', 'sales-document')->firstOrFail();

        $permissionIds = collect([
            'view' => 'Bán hàng - Xem',
            'create' => 'Bán hàng - Tạo',
            'update' => 'Bán hàng - Sửa',
            'delete' => 'Bán hàng - Xoá',
            'post' => 'Bán hàng - Xác nhận xuất kho',
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