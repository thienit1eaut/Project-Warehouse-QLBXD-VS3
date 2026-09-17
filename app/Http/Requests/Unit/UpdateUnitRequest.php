<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->id;

        return [
            'code'        => ['required', 'string', 'max:20', Rule::unique('units', 'code')->ignore($unitId)],
            'name'        => ['required', 'string', 'max:100', Rule::unique('units', 'name')->ignore($unitId)],
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