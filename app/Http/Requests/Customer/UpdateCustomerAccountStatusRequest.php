<?php

namespace App\Http\Requests\Customer;

use App\Models\CustomerAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:customer.update ở route
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CustomerAccount::STATUSES)],
        ];
    }
}