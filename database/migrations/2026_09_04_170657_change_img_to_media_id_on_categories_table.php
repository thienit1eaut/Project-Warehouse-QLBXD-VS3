<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đổi categories.img từ text (chưa từng được dùng — không có Request
     * rule, không có Controller/Service logic nào đụng tới field này từ
     * trước) sang unsignedBigInteger FK trỏ tới medias.id.
     *
     * Cùng pattern đã áp dụng cho brands.img: giữ nguyên TÊN CỘT "img",
     * chỉ đổi kiểu dữ liệu + ý nghĩa. nullOnDelete() cùng lý do: Category
     * module không cần biết/không chặn việc xoá Media ở module khác.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('img');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('img')
                ->nullable()
                ->after('slug')
                ->constrained('medias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['img']);
            $table->dropColumn('img');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->text('img')->nullable()->after('slug');
        });
    }
};