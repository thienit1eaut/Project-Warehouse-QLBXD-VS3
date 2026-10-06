<?php

namespace App\Http\Requests\Stocktake;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStocktakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở route middleware
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'stocktake_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.exists' => 'Kho không tồn tại hoặc đang ngừng hoạt động.',
            'items.*.product_id.distinct' => 'Sản phẩm bị trùng trong phiếu kiểm kê.',
            'items.*.actual_quantity.min' => 'Số lượng thực tế không được nhỏ hơn 0.',
        ];
    }
}