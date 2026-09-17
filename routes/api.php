<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==== AUTH (public) ====
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ==== AUTH (đã đăng nhập) ====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']); // FE gọi khi load app để check trạng thái đăng nhập
 
    // ==== QUẢN LÝ USER (chỉ admin) ====
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
    });
 
    // ==== Các route nghiệp vụ khác (bikes, parts, inventory...) đặt ở đây ====
    // Route::apiResource('bikes', BikeController::class);
    // Route::apiResource('parts', PartController::class);
});
