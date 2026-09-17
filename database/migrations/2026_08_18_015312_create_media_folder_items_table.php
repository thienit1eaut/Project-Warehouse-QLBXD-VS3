<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folder_items', function (Blueprint $table) {
            $table->id();

            // CASCADE: khi Media bị hard-delete thật (Permanent Delete), quan hệ
            // Folder của nó không còn ý nghĩa — dọn tự động, không cần Service tự
            // xoá từng pivot row trước khi forceDelete Media.
            $table->foreignId('media_id')
                ->constrained('medias')
                ->cascadeOnDelete();

            // CASCADE: đúng yêu cầu Permanent Delete Folder phải xoá quan hệ
            // Folder <-> Media (nhưng KHÔNG xoá Media — CASCADE ở đây chỉ chảy
            // vào chính bảng pivot, không có FK nào từ pivot trỏ ngược ra ngoài).
            $table->foreignId('folder_id')
                ->constrained('media_folders')
                ->cascadeOnDelete();

            // Chỉ cần biết "thêm vào Folder lúc nào" — không có khái niệm "sửa"
            // quan hệ (chỉ Add hoặc Remove), nên KHÔNG dùng timestamps() (sẽ tạo
            // cả updated_at không cần thiết).
            $table->timestamp('created_at')->nullable();

            // Chặn 1 Media có 2 quan hệ trùng với cùng 1 Folder. Đồng thời cột đầu
            // (media_id) phục vụ truy vấn "Folder nào đang chứa Media X" tận dụng
            // leftmost-prefix của composite index này.
            $table->unique(['media_id', 'folder_id']);

            // Không cần thêm index('folder_id') riêng: InnoDB tự động tạo index hỗ
            // trợ cho cột FK khi thêm ràng buộc foreign key (kể cả với folder_id dù
            // nó là cột thứ 2 trong composite unique ở trên, không tận dụng được
            // leftmost-prefix cho riêng nó) — MySQL tự đảm bảo, không cần khai báo
            // thêm, tránh 2 index cùng phủ 1 cột một cách dư thừa.
            // Query "lấy toàn bộ Media trong Folder X" (foreign_id = ?) vẫn nhanh
            // nhờ index tự động này.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folder_items');
    }
};