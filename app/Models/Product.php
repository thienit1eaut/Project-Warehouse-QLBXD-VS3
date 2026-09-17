<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'slug',
        'short_description',
        'category_id',
        'brand_id',
        'supplier_id',
        'unit_id',
        'img', // FK -> medias.id (media_id), cùng pattern Brand/Category
        'description',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'selling_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Ảnh đại diện — trỏ tới Media, cùng pattern Brand::media()/Category::media(). */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'img');
    }
}