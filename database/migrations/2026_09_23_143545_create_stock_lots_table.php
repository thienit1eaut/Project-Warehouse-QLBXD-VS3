<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * StockLot = số lượng còn lại phát sinh từ MỘT lần nhập kho (receive).
     * Mỗi lần nhập tạo 1 StockLot mới — KHÔNG gộp vào lot cũ (xem
     * docs/business/inventory-rules.md, docs/business/fifo-rules.md).
     *
     * Phase B (foundation only): chỉ tạo schema. InventoryService CHƯA được
     * sửa để tạo/tiêu thụ StockLot — đó là Phase C (Receive) và Phase D/E
     * (FIFO issue + StockAllocation).
     *
     * FK dùng restrictOnDelete cho cả 3 (stock_id/warehouse_id/product_id) —
     * cùng convention đã áp dụng cho warehouse_id/product_id ở bảng
     * `stocks`/`stock_movements`: không cho xoá Warehouse/Product/Stock đang
     * có StockLot tham chiếu, tránh mất lịch sử inventory một cách âm thầm.
     *
     * quantity_received/quantity_remaining dùng decimal(15,3) — nhất quán với
     * Stock.quantity_on_hand và StockMovement.quantity/before/after, để
     * invariant SUM(quantity_remaining) = Stock.quantity_on_hand không bị lệch
     * làm tròn do khác precision.
     */
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_id')
                ->constrained('stocks')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_remaining', 15, 3);

            $table->timestamp('received_at');
            $table->date('expiry_date')->nullable();

            $table->timestamps();

            // Phục vụ FIFO: "còn lot khả dụng nào cho warehouse+product này?"
            $table->index(
                ['warehouse_id', 'product_id', 'quantity_remaining'],
                'stock_lots_availability_index'
            );

            // Phục vụ thứ tự FIFO: ORDER BY received_at ASC, id ASC
            // (id là tie-breaker deterministic khi received_at bằng nhau).
            $table->index(
                ['warehouse_id', 'product_id', 'received_at', 'id'],
                'stock_lots_fifo_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
