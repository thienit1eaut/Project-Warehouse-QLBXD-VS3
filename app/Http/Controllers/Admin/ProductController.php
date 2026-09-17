<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\BrandService;
use App\Services\CategoryService;
use App\Services\ProductService;
use App\Services\SupplierService;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected CategoryService $categoryService,
        protected BrandService $brandService,
        protected SupplierService $supplierService,
        protected UnitService $unitService,
    ) {}

    public function index(Request $request): Response
    {
        $products = $this->productService->list(
            $request->only(['search', 'category_id', 'brand_id', 'is_active', 'sort', 'direction'])
        );

        return Inertia::render('Admin/Products/Index', [
            'pageTitle'       => 'Quản lý sản phẩm',
            'products'        => $products,
            'filters'         => $request->only(['search', 'category_id', 'brand_id', 'is_active']),
            'categoryOptions' => $this->categoryService->options(),
            'brandOptions'    => $this->brandService->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'pageTitle'       => 'Thêm sản phẩm',
            'product'         => null,
            'categoryOptions' => $this->categoryService->options(),
            'brandOptions'    => $this->brandService->options(),
            'supplierOptions' => $this->supplierService->options(),
            'unitOptions'     => $this->unitService->options(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->productService->create($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Đã tạo sản phẩm thành công.');
    }

    /**
     * Route model binding không đi qua ProductRepository::findById() (đã
     * eager-load sẵn) — phải tự load() ở đây, cùng pattern
     * BrandController::edit()/CategoryController::edit().
     */
    public function edit(Product $product): Response
    {
        $product->load('media.variants');

        return Inertia::render('Admin/Products/Form', [
            'pageTitle' => 'Sửa sản phẩm: ' . $product->name,
            'product'   => array_merge(
                $product->only(
                    'id', 'sku', 'name', 'short_description', 'category_id', 'brand_id',
                    'supplier_id', 'unit_id', 'img', 'description',
                    'selling_price', 'is_active'
                ),
                ['media' => $product->media]
            ),
            'categoryOptions' => $this->categoryService->options(),
            'brandOptions'    => $this->brandService->options(),
            'supplierOptions' => $this->supplierService->options(),
            'unitOptions'     => $this->unitService->options(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->productService->update($product, $request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Đã cập nhật sản phẩm.');
    }

    /**
     * Trang xem chi tiết read-only — dùng Repository::findById() (đã
     * eager-load category/brand/supplier/unit/media.variants đầy đủ).
     * Model serialize tự động qua $hidden/$appends của Media, không cần
     * transform thủ công.
     */
    public function show(Product $product): Response
    {
        $product = $this->productService->find($product->id);

        return Inertia::render('Admin/Products/Show', [
            'pageTitle' => 'Chi tiết sản phẩm: ' . $product->name,
            'product'   => $product,
        ]);
    }

    /** Hard delete — Product không dùng SoftDeletes, cùng convention Category/Brand/Supplier/Unit. */
    public function destroy(Product $product): RedirectResponse
    {
        $this->productService->delete($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Đã xoá sản phẩm.');
    }
}