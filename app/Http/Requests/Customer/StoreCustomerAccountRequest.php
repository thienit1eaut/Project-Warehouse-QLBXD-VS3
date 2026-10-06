<?php

namespace App\Http\Requests\Customer;

use App\Models\CustomerAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCustomerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:customer.update ở route
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:customer_accounts,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'status' => ['nullable', Rule::in(CustomerAccount::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email tài khoản này đã được sử dụng.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}