<?php

namespace App\Http\Requests\Brand;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;

        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brandId)],
            'description' => ['nullable', 'string', 'max:2000'],
            // img = media_id (int) hoặc null (xoá ảnh). Không còn 'remove_logo'
            // riêng — gửi img: null là đủ để biểu thị "không có ảnh".
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
            'website'   => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên thương hiệu này đã tồn tại.',
            'website.url' => 'Website không đúng định dạng (vd: https://example.com).',
        ];
    }
}