<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'email.unique'       => 'Email này đã được đăng ký.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min'       => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        // Resolve role_id từ slug 'staff' — không hard-code ID vì ID phụ thuộc
        // vào thứ tự insert (có thể khác nhau giữa môi trường dev/staging/production).
        // Nếu Role 'staff' không tồn tại (DB chưa seed), abort rõ ràng thay vì
        // để FK constraint throw lỗi mơ hồ.
        $staffRole = Role::where('slug', 'staff')->first();

        if (! $staffRole) {
            abort(500, 'Cấu hình hệ thống lỗi: Role "staff" chưa được khởi tạo.');
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role_id'   => $staffRole->id,
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}