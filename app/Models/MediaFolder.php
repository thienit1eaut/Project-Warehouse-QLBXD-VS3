<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFolder extends Model
{
    use HasFactory, SoftDeletes;

    // Chỉ 2 cột — đúng schema tối giản đã chốt: không uuid, không slug,
    // không description, không sort_order, không created_by.
    protected $fillable = [
        'parent_id',
        'name',
    ];

    // Không khai báo relationship/method nào liên quan Storage path —
    // Folder không ảnh hưởng vị trí lưu file vật lý, đúng nguyên tắc II đã chốt.

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id');
    }

    /**
     * Toàn bộ Media đang thuộc Folder này (many-to-many qua pivot).
     * Mặc định query CHỈ trả Media active (SoftDeletes global scope của Media
     * tự loại trừ trashed) — khi cần xem Media đã trash bên trong 1 Folder,
     * Service/Repository phải chủ động gọi withTrashed() ở phía Media, không
     * override behavior mặc định ở đây (đúng mục 12 đã chốt).
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            Media::class,
            'media_folder_items',
            'folder_id',
            'media_id'
        )->withPivot('created_at');
    }

    public function folderItems(): HasMany
    {
        return $this->hasMany(MediaFolderItem::class, 'folder_id');
    }
}