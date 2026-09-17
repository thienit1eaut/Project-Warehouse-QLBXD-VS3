<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validate tên Media Folder theo whitelist — KHÔNG dùng blacklist liệt kê từng
 * ký tự cấm, vì whitelist an toàn hơn: bất kỳ ký tự nào không nằm trong danh
 * sách cho phép đều tự động bị chặn, kể cả những ký tự nguy hiểm chưa nghĩ tới.
 *
 * Cho phép: \p{L} (chữ Unicode, bao gồm tiếng Việt có dấu), \p{M} (dấu kết hợp
 * — phòng trường hợp Unicode decomposed form của tiếng Việt), \p{N} (số),
 * khoảng trắng (chỉ dấu cách thường, KHÔNG gồm tab/newline), - _ ( )
 *
 * KHÔNG cho phép dấu chấm "." dưới bất kỳ hình thức nào — vì whitelist không
 * liệt kê "." nên tên như ".", ".." hay "v1.2" đều tự động bị từ chối, loại bỏ
 * hoàn toàn nguy cơ path traversal (../, ..\) mà không cần rule riêng cho
 * trường hợp đặc biệt "." / "..".
 *
 * Value truyền vào rule này đã được trim ở prepareForValidation() của từng
 * FormRequest gọi rule — không tự trim lại ở đây để tránh 2 nơi cùng xử lý
 * 1 việc.
 */
class ValidFolderName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Tên thư mục không hợp lệ.');
 
            return;
        }
 
        if (! preg_match('/^[\p{L}\p{M}\p{N} \-_()]+$/u', $value)) {
            $fail('Tên thư mục chỉ được chứa chữ, số, khoảng trắng và các ký tự - _ ( ). Không được chứa / \\ : * ? " < > | hoặc dấu chấm.');
        }
    }
}
