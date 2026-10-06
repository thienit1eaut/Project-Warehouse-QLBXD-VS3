<?php

namespace App\Http\Requests\SalesDocument;

class UpdateSalesDocumentRequest extends StoreSalesDocumentRequest
{
    // Cùng rule với Store; quyền 'sales-document.update' kiểm ở route.
    // Trạng thái DRAFT được SalesDocumentService::updateDraft() bảo vệ.
}