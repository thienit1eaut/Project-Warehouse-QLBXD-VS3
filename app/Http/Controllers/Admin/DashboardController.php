<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected InventoryMonitoringService $monitoring,
    ) {
    }

    /**
     * Route này mở cho mọi người dùng đã đăng nhập (là đích redirect khi bị từ chối quyền ở các route khác).
     * Số liệu kho chỉ được trả khi người dùng có 'stock.view'; ngược lại 'inventory' = null.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'pageTitle' => 'Tổng quan kho hàng',
            'inventory' => $request->user()->hasPermission('stock.view')
                ? $this->monitoring->dashboard()
                : null,
        ]);
    }
}