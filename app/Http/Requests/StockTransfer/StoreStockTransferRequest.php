<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở route middleware
    }

    public function rules(): array
    {
        $activeWarehouse = Rule::exists('warehouses', 'id')->where('is_active', true);

        return [
            'from_warehouse_id' => ['required', 'integer', $activeWarehouse],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', $activeWarehouse],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_warehouse_id.exists' => 'Kho nguồn không tồn tại hoặc đang ngừng hoạt động.',
            'to_warehouse_id.exists' => 'Kho đích không tồn tại hoặc đang ngừng hoạt động.',
            'to_warehouse_id.different' => 'Kho nguồn và kho đích phải khác nhau.',
        ];
    }
}