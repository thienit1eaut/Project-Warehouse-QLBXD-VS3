<?php

namespace App\Http\Requests\MediaFolder;

use App\Rules\ValidFolderName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // chặn ở middleware route ('manager'); Phase 5 bổ sung Policy chi tiết hơn
    }

    /** Trim TRƯỚC khi validate — chuỗi chỉ toàn whitespace sẽ thành rỗng và bị 'required' chặn. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidFolderName()],

            // parent_id null = tạo folder gốc (root). Nếu có, phải trỏ tới 1
            // folder ĐANG ACTIVE (chưa soft-delete) — Rule::exists() chạy raw
            // query nên phải tự thêm whereNull('deleted_at'), KHÔNG tự động
            // loại trừ trashed như query Eloquent thường.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where(
                    fn ($query) => $query->whereNull('deleted_at')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên thư mục không được để trống.',
            'name.max' => 'Tên thư mục tối đa 100 ký tự.',
            'parent_id.exists' => 'Thư mục cha không tồn tại hoặc đã bị xoá.',
        ];
    }
}