<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerAccountRequest;
use App\Http\Requests\Customer\UpdateCustomerAccountStatusRequest;
use App\Models\Customer;
use App\Services\CustomerAccountService;
use Illuminate\Http\RedirectResponse;

/**
 * Quản lý dữ liệu CustomerAccount trong back-office (nền tảng cho Ecommerce tương lai).
 * Không có đăng nhập, không có portal. Không bao giờ trả lại password.
 */
class CustomerAccountController extends Controller
{
    public function __construct(
        protected CustomerAccountService $accountService,
    ) {
    }

    public function store(StoreCustomerAccountRequest $request, Customer $customer): RedirectResponse
    {
        $this->accountService->create($customer, $request->validated());

        return redirect()->route('admin.customers.show', $customer->id)
            ->with('success', 'Đã tạo tài khoản cho khách hàng.');
    }

    public function updateStatus(UpdateCustomerAccountStatusRequest $request, Customer $customer): RedirectResponse
    {
        $this->accountService->changeStatus($customer, $request->validated()['status']);

        return redirect()->route('admin.customers.show', $customer->id)
            ->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }
}