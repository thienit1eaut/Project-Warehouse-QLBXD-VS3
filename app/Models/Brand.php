<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'img', // FK -> medias.id (media_id), KHÔNG còn là path string
        'website',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Ảnh đại diện Brand — trỏ tới Media (Single Source of Truth cho file +
     * variants + url). Tên relationship "media" (không phải "img") để rõ
     * nghĩa, dù cột FK vẫn tên "img" theo kiến trúc đã chốt.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'img');
    }

    /** Guard tương tự Category — Product model chưa tồn tại ở giai đoạn này. */
    public function products(): HasMany
    {
        if (! class_exists(\App\Models\Product::class)) {
            throw new \RuntimeException('Model Product chưa tồn tại trong project.');
        }
    
        return $this->hasMany(\App\Models\Product::class);
    }
}