<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tạo 3 user demo (admin/manager/staff) để test nhanh sau mỗi lần
 * `migrate:fresh --seed`. CHỈ chạy ở môi trường local — tự bỏ qua nếu
 * APP_ENV khác 'local' (an toàn khi lỡ chạy seeder trên production/staging).
 *
 * Dùng firstOrCreate theo email — chạy lại nhiều lần không tạo trùng,
 * không reset password nếu user đã tồn tại (tránh ghi đè password thật
 * nếu ai đó lỡ đổi qua UI rồi seed lại).
 *
 * ⚠️ Password mặc định 'password' CHỈ dùng cho local dev — không dùng lại
 * giá trị này cho môi trường khác.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('DemoUserSeeder: bỏ qua vì APP_ENV không phải local.');

            return;
        }

        $roles = Role::pluck('id', 'slug');

        $demoUsers = [
            [
                'name' => 'Admin Demo',
                'email' => 'admin@example.com',
                'role_slug' => 'admin',
                'is_protected' => true,
            ],
            [
                'name' => 'Manager Demo',
                'email' => 'manager@example.com',
                'role_slug' => 'manager',
                'is_protected' => false,
            ],
            [
                'name' => 'Staff Demo',
                'email' => 'staff@example.com',
                'role_slug' => 'staff',
                'is_protected' => false,
            ],
        ];

        foreach ($demoUsers as $data) {
            $roleId = $roles[$data['role_slug']] ?? null;

            if ($roleId === null) {
                $this->command?->warn("DemoUserSeeder: role '{$data['role_slug']}' chưa tồn tại — bỏ qua user {$data['email']}.");

                continue;
            }

            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'role_id' => $roleId,
                    'is_active' => true,
                    'is_protected' => $data['is_protected'],
                ]
            );
        }
    }
}
