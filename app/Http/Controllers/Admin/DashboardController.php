<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            // Sau này — lấy từ DB, cache 1 tiếng để không query liên tục
            // 'pageTitle' => cache()->remember('setting.page_title.users', 3600, fn () =>
            //     Setting::where('key', 'page_title_users')->value('value') ?? 'Quản lý người dùng'
            // ),
            'pageTitle' => 'Tổng quan kho hàng',
            'stats' => [
                'totalUsers' => User::count(),
                'totalBikes' => 0, // thay bằng Bike::count() khi có model
                'totalParts' => 0,
                'lowStock'   => 0,
            ],
        ]);
    }
}
