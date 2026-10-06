<?php

namespace App\Http\Requests\StockTransfer;

class UpdateStockTransferRequest extends StoreStockTransferRequest
{
    // Cùng rule với Store; quyền 'stock-transfer.update' kiểm ở route.
    // Trạng thái DRAFT được StockTransferService::updateDraft() bảo vệ.
}