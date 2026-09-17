<?php

namespace App\Http\Requests\Brand;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', 'unique:brands,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            // img = media_id (int), KHÔNG còn là file upload trực tiếp.
            // Closure kiểm tra Media tồn tại (active) VÀ đúng type=image —
            // không cho phép gán Media video/document/... làm ảnh đại diện.
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