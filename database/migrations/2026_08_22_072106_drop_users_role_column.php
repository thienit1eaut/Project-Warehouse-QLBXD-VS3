<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chạy migration này SAU KHI:
     * 1. role_id đã backfill đầy đủ (migration 006).
     * 2. role_id đã NOT NULL (migration 007).
     * 3. Toàn bộ code không còn đọc/ghi cột users.role (đã verify thủ công).
     *
     * down() khôi phục lại cột role ENUM và backfill ngược từ roles.slug
     * để rollback an toàn trong quá trình development.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm lại cột role ENUM để rollback về trạng thái ban đầu
            $table->enum('role', ['admin', 'manager', 'staff'])
                ->default('staff')
                ->after('email')
                ->nullable(); // nullable tạm để backfill bên dưới
        });

        // Backfill ngược: đọc slug của Role từ role_id
        \Illuminate\Support\Facades\DB::statement('
            UPDATE users u
            JOIN roles r ON r.id = u.role_id
            SET u.role = r.slug
        ');

        // Đổi lại về NOT NULL sau khi đã backfill
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'staff'])
                ->default('staff')
                ->nullable(false)
                ->change();
        });
    }
};