<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission 'customer.view|create|update|delete' cho admin + manager. Idempotent.
 * Cũng dọn module 'customer-account' nếu bản Phase J cũ đã seed nó (không còn dùng).
 */
class CustomerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('slug', 'customer')->firstOrFail();

        $permissionIds = collect([
            'view' => 'Khách hàng - Xem',
            'create' => 'Khách hàng - Tạo',
            'update' => 'Khách hàng - Sửa',
            'delete' => 'Khách hàng - Xoá',
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

        $this->removeObsoleteCustomerAccountModule();
    }

    private function removeObsoleteCustomerAccountModule(): void
    {
        $obsolete = Module::where('slug', 'customer-account')->first();

        if ($obsolete === null) {
            return;
        }

        $ids = Permission::where('module_id', $obsolete->id)->pluck('id');

        Role::query()->get()->each(fn (Role $role) => $role->permissions()->detach($ids));

        Permission::whereIn('id', $ids)->delete();
        $obsolete->delete();
    }
}