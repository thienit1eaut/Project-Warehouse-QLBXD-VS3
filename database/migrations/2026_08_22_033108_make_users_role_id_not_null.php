<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: kiểm tra còn User nào chưa được backfill không.
        // Nếu còn thì fail migration với thông báo rõ ràng thay vì để
        // MySQL throw lỗi FK/NOT NULL mơ hồ.
        $nullCount = DB::table('users')->whereNull('role_id')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Migration thất bại: còn {$nullCount} user(s) có role_id = NULL. "
                . 'Kiểm tra migration 000006 đã chạy thành công và cột users.role '
                . 'chứa đúng giá trị admin/manager/staff cho tất cả user.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            // change() — yêu cầu doctrine/dbal hoặc Laravel native column modifier
            // constrained() không cần khai báo lại vì FK đã tồn tại ở migration 5
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->change();
        });
    }
};