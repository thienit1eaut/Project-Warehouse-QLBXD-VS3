<?php

namespace App\Http\Requests\Category;

use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255', 'unique:categories,name'],
            'parent_id'     => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'short_content' => ['nullable', 'string', 'max:2000'],
            // img = media_id (int) hoặc null. Cùng pattern StoreBrandRequest —
            // Closure kiểm tra Media tồn tại VÀ đúng type=image.
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
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'      => 'Tên danh mục này đã tồn tại.',
            'parent_id.exists' => 'Danh mục cha không hợp lệ.',
        ];
    }
}