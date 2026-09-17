<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Dữ liệu share cho MỌI page Vue qua usePage().props.
     *
     * CONTRACT FRONTEND (không thay đổi):
     * - auth.user.role  → slug string ('admin'|'manager'|...) cho badge/label
     *
     * MỚI THÊM:
     * - auth.user.permissions → mảng permission key của user hiện tại
     *   Ví dụ: ['media.view', 'media.create', 'media.manage-folder']
     *   Vue dùng để ẩn/hiện button/menu (UX only — backend middleware là security layer)
     *
     * HIỆU NĂNG:
     * - Eager-load 'role.permissions.module' 1 lần per-request tại đây
     * - Mọi hasPermission() call trong cùng request sẽ chạy in-memory (0 query thêm)
     * - Permissions array build từ collection đã load, không query lại DB
     *
     * Dùng Closure để lazy-evaluate — chỉ tính khi Inertia thực sự cần,
     * không chạy query trên mọi request kể cả non-Inertia.
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return ['user' => null];
                }

                // Eager-load 1 lần — mọi hasPermission() call sau đó in-memory
                $user->loadMissing('role.permissions.module');

                // Build permission keys từ collection đã load (0 query thêm)
                $permissions = $user->role
                    ?->permissions
                    ->map(fn ($perm) => $perm->module->slug . '.' . $perm->action)
                    ->values()
                    ->all()
                    ?? [];

                return [
                    'user' => [
                        'id'          => $user->id,
                        'name'        => $user->name,
                        'email'       => $user->email,
                        // slug string — giữ đúng contract cũ cho roleLabel/roleBadge
                        'role'        => $user->role?->slug,
                        'is_active'   => $user->is_active,
                        // Mảng permission keys cho Vue UX
                        'permissions' => $permissions,
                    ],
                ];
            },

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}