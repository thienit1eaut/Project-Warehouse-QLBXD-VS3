<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'img',
        'short_content',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Ảnh đại diện Category — trỏ tới Media (Single Source of Truth cho
     * file + variants + url), cùng pattern Brand::media(). Tên relationship
     * "media" dù cột FK vẫn tên "img" theo kiến trúc đã chốt.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'img');
    }

    /**
     * Quan hệ tới Product — trước đây chỉ là placeholder comment vì model
     * Product chưa tồn tại. Product module đã hoàn thành (Phase 1), kích
     * hoạt quan hệ thật để CategoryRepository/Service có thể chặn xoá
     * Category đang được Product tham chiếu (FK category_id là RESTRICT ở DB).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}