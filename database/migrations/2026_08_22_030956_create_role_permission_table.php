<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            // CASCADE: xoá Role → tự xoá pivot
            // (Role có User bị chặn ở FK users.role_id RESTRICT trước nên
            // CASCADE này chỉ là safety net khi Role không còn User)
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            // CASCADE: xoá Permission → tự xoá khỏi pivot
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};