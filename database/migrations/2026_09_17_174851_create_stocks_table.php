<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FIX (Phase A - foundation correction): migration gốc chỉ có id+timestamps,
     * thiếu toàn bộ cột mà Model Stock/StockRepository/InventoryService đang thao
     * tác (warehouse_id, product_id, quantity_on_hand) và thiếu UNIQUE constraint
     * theo đúng invariant "1 Product tại 1 Warehouse có tối đa 1 Stock record"
     * (docs/business/inventory-rules.md mục 2, docs/database/inventory-database.md
     * mục 4). FK dùng restrictOnDelete cùng convention đã áp dụng cho
     * warehouse_id/product_id ở bảng stock_movements — không cho xoá Warehouse/
     * Product đang có Stock tham chiếu.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->decimal('quantity_on_hand', 15, 3)->default(0);

            $table->timestamps();

            // Logic identity: 1 Product chỉ có tối đa 1 Stock record trong 1
            // Warehouse. Đồng thời phục vụ luôn cho query lookup/lock theo
            // warehouse_id+product_id (StockRepository::getForUpdate/find...).
            $table->unique(['warehouse_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
