<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:20', 'unique:units,code'],
            'name'        => ['required', 'string', 'max:100', 'unique:units,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã đơn vị tính không được để trống.',
            'code.unique'   => 'Mã đơn vị tính này đã tồn tại.',
            'name.required' => 'Tên đơn vị tính không được để trống.',
            'name.unique'   => 'Tên đơn vị tính này đã tồn tại.',
        ];
    }
}