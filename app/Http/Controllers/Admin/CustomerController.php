<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'customer_type', 'sort', 'direction']);

        return Inertia::render('Admin/Customers/Index', [
            'pageTitle' => 'Quản lý khách hàng',
            'customers' => $this->customerService->list($filters),
            'filters' => $request->only(['search', 'customer_type']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Form', [
            'pageTitle' => 'Thêm khách hàng',
            'customer' => null,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customerService->create($request->validated());

        return redirect()->route('admin.customers.show', $customer->id)
            ->with('success', 'Đã tạo khách hàng thành công.');
    }

    public function show(int $customer): Response
    {
        $customer = $this->customerService->findDetail($customer);

        return Inertia::render('Admin/Customers/Show', [
            'pageTitle' => 'Khách hàng: ' . $customer->name,
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Admin/Customers/Form', [
            'pageTitle' => 'Sửa khách hàng: ' . $customer->name,
            'customer' => $customer->only([
                'id', 'customer_code', 'name', 'phone', 'email', 'customer_type', 'address', 'note',
            ]),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customerService->update($customer, $request->validated());

        return redirect()->route('admin.customers.show', $customer->id)
            ->with('success', 'Đã cập nhật khách hàng.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        try {
            $this->customerService->delete($customer);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.customers.index')
            ->with('success', 'Đã xoá khách hàng.');
    }
}