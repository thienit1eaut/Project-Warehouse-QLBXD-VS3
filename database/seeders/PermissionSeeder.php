<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Tổng: 29 permissions
     * category(4) + brand(4) + supplier(4) + unit(4) + media(7) + media-folder(6)
     *
     * Thêm permission mới: thêm vào đây + implement action trong code.
     * Không tự thêm permission cho action chưa tồn tại trong code.
     */
    public function run(): void
    {
        $definitions = [
            'category' => [
                ['action' => 'view',   'name' => 'Xem danh mục'],
                ['action' => 'create', 'name' => 'Tạo danh mục'],
                ['action' => 'update', 'name' => 'Cập nhật danh mục'],
                ['action' => 'delete', 'name' => 'Xoá danh mục'],
            ],
            'brand' => [
                ['action' => 'view',   'name' => 'Xem thương hiệu'],
                ['action' => 'create', 'name' => 'Tạo thương hiệu'],
                ['action' => 'update', 'name' => 'Cập nhật thương hiệu'],
                ['action' => 'delete', 'name' => 'Xoá thương hiệu'],
            ],
            'supplier' => [
                ['action' => 'view',   'name' => 'Xem nhà cung cấp'],
                ['action' => 'create', 'name' => 'Tạo nhà cung cấp'],
                ['action' => 'update', 'name' => 'Cập nhật nhà cung cấp'],
                ['action' => 'delete', 'name' => 'Xoá nhà cung cấp'],
            ],
            'unit' => [
                ['action' => 'view',   'name' => 'Xem đơn vị tính'],
                ['action' => 'create', 'name' => 'Tạo đơn vị tính'],
                ['action' => 'update', 'name' => 'Cập nhật đơn vị tính'],
                ['action' => 'delete', 'name' => 'Xoá đơn vị tính'],
            ],
            'media' => [
                ['action' => 'view',          'name' => 'Xem Media'],
                ['action' => 'create',        'name' => 'Upload Media'],
                ['action' => 'update',        'name' => 'Cập nhật Media'],
                ['action' => 'manage-folder', 'name' => 'Quản lý Media trong Folder'],
                ['action' => 'delete',        'name' => 'Xoá Media'],
                ['action' => 'restore',       'name' => 'Khôi phục Media'],
                ['action' => 'force-delete',  'name' => 'Xoá vĩnh viễn Media'],
            ],
            'media-folder' => [
                ['action' => 'view',         'name' => 'Xem Folder'],
                ['action' => 'create',       'name' => 'Tạo Folder'],
                ['action' => 'update',       'name' => 'Đổi tên / Di chuyển Folder'],
                ['action' => 'delete',       'name' => 'Xoá Folder'],
                ['action' => 'restore',      'name' => 'Khôi phục Folder'],
                ['action' => 'force-delete', 'name' => 'Xoá vĩnh viễn Folder'],
            ],
        ];
 
        foreach ($definitions as $moduleSlug => $actions) {
            $module = Module::where('slug', $moduleSlug)->firstOrFail();
 
            foreach ($actions as $permData) {
                Permission::firstOrCreate(
                    ['module_id' => $module->id, 'action' => $permData['action']],
                    ['name' => $permData['name']]
                );
            }
        }
    }
}
