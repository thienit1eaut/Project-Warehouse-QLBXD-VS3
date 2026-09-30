<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAllocation extends Model
{
    use HasFactory;

    // Không SoftDeletes — cùng lý do StockMovement: đây là chi tiết của sổ cái
    // bất biến, không ai được sửa/xoá 1 dòng allocation đã ghi.

    protected $fillable = [
        'stock_movement_id',
        'stock_lot_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }
}