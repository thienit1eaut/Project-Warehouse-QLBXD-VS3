<?php

namespace App\Http\Requests\Stocktake;

class UpdateStocktakeRequest extends StoreStocktakeRequest
{
    // Cùng rule với Store; quyền 'stocktake.update' kiểm ở route.
    // Trạng thái DRAFT được StocktakeService::updateDraft() bảo vệ.
}