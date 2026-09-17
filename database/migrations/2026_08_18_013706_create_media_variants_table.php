<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_id')
                ->constrained('medias')
                ->cascadeOnDelete(); // chỉ kích hoạt khi Media bị hard-delete thật

            // thumbnail / medium / large / xlarge — theo config('media.image.variants')
            $table->string('name', 50);

            $table->string('disk', 50);
            $table->string('path', 500);

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size'); // bytes

            // Dự phòng — vd lưu quality riêng nếu sau này cho phép khác nhau theo variant
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Chặn 1 Media có 2 variant cùng tên; đồng thời phục vụ truy vấn
            // "lấy variant X của Media Y" (media_id là cột đầu, tận dụng leftmost-prefix)
            $table->unique(['media_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};