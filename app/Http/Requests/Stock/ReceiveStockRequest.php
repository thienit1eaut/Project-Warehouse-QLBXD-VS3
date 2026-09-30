<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã được kiểm tra ở route middleware ('permission:stock.receive').
        return true;
    }

    /**
     * Đây là validate ở tầng HTTP/input (đúng format, tồn tại, quantity > 0).
     * KHÔNG thay thế validate business rule trong InventoryService::receiveStock()
     * (Service tự bảo vệ invariant của chính nó, không phụ thuộc tầng này).
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'received_at' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}