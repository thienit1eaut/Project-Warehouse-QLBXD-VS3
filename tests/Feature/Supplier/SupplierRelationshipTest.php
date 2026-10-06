<?php

namespace Tests\Feature\Supplier;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Repositories\SupplierRepository;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase I.1 — khoá hành vi Supplier::products() (trước đây bị comment làm trang
 * Nhà cung cấp lỗi) và việc chặn xoá Supplier đang được sử dụng.
 */
class SupplierRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Xe đạp', 'slug' => 'xe-dap', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Chiếc', 'is_active' => true]);

        $this->supplier = Supplier::create(['code' => 'SUP-001', 'name' => 'NCC A', 'is_active' => true]);

        $this->product = Product::create([
            'sku' => 'HG54',
            'name' => 'Sản phẩm HG54',
            'slug' => 'hg54',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'supplier_id' => $this->supplier->id,
            'selling_price' => 100000,
            'is_active' => true,
        ]);
    }

    public function test_supplier_has_many_products(): void
    {
        $this->assertSame(1, $this->supplier->products()->count());
        $this->assertTrue($this->supplier->products->first()->is($this->product));
    }

    public function test_repository_paginate_returns_products_count(): void
    {
        $rows = app(SupplierRepository::class)->paginate()->items();

        $this->assertSame(1, (int) $rows[0]->products_count);
    }

    public function test_cannot_delete_supplier_used_by_product(): void
    {
        try {
            app(SupplierService::class)->delete($this->supplier);
            $this->fail('Phải chặn xoá supplier đang có sản phẩm.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier', $e->errors());
        }

        $this->assertDatabaseHas('suppliers', ['id' => $this->supplier->id]);
    }

    public function test_cannot_delete_supplier_with_purchase_receipt(): void
    {
        $other = Supplier::create(['code' => 'SUP-002', 'name' => 'NCC B', 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'WH-01', 'name' => 'Kho 01']);

        PurchaseReceipt::create([
            'receipt_code' => 'PN-TEST',
            'supplier_id' => $other->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseReceipt::STATUS_DRAFT,
            'receipt_date' => '2026-10-01',
        ]);

        try {
            app(SupplierService::class)->delete($other);
            $this->fail('Phải chặn xoá supplier đã có phiếu nhập.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier', $e->errors());
        }

        $this->assertDatabaseHas('suppliers', ['id' => $other->id]);
    }

    public function test_unused_supplier_can_be_deleted(): void
    {
        $free = Supplier::create(['code' => 'SUP-003', 'name' => 'NCC C', 'is_active' => true]);

        app(SupplierService::class)->delete($free);

        $this->assertDatabaseMissing('suppliers', ['id' => $free->id]);
    }
}