<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService,
    ) {}
 
    public function index(Request $request): Response
    {
        $suppliers = $this->supplierService->list(
            $request->only(['search', 'is_active', 'sort', 'direction'])
        );
 
        return Inertia::render('Admin/Suppliers/Index', [
            'pageTitle' => 'Quản lý nhà cung cấp',
            'suppliers' => $suppliers,
            'filters'   => $request->only(['search', 'is_active']),
        ]);
    }
 
    public function create(): Response
    {
        return Inertia::render('Admin/Suppliers/Form', [
            'pageTitle' => 'Thêm nhà cung cấp',
            'supplier'  => null,
        ]);
    }
 
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->supplierService->create($request->validated());
 
        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Đã tạo nhà cung cấp thành công.');
    }
 
    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Admin/Suppliers/Form', [
            'pageTitle' => 'Sửa nhà cung cấp: ' . $supplier->name,
            'supplier'  => $supplier->only([
                'id', 'code', 'name', 'contact_person', 'phone',
                'email', 'address', 'tax_code', 'website', 'description', 'is_active',
            ]),
        ]);
    }
 
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->update($supplier, $request->validated());
 
        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Đã cập nhật nhà cung cấp.');
    }
 
    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $this->supplierService->delete($supplier);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
 
        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Đã xoá nhà cung cấp.');
    }
}
