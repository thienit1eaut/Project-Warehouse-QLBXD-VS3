<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_document_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_document_id')->constrained('sales_documents')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 15, 3);
            // Snapshot giá bán tại thời điểm tạo dòng. KHÔNG đọc lại Product.selling_price khi POST.
            $table->decimal('unit_price', 15, 2);

            $table->timestamps();

            $table->index('sales_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_items');
    }
};