<?php

namespace App\Repositories;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Database\Eloquent\Collection;

class PurchaseReceiptItemRepository
{
    public function createMany(PurchaseReceipt $receipt, array $items): void
    {
        foreach ($items as $item) {
            $receipt->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);
        }
    }

    public function getByReceipt(PurchaseReceipt $receipt): Collection
    {
        return PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)
            ->orderBy('id')
            ->get();
    }

    /** Chỉ Service gọi khi receipt còn DRAFT. */
    public function deleteByReceipt(PurchaseReceipt $receipt): void
    {
        PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)->delete();
    }
}