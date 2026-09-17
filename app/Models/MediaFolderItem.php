<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFolderItem extends Model
{
    use HasFactory;

    // Không SoftDeletes — Remove from Folder = DELETE thật, không có khái niệm
    // Trash cho 1 quan hệ (đã chốt ở mục XI/9 của yêu cầu gốc).

    /**
     * Bảng chỉ có created_at, KHÔNG có updated_at (quan hệ Media<->Folder không
     * có khái niệm "sửa", chỉ Add hoặc Remove). Khai báo UPDATED_AT = null để
     * Eloquent tự set created_at khi tạo record như bình thường, nhưng KHÔNG
     * bao giờ cố ghi/đọc cột updated_at (tránh lỗi "Unknown column") — đây là
     * cách Laravel hỗ trợ chính thức cho bảng chỉ có 1 trong 2 cột timestamp,
     * sạch hơn việc tắt hẳn $timestamps rồi tự set created_at thủ công.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'media_id',
        'folder_id',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }
}