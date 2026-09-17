<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();

            // Self-reference cho cây phân cấp (adjacency list). NULL = root folder.
            // RESTRICT (không SET NULL): lớp bảo vệ DB cuối cùng — Service phải chặn
            // Permanent Delete khi còn folder con (descendants), FK RESTRICT đảm bảo
            // ngay cả khi có thao tác bypass Service, DB vẫn từ chối thay vì âm thầm
            // "phẳng hoá" cây (SET NULL sẽ làm folder con nhảy lên thành root).
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('media_folders')
                ->restrictOnDelete();

            // Logical name only — KHÔNG dùng để build physical storage path,
            // KHÔNG có slug (không có route public/SEO cho Folder).
            // Validate whitelist ký tự + reject path separator ở FormRequest (STEP sau),
            // không phải ở DB. VARCHAR(255) đủ dư so với giới hạn 100 ký tự ở validation.
            $table->string('name');

            $table->timestamps();

            // Soft delete bắt buộc: Delete -> Trash -> Restore / Permanent Delete.
            // KHÔNG unique(parent_id, name) ở DB — MySQL coi NULL không so sánh được
            // với chính nó nên composite unique không chặn được 2 root folder trùng
            // tên; đồng thời soft-delete sẽ làm unique giữ "chỗ" của tên đã bị trash.
            // Duplicate-name check chỉ xét folder active (deleted_at IS NULL), xử lý
            // ở MediaFolderService — Eloquent SoftDeletes tự loại trừ trashed record
            // khỏi query mặc định, không cần viết thêm điều kiện.
            $table->softDeletes();
            $table->index('deleted_at');

            // Không cần thêm $table->index('parent_id') riêng: InnoDB tự động tạo
            // index hỗ trợ cho cột FK khi thêm ràng buộc foreign key (constrained()
            // ở trên đã kích hoạt việc này) — thêm lần nữa chỉ tạo 2 index cùng phủ
            // 1 cột một cách dư thừa, không sai nhưng lãng phí dung lượng/ghi.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};