<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /** Hiển thị trang đăng nhập */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }
 
    /** Xử lý đăng nhập */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);
 
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }
 
        $user = Auth::user();
 
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Tài khoản của bạn đã bị vô hiệu hoá.',
            ]);
        }
 
        $request->session()->regenerate();
 
        return redirect()->intended(route('admin.dashboard'));
    }
 
    /** Đăng xuất */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
 
        $request->session()->invalidate();
        $request->session()->regenerateToken();
 
        return redirect()->route('login');
    }
}
