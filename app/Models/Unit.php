<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Quan hệ tới Product — kích hoạt thật, Product module đã hoàn thành
     * (Phase 1). unit_id là FK NOT NULL + RESTRICT ở DB.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * ProductVariant (biến thể sản phẩm) CHƯA tồn tại — ngoài phạm vi Phase 1,
     * giữ nguyên placeholder, không kích hoạt.
     */
    // public function productVariants(): HasMany
    // {
    //     if (! class_exists(\App\Models\ProductVariant::class)) {
    //         throw new \RuntimeException('Model ProductVariant chưa tồn tại trong project.');
    //     }
    //
    //     return $this->hasMany(\App\Models\ProductVariant::class);
    // }
}