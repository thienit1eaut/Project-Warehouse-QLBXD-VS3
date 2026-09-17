<?php

namespace App\Http\Requests\Category;

use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        // route model binding: /admin/categories/{category}
        $categoryId = $this->route('category')?->id;

        return [
            'name'      => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($categoryId)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                // Không cho chọn chính category hiện tại làm cha — chặn sớm ở validation,
                // circular hierarchy sâu hơn (con/cháu) được CategoryService xử lý.
                Rule::notIn([$categoryId]),
            ],
            'short_content' => ['nullable', 'string', 'max:2000'],
            // img = media_id (int) hoặc null (xoá ảnh). Cùng pattern UpdateBrandRequest.
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
            'name.unique'       => 'Tên danh mục này đã tồn tại.',
            'parent_id.exists'  => 'Danh mục cha không hợp lệ.',
            'parent_id.not_in'  => 'Danh mục không thể là danh mục cha của chính nó.',
        ];
    }
}