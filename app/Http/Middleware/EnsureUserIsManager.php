<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    /**
     * Cho phép admin + manager. Khác với EnsureUserIsAdmin (chỉ admin) —
     * dùng cho các module nghiệp vụ (Category, Product, Inventory...) mà
     * cả admin lẫn manager đều cần thao tác, staff thì chỉ xem.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isManager()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Bạn không có quyền thực hiện hành động này.');
        }
        
        return $next($request);
    }
}
