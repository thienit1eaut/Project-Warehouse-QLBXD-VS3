<?php

namespace App\Repositories;

use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use Illuminate\Database\Eloquent\Collection;

class SalesDocumentItemRepository
{
    public function createMany(SalesDocument $document, array $items): void
    {
        foreach ($items as $item) {
            $document->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }
    }

    public function getByDocument(SalesDocument $document): Collection
    {
        return SalesDocumentItem::with('product:id,sku,name')
            ->where('sales_document_id', $document->id)
            ->orderBy('id')
            ->get();
    }

    /** Chỉ Service gọi khi chứng từ còn DRAFT. */
    public function deleteByDocument(SalesDocument $document): void
    {
        SalesDocumentItem::where('sales_document_id', $document->id)->delete();
    }
}