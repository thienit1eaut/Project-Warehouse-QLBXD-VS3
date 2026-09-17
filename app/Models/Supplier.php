<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_code',
        'website',
        'description',
        'is_active',
    ];
 
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
 
    /** Guard tương tự Category/Brand — Product chưa tồn tại ở giai đoạn này. */
    // public function products(): HasMany
    // {
    //     if (! class_exists(\App\Models\Product::class)) {
    //         throw new \RuntimeException('Model Product chưa tồn tại trong project.');
    //     }
 
    //     return $this->hasMany(\App\Models\Product::class);
    // }
 
    /** Guard tương tự — module Purchase Order chưa tồn tại ở giai đoạn này. */
    // public function purchaseOrders(): HasMany
    // {
    //     if (! class_exists(\App\Models\PurchaseOrder::class)) {
    //         throw new \RuntimeException('Model PurchaseOrder chưa tồn tại trong project.');
    //     }
 
    //     return $this->hasMany(\App\Models\PurchaseOrder::class);
    // }
}
