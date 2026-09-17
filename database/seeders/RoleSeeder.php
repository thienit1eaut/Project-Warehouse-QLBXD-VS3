<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'        => 'Quản trị viên',
                'slug'        => 'admin',
                'description' => 'Toàn quyền hệ thống.',
            ],
            [
                'name'        => 'Quản lý',
                'slug'        => 'manager',
                'description' => 'Quản lý nghiệp vụ kho, không có quyền xoá vĩnh viễn.',
            ],
            [
                'name'        => 'Nhân viên',
                'slug'        => 'staff',
                'description' => 'Nhân viên vận hành — quyền được cấp theo từng nghiệp vụ.',
            ],
        ];
 
        foreach ($roles as $data) {
            Role::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
