<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property string                                          $uuid
 * @property string                                          $original_name
 * @property string                                          $file_name
 * @property string                                          $extension
 * @property string                                          $mime_type
 * @property string                                          $type
 * @property int                                             $size
 * @property int|null                                        $width
 * @property int|null                                        $height
 * @property int|null                                        $duration
 * @property array|null                                      $metadata
 * @property string|null                                     $alt
 * @property string|null                                     $title
 * @property string|null                                     $description
 * @property string                                          $status
 * @property-read string                                          $url         Runtime accessor — không lưu DB
 * @property \Illuminate\Database\Eloquent\Collection        $variants
 * @property \Illuminate\Database\Eloquent\Collection        $folders
 * @property \Illuminate\Support\Carbon|null                 $created_at
 * @property \Illuminate\Support\Carbon|null                 $updated_at
 * @property \Illuminate\Support\Carbon|null                 $deleted_at
 */
class Media extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Khai báo tường minh — Laravel tự đoán tên bảng là "media" (coi "media" là
     * số nhiều bất quy tắc của "medium", không tự thêm "s"), sai với tên bảng
     * thật "medias" đã tạo ở migration. Đây là gotcha kinh điển của Eloquent với
     * từ tiếng Anh có số nhiều bất quy tắc — bắt buộc phải khai báo $table.
     */
    protected $table = 'medias';

    /**
     * Toàn bộ cột mà MediaService sẽ set tường minh khi tạo/cập nhật record —
     * cùng nguyên tắc mass-assignment với Category/Brand/Supplier/Unit (Repository
     * luôn truyền $data đầy đủ, không có input nào đi thẳng từ Controller vào đây).
     */
    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'original_name',
        'file_name',
        'extension',
        'mime_type',
        'type',
        'size',
        'file_hash',
        'width',
        'height',
        'duration',
        'metadata',
        'alt',
        'title',
        'description',
        'status',
        'created_by',
    ];

    /**
     * Runtime accessor — không lưu DB, không thêm cột. Frontend cần URL để
     * hiển thị <img>, tính lại từ disk+path mỗi lần truy cập (Phase 7 B1).
     * $appends đảm bảo 'url' luôn có mặt khi Model serialize sang JSON/array,
     * kể cả khi Controller không gọi ->makeVisible() thủ công.
     */
    protected $appends = ['url'];

    /**
     * Ẩn khỏi MỌI serialization (Inertia paginator ở index()/trash() dùng
     * default toArray()/jsonSerialize(), không đi qua transform thủ công
     * như show()/edit() — nên phải chặn ở tầng Model để không lộ ra ngoài
     * bất kỳ endpoint nào, kể cả những chỗ chưa từng dùng transform helper).
     * file_hash: dữ liệu nội bộ (SHA-256 dedup), không có ý nghĩa với UI.
     * disk/path: chi tiết filesystem nội bộ — 'url' đã đủ cho frontend.
     * created_by: expose qua relationship createdBy() nếu cần, không cần
     * lộ FK integer thô trên danh sách UI.
     */
    protected $hidden = ['file_hash', 'disk', 'path', 'created_by'];

    protected function casts(): array
    {
        return [
            'uuid' => 'string', // luôn ép về string ngay sau create(), tránh strict-comparison
                                 // sai vì Str::uuid() trả về object Ramsey\Uuid trước khi cast
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'metadata' => 'array', // JSON column <-> PHP array, Laravel tự encode/decode
        ];
    }

    /**
     * URL public của file gốc — dùng Storage::disk($this->disk)->url($this->path)
     * đúng convention Laravel Filesystem, không hard-code path pattern.
     * Không lazy — tính ngay mỗi lần access vì chi phí thấp (chỉ string concat,
     * không I/O), Laravel tự cache trong request qua Attribute nếu cần.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk($this->disk)->url($this->path),
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    /**
     * Toàn bộ Folder mà Media này đang thuộc về (many-to-many qua pivot).
     * Pivot chỉ có created_at (không có updated_at) — KHÔNG dùng withTimestamps()
     * vì method đó giả định cả 2 cột tồn tại, sẽ lỗi khi Eloquent cố ghi updated_at.
     */
    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaFolder::class,
            'media_folder_items',
            'media_id',
            'folder_id'
        )->withPivot('created_at');
    }

    /**
     * Truy cập trực tiếp từng dòng pivot (khi cần thao tác ở mức quan hệ,
     * không chỉ đọc Folder liên quan) — Repository/Service Phase 3-4 sẽ dùng
     * cái này cho add/remove/move thay vì luôn qua belongsToMany.
     */
    public function folderItems(): HasMany
    {
        return $this->hasMany(MediaFolderItem::class);
    }
}