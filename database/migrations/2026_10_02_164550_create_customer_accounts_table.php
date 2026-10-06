<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id(); // identity ổn định của account — không dùng email làm khoá

            // unique => DB bảo vệ invariant "1 Customer có tối đa 1 account".
            // restrictOnDelete: không cascade xoá account một cách âm thầm.
            $table->foreignId('customer_id')->unique()->constrained('customers')->restrictOnDelete();

            $table->string('email')->unique();          // credential/identifier tương lai, độc lập customers.email
            $table->string('password_hash');            // chỉ lưu hash
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 20)->default('active'); // active|inactive

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts');
    }
};