<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // đã chặn ở middleware 'admin' tại route
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Khi update (route có {user}), bỏ qua email trùng của chính user đó
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(['admin', 'manager', 'staff'])],
            'is_active' => ['boolean'],
        ];
    }
}
