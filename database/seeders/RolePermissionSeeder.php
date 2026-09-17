<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Default permission mapping đã chốt:
     *
     * admin   → tất cả 29 permissions
     * manager → tất cả trừ media.force-delete và media-folder.force-delete (27 permissions)
     * staff   → không có permission Admin module mặc định (0 permissions)
     *
     * Đây là initial data — admin có thể thay đổi qua UI sau này.
     * syncWithoutDetaching() để idempotent khi chạy lại seeder.
     */
    public function run(): void
    {
        $allPermissions = Permission::all();
 
        // Admin: tất cả 29 permissions
        $admin = Role::where('slug', 'admin')->firstOrFail();
        $admin->permissions()->syncWithoutDetaching($allPermissions->pluck('id'));
 
        // Manager: tất cả trừ force-delete của media và media-folder
        $manager = Role::where('slug', 'manager')->firstOrFail();
        $managerPermissions = $allPermissions->reject(function ($perm) {
            return $perm->action === 'force-delete'
                && in_array($perm->module->slug, ['media', 'media-folder'], true);
        });
        $manager->permissions()->syncWithoutDetaching($managerPermissions->pluck('id'));
 
        // Staff: không có permission mặc định
        // (không cần sync — bảng role_permission không có record nào cho staff)
        // Sau này cấp qua UI khi có module nghiệp vụ Staff cần dùng.
    }
}
