<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã được kiểm tra ở route middleware ('permission:stock.adjust').
        return true;
    }

    /**
     * `actual_quantity` là TỔNG số lượng thực tế sau kiểm kê, KHÔNG phải
     * delta — khớp đúng signature InventoryService::adjustStock($actualQuantity).
     * Validate ở đây chỉ là "không âm" (input hợp lệ); business rule (FIFO
     * consume đủ hay không khi giảm) thuộc Service.
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'actual_quantity' => ['required', 'numeric', 'gte:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}