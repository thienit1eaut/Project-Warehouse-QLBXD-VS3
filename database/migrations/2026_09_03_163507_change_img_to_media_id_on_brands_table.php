<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đổi brands.img từ string (path logo cũ, tính năng chưa từng hoạt động
     * thật — toàn bộ logic upload trong BrandService đã bị comment out từ
     * trước) sang unsignedBigInteger FK trỏ tới medias.id.
     *
     * Giữ nguyên TÊN CỘT "img" theo đúng yêu cầu kiến trúc đã chốt — chỉ đổi
     * kiểu dữ liệu và ý nghĩa (giờ lưu media_id thay vì path).
     *
     * nullOnDelete(): nếu Media bị force-delete trong khi đang được Brand
     * tham chiếu, Brand.img tự về NULL thay vì chặn việc xoá Media (Media
     * module không biết và không cần biết về Brand — quan hệ 1 chiều).
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('img');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('img')
                ->nullable()
                ->after('description')
                ->constrained('medias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['img']);
            $table->dropColumn('img');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('img')->nullable()->after('description');
        });
    }
};