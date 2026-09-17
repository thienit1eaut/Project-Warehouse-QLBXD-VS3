<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migration này làm 2 việc trong cùng 1 transaction:
     * 1. Insert 3 Role mặc định nếu chưa tồn tại (idempotent — an toàn khi
     *    chạy migrate:fresh nhiều lần).
     * 2. Backfill users.role_id từ users.role ENUM.
     *
     * Dùng Query Builder (DB::table) thay vì Eloquent Model để tránh phụ
     * thuộc vào state của Model tại thời điểm migration chạy (Model có thể
     * thay đổi trong tương lai, migration phải chạy đúng mãi mãi).
     */
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            // Insert 3 Role mặc định — idempotent qua insertOrIgnore()
            // (không throw nếu slug đã tồn tại do UNIQUE constraint)
            DB::table('roles')->insertOrIgnore([
                [
                    'name'        => 'Quản trị viên',
                    'slug'        => 'admin',
                    'description' => 'Toàn quyền hệ thống.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'name'        => 'Quản lý',
                    'slug'        => 'manager',
                    'description' => 'Quản lý nghiệp vụ kho, không có quyền xoá vĩnh viễn.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'name'        => 'Nhân viên',
                    'slug'        => 'staff',
                    'description' => 'Nhân viên vận hành — quyền được cấp theo từng nghiệp vụ.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
            ]);

            // Lấy id của 3 role vừa insert (hoặc đã tồn tại)
            $roles = DB::table('roles')
                ->whereIn('slug', ['admin', 'manager', 'staff'])
                ->pluck('id', 'slug'); // ['admin' => 1, 'manager' => 2, 'staff' => 3]

            // Backfill từng role bằng bulk UPDATE để tránh N+1
            foreach ($roles as $slug => $roleId) {
                DB::table('users')
                    ->where('role', $slug)
                    ->whereNull('role_id') // idempotent: bỏ qua user đã backfill
                    ->update(['role_id' => $roleId]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Đặt lại role_id về NULL cho toàn bộ user
            DB::table('users')->update(['role_id' => null]);

            // Xoá 3 role mặc định — chỉ xoá nếu không còn user nào trỏ vào
            // (users.role_id đã về NULL ở trên nên FK RESTRICT không chặn)
            DB::table('roles')
                ->whereIn('slug', ['admin', 'manager', 'staff'])
                ->delete();
        });
    }
};