<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Danh mục',      'slug' => 'category',     'description' => 'Quản lý danh mục sản phẩm.'],
            ['name' => 'Thương hiệu',   'slug' => 'brand',        'description' => 'Quản lý thương hiệu sản phẩm.'],
            ['name' => 'Nhà cung cấp',  'slug' => 'supplier',     'description' => 'Quản lý nhà cung cấp.'],
            ['name' => 'Đơn vị tính',   'slug' => 'unit',         'description' => 'Quản lý đơn vị tính sản phẩm.'],
            ['name' => 'Media',         'slug' => 'media',        'description' => 'Quản lý file media (ảnh, video, tài liệu).'],
            ['name' => 'Media Folder',  'slug' => 'media-folder', 'description' => 'Quản lý thư mục chứa media.'],
        ];
 
        foreach ($modules as $data) {
            Module::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
