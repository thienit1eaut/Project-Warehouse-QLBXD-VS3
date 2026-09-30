<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * StockAllocation: ghi nhận 1 OUT movement đã lấy hàng từ những StockLot
     * nào, bao nhiêu. KHÔNG lưu warehouse_id/product_id (truy ra qua
     * stock_movement -> stock hoặc stock_lot -> stock, tránh duplicate data).
     * FK restrictOnDelete cùng convention stocks/stock_movements/stock_lots
     * — không cho xoá StockMovement/StockLot đang có Allocation tham chiếu.
     */
    public function up(): void
    {
        Schema::create('stock_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->restrictOnDelete();

            $table->foreignId('stock_lot_id')
                ->constrained('stock_lots')
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3);

            $table->timestamps();

            // 1 movement + 1 lot chỉ có đúng 1 dòng allocation (tránh duplicate
            // nếu code gọi nhầm 2 lần). Composite này cũng phục vụ luôn query
            // theo stock_movement_id (leading column).
            $table->unique(['stock_movement_id', 'stock_lot_id']);

            // Query ngược theo lot (StockLot::allocations()) cần index riêng vì
            // stock_lot_id không phải leading column của unique composite trên.
            $table->index('stock_lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_allocations');
    }
};