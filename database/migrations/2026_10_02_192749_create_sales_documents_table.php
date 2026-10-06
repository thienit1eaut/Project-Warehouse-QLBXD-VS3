<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_code', 50)->unique();

            // Nullable = khách vãng lai. restrictOnDelete (theo spec) để bảo vệ lịch sử chứng từ.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->string('status', 20)->default('draft'); // draft | posted
            $table->date('document_date');
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_documents');
    }
};