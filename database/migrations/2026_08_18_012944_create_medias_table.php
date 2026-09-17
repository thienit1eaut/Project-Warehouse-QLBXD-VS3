<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->id();

            // Public identifier — dùng khi expose qua API/Next.js/mobile sau này,
            // không dùng id tuần tự để tránh lộ số lượng record / đoán ID.
            $table->uuid('uuid')->unique();

            // Không lưu full URL — URL luôn sinh qua Storage::disk($disk)->url($path)
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);

            $table->string('original_name');
            $table->string('file_name');
            $table->string('extension', 20);
            $table->string('mime_type', 150);

            // image / video / document / audio / file — validate ở app layer,
            // KHÔNG dùng MySQL enum để dễ mở rộng loại mới không cần ALTER TABLE.
            $table->string('type', 20)->index();

            // Bytes — đơn vị đo thực tế, khác với config('media.max_size') dùng KB
            $table->unsignedBigInteger('size');

            // SHA-256 nội dung binary — phát hiện duplicate upload, KHÔNG dùng để
            // định danh (đó là vai trò của uuid). NOT NULL vì luôn tính được tại
            // thời điểm upload, không có luồng hợp lệ nào bỏ qua bước này.
            $table->char('file_hash', 64)->index();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Giây — thời lượng video/audio, NULL với image/document
            $table->unsignedInteger('duration')->nullable();

            // Metadata kỹ thuật KHÔNG ổn định/phụ thuộc loại file (orientation,
            // has_alpha, fps, codec, pages...). KHÔNG duplicate width/height/duration
            // đã có column riêng.
            $table->json('metadata')->nullable();

            // SEO / accessibility — quản lý riêng, không nhét vào metadata JSON
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // pending / processing / ready / failed — danh sách hợp lệ ở config('media.statuses'),
            // KHÔNG dùng MySQL enum để thêm trạng thái mới không cần migration.
            $table->string('status', 20)->default('ready')->index();

            // Nullable — hỗ trợ API/import/queue tạo media không gắn liền 1 user cụ thể
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Soft delete: Delete -> Trash -> Restore hoặc Permanent Delete.
            // Index vì trang Trash query WHERE deleted_at IS NOT NULL có độ chọn lọc cao.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};