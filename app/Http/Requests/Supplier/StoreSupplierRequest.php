<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'            => ['required', 'string', 'max:50', 'unique:suppliers,code'],
            'name'            => ['required', 'string', 'max:255'],
            'contact_person'  => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'email'           => ['nullable', 'email', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'tax_code'        => ['nullable', 'string', 'max:50'],
            'website'         => ['nullable', 'url', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'is_active'       => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'  => 'Mã nhà cung cấp không được để trống.',
            'code.unique'    => 'Mã nhà cung cấp này đã tồn tại.',
            'name.required'  => 'Tên nhà cung cấp không được để trống.',
            'phone.regex'    => 'Số điện thoại không đúng định dạng.',
            'email.email'    => 'Email không đúng định dạng.',
            'website.url'    => 'Website không đúng định dạng (vd: https://example.com).',
        ];
    }
}
