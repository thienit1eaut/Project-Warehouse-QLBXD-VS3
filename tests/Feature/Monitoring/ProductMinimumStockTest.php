<?php

namespace Tests\Feature\Monitoring;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMinimumStockTest extends TestCase
{
    use RefreshDatabase, MonitoringFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMonitoringFixtures();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'MIN-001',
            'name' => 'Phanh Shimano',
            'category_id' => $this->catPart->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 250000,
            'is_active' => true,
        ], $overrides);
    }

    public function test_default_minimum_stock_is_zero(): void
    {
        $product = Product::create([
            'sku' => 'RAW-1',
            'name' => 'Tạo thẳng bằng model',
            'slug' => 'raw-1',
            'category_id' => $this->catRoad->id,
            'unit_id' => $this->unit->id,
            'selling_price' => 1000,
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $product->fresh()->minimum_stock, 0.0005);
    }

    public function test_create_without_minimum_stock_uses_default_zero(): void
    {
        $this->actingAs($this->manager)->post('/admin/products', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(0.0, (float) Product::where('sku', 'MIN-001')->firstOrFail()->minimum_stock, 0.0005);
    }

    public function test_create_with_minimum_stock(): void
    {
        $this->actingAs($this->manager)->post('/admin/products', $this->payload(['minimum_stock' => 7.5]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(7.5, (float) Product::where('sku', 'MIN-001')->firstOrFail()->minimum_stock, 0.0005);
    }

    public function test_update_minimum_stock(): void
    {
        $product = $this->makeProduct('MIN-002', ['minimum_stock' => 3]);

        $this->actingAs($this->manager)->put("/admin/products/{$product->id}", $this->payload([
            'sku' => 'MIN-002',
            'minimum_stock' => 12,
        ]))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(12.0, (float) $product->fresh()->minimum_stock, 0.0005);
    }

    public function test_update_with_blank_minimum_stock_keeps_existing_value(): void
    {
        $product = $this->makeProduct('MIN-003', ['minimum_stock' => 9]);

        $this->actingAs($this->manager)->put("/admin/products/{$product->id}", $this->payload([
            'sku' => 'MIN-003',
            'minimum_stock' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(9.0, (float) $product->fresh()->minimum_stock, 0.0005);
    }

    public function test_invalid_minimum_stock_is_rejected(): void
    {
        foreach ([-1, 'abc', 1.2345] as $bad) {
            $this->actingAs($this->manager)->post('/admin/products', $this->payload(['minimum_stock' => $bad]))
                ->assertSessionHasErrors('minimum_stock');
        }

        $this->assertSame(0, Product::count());
    }

    public function test_edit_page_exposes_minimum_stock(): void
    {
        $product = $this->makeProduct('MIN-004', ['minimum_stock' => 5]);

        $this->actingAs($this->manager)->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.minimum_stock', fn ($v) => (float) $v === 5.0));
    }
}