<?php

namespace App\Http\Requests\Product;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'                => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name'               => ['required', 'string', 'max:255'],
            'short_description'  => ['nullable', 'string', 'max:500'],
            'category_id'        => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id'    => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'unit_id'     => ['required', 'integer', Rule::exists('units', 'id')],
            // img = media_id (int) hoặc null — cùng pattern Store/UpdateBrandRequest.
            'img' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                $media = Media::find($value);

                if (! $media) {
                    $fail('Ảnh được chọn không tồn tại hoặc đã bị xoá.');
                    return;
                }

                if ($media->type !== 'image') {
                    $fail('File được chọn không phải là ảnh.');
                }
            }],
            'description'   => ['nullable', 'string', 'max:5000'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_active'     => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required'          => 'Vui lòng nhập mã SKU.',
            'sku.unique'            => 'Mã SKU này đã tồn tại.',
            'category_id.required'  => 'Vui lòng chọn danh mục.',
            'category_id.exists'    => 'Danh mục không hợp lệ.',
            'brand_id.exists'       => 'Thương hiệu không hợp lệ.',
            'supplier_id.exists'    => 'Nhà cung cấp không hợp lệ.',
            'unit_id.required'      => 'Vui lòng chọn đơn vị tính.',
            'unit_id.exists'        => 'Đơn vị tính không hợp lệ.',
            'selling_price.required' => 'Vui lòng nhập giá bán.',
            'selling_price.min'     => 'Giá bán không được âm.',
        ];
    }
}