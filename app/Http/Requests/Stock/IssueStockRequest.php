<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class IssueStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã được kiểm tra ở route middleware ('permission:stock.issue').
        return true;
    }

    /**
     * Validate HTTP/input thuần (quantity > 0 theo hình thức nhập liệu).
     * Việc "đủ tồn hay không" là business rule, thuộc
     * InventoryService::issueStock() (FIFO + lock), KHÔNG check ở đây.
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}