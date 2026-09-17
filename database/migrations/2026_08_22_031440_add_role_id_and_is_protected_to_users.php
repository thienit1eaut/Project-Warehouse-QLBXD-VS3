<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable ở bước này — backfill ở migration 6,
            // đổi NOT NULL ở migration 7
            // RESTRICT: không cho phép xoá Role còn User
            $table->foreignId('role_id')
                ->nullable()
                ->after('role')
                ->constrained('roles')
                ->restrictOnDelete();

            // Bảo vệ lifecycle tài khoản Developer Admin.
            // Không bypass Permission, không thay thế Role.
            // Protected User không được delete/deactivate/change role.
            $table->boolean('is_protected')
                ->default(false)
                ->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Phải drop FK trước khi drop column trên MySQL
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'is_protected']);
        });
    }
};