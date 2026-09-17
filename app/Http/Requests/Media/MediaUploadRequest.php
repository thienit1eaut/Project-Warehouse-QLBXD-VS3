<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', function ($attribute, $value, $fail) {
                $this->validateFileAgainstConfig($value, $fail);
            }],

            // Tuỳ chọn: attach ngay vào 1 Folder sau khi upload thành công.
            // Chỉ 1 folder_id đơn (không phải mảng) — giữ đơn giản cho phase 1,
            // muốn attach nhiều Folder cùng lúc thì gọi thêm addToFolder() sau.
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where(
                    fn ($query) => $query->whereNull('deleted_at')
                ),
            ],

            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file để upload.',
            'file.file' => 'File không hợp lệ.',
            'folder_id.exists' => 'Thư mục được chọn không tồn tại hoặc đã bị xoá.',
        ];
    }

    /**
     * Validate file theo config('media.types') + config('media.max_size') —
     * KHÔNG hard-code danh sách extension/mime/size ở đây, đúng yêu cầu STEP 3.
     *
     * Dùng MIME thực tế được sniff từ nội dung file (UploadedFile::getMimeType()
     * đọc file signature qua fileinfo, KHÔNG tin Content-Type header client gửi)
     * để xác định type — an toàn hơn dựa vào extension client cung cấp.
     *
     * Giới hạn size áp dụng ĐÚNG theo type phát hiện được (ảnh 5MB, video 50MB,
     * document 10MB...) — không dùng chung 1 giới hạn cho mọi loại file.
     */
    protected function validateFileAgainstConfig($file, \Closure $fail): void
    {
        if ($file->getSize() === 0) {
            $fail('File rỗng, vui lòng chọn file khác.');

            return;
        }

        $mime = $file->getMimeType();
        $type = $this->resolveTypeFromMime($mime);

        if ($type === null) {
            $fail('Định dạng file không được hỗ trợ.');

            return;
        }

        // Cross-check: extension client khai báo phải khớp với type đã xác định
        // TỪ NỘI DUNG THẬT — chặn trường hợp đổi đuôi 1 loại file để "mượn"
        // giới hạn dung lượng của loại khác (vd ảnh thật đổi đuôi .mp4 để được
        // tính giới hạn 50MB thay vì 5MB), dù nội dung không nguy hiểm nhưng vi
        // phạm business rule "type phải nhất quán với nội dung thật".
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = config("media.types.{$type}.extensions", []);

        if (! in_array($extension, $allowedExtensions, true)) {
            $fail('Đuôi file không khớp với định dạng nội dung thực tế của file.');

            return;
        }

        $maxKb = config("media.max_size.{$type}");

        if ($maxKb && $file->getSize() > $maxKb * 1024) {
            $maxMb = round($maxKb / 1024, 1);
            $fail("Dung lượng file vượt quá giới hạn cho phép ({$maxMb}MB).");
        }
    }

    protected function resolveTypeFromMime(string $mime): ?string
    {
        foreach (config('media.types', []) as $type => $typeConfig) {
            if (in_array($mime, $typeConfig['mimes'] ?? [], true)) {
                return $type;
            }
        }

        return null;
    }
}