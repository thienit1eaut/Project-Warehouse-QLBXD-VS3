<?php

namespace App\Http\Requests\MediaFolder;

use App\Rules\ValidFolderName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaFolderRequest extends FormRequest
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

    /**
     * Xử lý CẢ Rename (đổi name) lẫn Move (đổi parent_id) trong cùng 1 Request —
     * đúng yêu cầu Phase 2: không tách 2 request riêng cho 2 thao tác này.
     * Service (Phase 4) sẽ tự phân biệt "đây là rename hay move" bằng cách so
     * sánh giá trị mới với giá trị hiện tại của Model, không phải việc của Request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new ValidFolderName()],

            // parent_id null = move về root. Nếu có, phải là folder ĐANG ACTIVE.
            //
            // CHÚ Ý QUAN TRỌNG: KHÔNG có Rule::notIn($folderId) ở đây — kể cả
            // trường hợp đơn giản nhất (tự chọn chính mình làm cha) cũng KHÔNG
            // được validate ở tầng Request theo yêu cầu Phase 2 (mục 6): toàn bộ
            // logic circular reference (bao gồm cả self-parent lẫn move-vào-
            // descendant) đều đẩy xuống MediaFolderService ở Phase 4 — Request
            // chỉ đảm bảo dữ liệu ĐẦU VÀO hợp lệ về cú pháp/tồn tại, không quyết
            // định business rule của cây thư mục.
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