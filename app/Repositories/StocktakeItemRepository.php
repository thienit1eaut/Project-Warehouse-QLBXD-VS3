<?php

namespace App\Repositories;

use App\Models\Stocktake;
use App\Models\StocktakeItem;
use Illuminate\Database\Eloquent\Collection;

class StocktakeItemRepository
{
    /** $rows[]: product_id, system_quantity, actual_quantity, difference */
    public function createMany(Stocktake $stocktake, array $rows): void
    {
        foreach ($rows as $row) {
            $stocktake->items()->create([
                'product_id' => $row['product_id'],
                'system_quantity' => $row['system_quantity'],
                'actual_quantity' => $row['actual_quantity'],
                'difference' => $row['difference'],
            ]);
        }
    }

    public function getByStocktake(Stocktake $stocktake): Collection
    {
        return StocktakeItem::with('product:id,sku,name')
            ->where('stocktake_id', $stocktake->id)
            ->orderBy('id')
            ->get();
    }

    /** Chỉ Service gọi khi phiếu còn DRAFT. */
    public function deleteByStocktake(Stocktake $stocktake): void
    {
        StocktakeItem::where('stocktake_id', $stocktake->id)->delete();
    }
}