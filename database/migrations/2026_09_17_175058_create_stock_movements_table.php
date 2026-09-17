<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * StockMovement = sổ cái tồn kho (immutable ledger). Mỗi thay đổi
     * quantity_on_hand ở bảng stocks PHẢI đi kèm đúng 1 record ở đây —
     * bất biến này do InventoryService đảm bảo (transaction), không phải
     * DB constraint.
     *
     * movement_type: string thường (KHÔNG dùng MySQL enum theo yêu cầu) —
     * giá trị hợp lệ Phase 1: 'in' | 'out' | 'adjustment'. Ràng buộc ở tầng
     * ứng dụng (InventoryService), không ràng buộc CHECK ở DB — đổi/thêm
     * loại movement sau này chỉ cần sửa code, không cần migration.
     *
     * reference_type/reference_id: polymorphic-style, dự phòng liên kết tới
     * PurchaseOrder/SalesOrder ở phase sau — CHƯA có bảng đó, để nullable.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->string('movement_type', 30);

            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);

            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['warehouse_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
