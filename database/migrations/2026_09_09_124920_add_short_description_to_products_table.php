<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * short_description: tóm tắt ngắn hiển thị ở danh sách/preview/SEO meta
     * description — khác với 'description' (nội dung chi tiết, có thể chứa
     * HTML từ rich text editor). Cùng khái niệm 'short_content' đã có sẵn ở
     * Category, nhưng đặt tên 'short_description' cho Product theo đúng
     * thuật ngữ người dùng yêu cầu.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('short_description');
        });
    }
};