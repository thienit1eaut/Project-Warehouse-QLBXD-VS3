<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Sanctum SPA auth cho API routes
        $middleware->statefulApi();
        $middleware->alias([
            // Legacy — giữ cho Users/Category/Brand/Supplier/Unit hiện tại
            // Xoá khi các module đó được migrate sang permission:module.action
            'admin'      => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'manager'    => \App\Http\Middleware\EnsureUserIsManager::class,
 
            // Phase trước
            'active'     => \App\Http\Middleware\EnsureUserIsActive::class,
 
            // Generic Permission Middleware — dùng cho mọi module/action mới
            // Cú pháp: permission:module.action
            // Ví dụ: permission:media.view, permission:media-folder.force-delete
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
