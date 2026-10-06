<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('stocktake_code', 50)->unique();

            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->string('status', 20)->default('draft'); // draft | posted
            $table->date('stocktake_date');
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'stocktake_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktakes');
    }
};