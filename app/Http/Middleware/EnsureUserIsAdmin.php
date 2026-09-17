<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            // Web route → redirect về dashboard kèm thông báo lỗi
            return redirect()->route('admin.dashboard')
                ->with('error', 'Bạn không có quyền truy cập trang này.');
        }
        
        return $next($request);
    }
}
