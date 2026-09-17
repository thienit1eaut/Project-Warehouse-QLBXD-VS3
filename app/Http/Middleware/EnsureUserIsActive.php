<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Chạy SAU middleware 'auth' — đảm bảo $request->user() luôn có giá trị.
     *
     * Giải quyết lỗ hổng: user bị deactivate (is_active = false) trong khi
     * session vẫn còn hiệu lực. LoginController đã check is_active lúc login,
     * nhưng không ngăn được session cũ tiếp tục hoạt động sau khi admin
     * deactivate user. Middleware này enforce per-request.
     *
     * Áp dụng: chỉ trong route group ['auth', 'active'] — không chạy trên
     * guest routes (/login, /register).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tài khoản của bạn đã bị vô hiệu hoá.');
        }

        return $next($request);
    }
}