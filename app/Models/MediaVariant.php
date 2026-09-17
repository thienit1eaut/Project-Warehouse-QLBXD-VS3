<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int                         $media_id
 * @property string                      $name      thumbnail | medium | large | xlarge
 * @property string                      $disk
 * @property string                      $path
 * @property int|null                    $width
 * @property int|null                    $height
 * @property string                      $mime_type
 * @property int                         $size
 * @property array|null                  $metadata
 * @property string                      $url       Runtime accessor — không lưu DB
 * @property \Illuminate\Support\Carbon  $created_at
 * @property \Illuminate\Support\Carbon  $updated_at
 */
class MediaVariant extends Model
{
    use HasFactory;

    // Không SoftDeletes — đúng schema đã chốt, media_variants không có deleted_at.
    // Variant bị xoá cứng tự động qua FK CASCADE khi Media gốc bị forceDelete.

    protected $fillable = [
        'media_id',
        'name',
        'disk',
        'path',
        'width',
        'height',
        'mime_type',
        'size',
        'metadata',
    ];

    /**
     * Runtime accessor — cùng nguyên tắc với Media::url() (Phase 7 B2).
     * Không lưu DB, tính lại từ disk+path mỗi lần truy cập.
     */
    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk($this->disk)->url($this->path),
        );
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    // Không tạo relationship với MediaFolder — Variant không thuộc Folder,
    // đúng nguyên tắc đã chốt: Folder chỉ tổ chức Media, không đụng Variant.
}