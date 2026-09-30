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
     * 2. ModuleSeeder  — modules phải có trước PermissionSeeder (đã bao gồm
     *                    'warehouses'/'stock' — xem A5, Phase A foundation fix)
     * 3. PermissionSeeder
     * 4. RolePermissionSeeder — cần cả Role lẫn Permission đã tồn tại
     * 5. WarehouseStockPermissionSeeder — cần Module 'warehouses'/'stock' (bước 2)
     *    và Role admin/manager (bước 1) đã tồn tại. Tạo permission riêng cho
     *    warehouses/stock rồi gán cho admin+manager (KHÔNG gán staff). Đặt sau
     *    RolePermissionSeeder vì đây là seed bổ sung cho module mới, không phải
     *    một phần của "toàn bộ permission" mà RolePermissionSeeder gán hàng loạt.
     * 6. DemoUserSeeder — chỉ chạy ở APP_ENV=local (tự bỏ qua ở môi trường khác),
     *    cần Role đã tồn tại (bước 1). Tạo 3 user demo admin/manager/staff để
     *    test nhanh sau mỗi lần migrate:fresh --seed, không cần tạo tay qua Tinker.
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
            WarehouseStockPermissionSeeder::class,
            // DemoUserSeeder::class,
        ]);
    }
}
