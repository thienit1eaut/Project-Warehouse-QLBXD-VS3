<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // RESTRICT: không cho phép xoá Module còn Permission
            $table->foreignId('module_id')
                ->constrained('modules')
                ->restrictOnDelete();

            // action là string cố định do developer định nghĩa
            // (view, create, update, delete, restore, force-delete, manage-folder)
            $table->string('action', 100);

            $table->string('name', 200);
            $table->timestamps();

            // 1 module không thể có 2 action trùng nhau
            $table->unique(['module_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};