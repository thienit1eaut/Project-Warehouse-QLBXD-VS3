<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Đăng ký tài khoản mới.
     * Lưu ý: đây KHÔNG tự động đăng nhập, vì bạn có thể muốn admin duyệt tài khoản
     * (is_active = false mặc định) trước khi user được phép login. Tuỳ nghiệp vụ,
     * bạn có thể bỏ is_active và cho login luôn sau khi đăng ký.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'staff', // mặc định thấp nhất, admin có thể nâng quyền sau
            'is_active' => true,
        ]);
 
        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng đăng nhập.',
            'user' => $user,
        ], 201);
    }

    /**
     * Đăng nhập theo session-based auth (Sanctum SPA).
     * KHÔNG trả về token — trình duyệt sẽ tự lưu session cookie do Laravel set.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
 
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }
 
        $user = Auth::user();
 
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Tài khoản của bạn đã bị vô hiệu hoá.'],
            ]);
        }
 
        // Chống session fixation — bắt buộc phải regenerate sau khi login thành công
        $request->session()->regenerate();
 
        return response()->json(['user' => $user]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
 
        $request->session()->invalidate();
        $request->session()->regenerateToken();
 
        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    /**
     * Trả về thông tin user đang đăng nhập — dùng để FE kiểm tra trạng thái
     * đăng nhập khi tải lại trang (vì không có token nào để tự kiểm tra ở FE cả).
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
