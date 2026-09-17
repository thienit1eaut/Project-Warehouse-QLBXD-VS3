<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */

    /**
     * Thứ tự quan trọng:
     * 1. RoleSeeder    — roles phải có trước khi migration 006 backfill users.role_id
     *                    (migration 006 tự insert nếu chưa có, nhưng Seeder này
     *                    đảm bảo data đồng nhất khi chạy migrate:fresh --seed)
     * 2. ModuleSeeder  — modules phải có trước PermissionSeeder
     * 3. PermissionSeeder
     * 4. RolePermissionSeeder — cần cả Role lẫn Permission đã tồn tại
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
