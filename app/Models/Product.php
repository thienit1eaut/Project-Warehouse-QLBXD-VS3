<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'img',
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

    /**
     * Inventory (Phase B foundation) — CHỈ relationship, KHÔNG thêm quantity
     * vào Product. Tồn kho thuộc về Stock/StockLot, không phải Product.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }
}