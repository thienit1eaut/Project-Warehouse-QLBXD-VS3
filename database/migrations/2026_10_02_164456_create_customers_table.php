<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable()->unique();   // nullable: nhiều NULL vẫn hợp lệ
            $table->string('email')->nullable()->unique();
            $table->string('customer_type', 20)->default('individual'); // individual|business|other
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('customer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};