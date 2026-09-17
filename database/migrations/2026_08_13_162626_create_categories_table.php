<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Self-reference cho category cha/con. nullOnDelete: xoá category cha
            // thì category con không bị xoá theo mà chỉ mất liên kết (business rule
            // ở Service đã chặn xoá category có con từ trước, cột này là lớp bảo vệ DB).
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
 
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('img')->nullable();
            $table->text('short_content')->nullable();
            $table->text('description')->nullable();
 
            // Dùng is_active (boolean) thay vì status (enum) để nhất quán với bảng users
            $table->boolean('is_active')->default(true);
 
            $table->timestamps();
 
            $table->index(['parent_id', 'is_active']);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
