<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService,
    ) {}

    public function index(Request $request): Response
    {
        $units = $this->unitService->list(
            $request->only(['search', 'is_active', 'sort', 'direction'])
        );

        return Inertia::render('Admin/Units/Index', [
            'pageTitle' => 'Quản lý đơn vị tính',
            'units'     => $units,
            'filters'   => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Units/Form', [
            'pageTitle' => 'Thêm đơn vị tính',
            'unit'      => null,
        ]);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $this->unitService->create($request->validated());

        return redirect()->route('admin.units.index')
            ->with('success', 'Đã tạo đơn vị tính thành công.');
    }

    public function edit(Unit $unit): Response
    {
        return Inertia::render('Admin/Units/Form', [
            'pageTitle' => 'Sửa đơn vị tính: ' . $unit->name,
            'unit'      => $unit->only('id', 'code', 'name', 'description', 'is_active'),
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->unitService->update($unit, $request->validated());

        return redirect()->route('admin.units.index')
            ->with('success', 'Đã cập nhật đơn vị tính.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        try {
            $this->unitService->delete($unit);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.units.index')
            ->with('success', 'Đã xoá đơn vị tính.');
    }
}