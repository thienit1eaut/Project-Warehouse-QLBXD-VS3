<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stocktake_id')->constrained('stocktakes')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Snapshot tồn hệ thống tại thời điểm lưu phiếu (KHÔNG đọc động từ Stock).
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('actual_quantity', 15, 3);
            // difference = actual_quantity - system_quantity (có dấu)
            $table->decimal('difference', 15, 3);

            $table->timestamps();

            // Mỗi Product chỉ xuất hiện 1 lần trong 1 phiếu kiểm kê.
            $table->unique(['stocktake_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
    }
};