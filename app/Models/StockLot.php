<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'warehouse_id',
        'product_id',
        'quantity_received',
        'quantity_remaining',
        'received_at',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received'  => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'received_at'        => 'datetime',
            'expiry_date'        => 'date',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StockAllocation::class);
    }
}