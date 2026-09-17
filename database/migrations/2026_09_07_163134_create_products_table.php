<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Phase 1 — Master Data. Không có quantity/stock/warehouse_id —
     * Inventory sẽ là module riêng sau này, đọc Product qua product_id.
     *
     * FK behavior:
     * - category_id, unit_id (NOT NULL): restrictOnDelete — không cho xoá
     *   Category/Unit đang được Product tham chiếu (Service sẽ không tự
     *   check trước vì đây là ràng buộc cứng bắt buộc, khác hasChildren()
     *   là business rule mềm ở CategoryService).
     * - brand_id, supplier_id, img (nullable): nullOnDelete — cùng pattern
     *   đã dùng cho brands.img/categories.img, xoá Brand/Supplier/Media
     *   không chặn, Product chỉ mất liên kết.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->foreignId('img')
                ->nullable()
                ->constrained('medias')
                ->nullOnDelete();

            $table->text('short_content')->nullable();
            $table->text('description')->nullable();
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};