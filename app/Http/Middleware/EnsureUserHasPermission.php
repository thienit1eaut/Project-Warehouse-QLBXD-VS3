<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Generic Permission Middleware — dùng cho mọi module/action.
     *
     * Khai báo trên route:
     *   ->middleware('permission:media.view')
     *   ->middleware('permission:media.create')
     *   ->middleware('permission:media-folder.force-delete')
     *
     * Middleware này:
     * 1. Xác nhận user đã authenticate (auth middleware đã chạy trước).
     * 2. Kiểm tra user active (defense-in-depth).
     * 3. Gọi $user->hasPermission($permission) — không hard-code role slug.
     * 4. Trả 403 / redirect nếu không có quyền.
     *
     * Không thay thế Policy — Policy vẫn dùng cho business rule phức tạp
     * (ownership, state, scope). Middleware này chỉ cho simple permission check.
     *
     * Không hard-code bất kỳ role slug nào.
     * Quyền hoàn toàn do database role_permission quyết định.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            abort(403, 'Tài khoản của bạn đã bị vô hiệu hoá.');
        }

        if (! $user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                abort(403, 'Bạn không có quyền thực hiện hành động này.');
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}