<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization tại route middleware: permission:media.update
        return true;
    }

    /**
     * Trim whitespace trước khi validate — đúng convention StoreMediaFolderRequest.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'alt'         => is_string($this->alt)         ? trim($this->alt)         : $this->alt,
            'title'       => is_string($this->title)       ? trim($this->title)       : $this->title,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
        ]);
    }

    /**
     * Chỉ cho phép cập nhật 3 field metadata SEO/accessibility.
     *
     * KHÔNG cho phép client sửa:
     * - path, disk, file_name, original_name, extension, mime_type (file identity)
     * - file_hash, uuid (identifier)
     * - size, width, height, duration, metadata (tính tự động khi upload)
     * - type (detect từ MIME khi upload)
     * - status (lifecycle — Phase 1 auto-set 'ready')
     * - created_by (audit trail)
     */
    public function rules(): array
    {
        return [
            'alt'         => ['nullable', 'string', 'max:255'],
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'alt.max'         => 'Alt text tối đa 255 ký tự.',
            'title.max'       => 'Tiêu đề tối đa 255 ký tự.',
            'description.max' => 'Mô tả tối đa 2000 ký tự.',
        ];
    }
}