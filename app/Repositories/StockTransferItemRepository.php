<?php

namespace App\Repositories;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Collection;

class StockTransferItemRepository
{
    public function createMany(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            $transfer->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }
    }

    public function getByTransfer(StockTransfer $transfer): Collection
    {
        return StockTransferItem::with('product:id,sku,name')
            ->where('stock_transfer_id', $transfer->id)
            ->orderBy('id')
            ->get();
    }

    /** Chỉ Service gọi khi chứng từ còn DRAFT. */
    public function deleteByTransfer(StockTransfer $transfer): void
    {
        StockTransferItem::where('stock_transfer_id', $transfer->id)->delete();
    }
}