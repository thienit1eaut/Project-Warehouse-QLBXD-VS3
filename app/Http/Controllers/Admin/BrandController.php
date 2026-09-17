<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService,
    ) {}

    public function index(Request $request): Response
    {
        $brands = $this->brandService->list(
            $request->only(['search', 'is_active', 'sort', 'direction'])
        );

        return Inertia::render('Admin/Brands/Index', [
            'pageTitle' => 'Quản lý thương hiệu',
            'brands'    => $brands,
            'filters'   => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Brands/Form', [
            'pageTitle' => 'Thêm thương hiệu',
            'brand'     => null,
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $this->brandService->create($request->validated());

        return redirect()->route('admin.brands.index')
            ->with('success', 'Đã tạo thương hiệu thành công.');
    }

    /**
     * $brand đã được BrandRepository::findById() eager-load 'media.variants'
     * qua route model binding? KHÔNG — route model binding mặc định KHÔNG
     * chạy qua Repository, chỉ query thẳng bảng brands. Phải tự load ở đây.
     *
     * Trả 'media' như 1 object lồng trong 'brand' — Media model đã tự loại
     * bỏ file_hash/disk/path/created_by qua $hidden (Phase 7), tự có 'url'
     * qua $appends, variants cũng tự có 'url' riêng — không cần transform
     * thủ công, tận dụng nguyên serialization mặc định của Eloquent.
     */
    public function edit(Brand $brand): Response
    {
        $brand->load('media.variants');

        return Inertia::render('Admin/Brands/Form', [
            'pageTitle' => 'Sửa thương hiệu: ' . $brand->name,
            'brand'     => array_merge(
                $brand->only('id', 'name', 'description', 'website', 'is_active', 'img'),
                ['media' => $brand->media]
            ),
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        try {
            $this->brandService->update($brand, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.brands.index')
            ->with('success', 'Đã cập nhật thương hiệu.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        try {
            $this->brandService->delete($brand);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.brands.index')
            ->with('success', 'Đã xoá thương hiệu.');
    }
}