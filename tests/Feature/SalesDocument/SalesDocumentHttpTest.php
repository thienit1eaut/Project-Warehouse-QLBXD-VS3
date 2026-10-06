<?php

namespace Tests\Feature\SalesDocument;

use App\Models\SalesDocument;
use App\Models\StockAllocation;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDocumentHttpTest extends TestCase
{
    use RefreshDatabase, SalesDocumentFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSalesFixtures();
    }

    private function draft(array $items = null): SalesDocument
    {
        return $this->salesService()->createDraft($this->payload($items), $this->admin->id);
    }

    // ------------------------------------------------------------------ pages

    public function test_index_create_show_edit_pages_render(): void
    {
        $document = $this->draft();

        $this->actingAs($this->admin)->get('/admin/sales')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/SalesDocuments/Index')->has('documents.data', 1));

        $this->actingAs($this->admin)->get('/admin/sales/create')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/SalesDocuments/Form')
                ->where('document', null)
                ->has('productOptions')
                ->has('warehouseOptions')
                ->has('customerOptions'));

        $this->actingAs($this->admin)->get("/admin/sales/{$document->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/SalesDocuments/Show')
                ->where('document.status', 'draft')
                ->where('document.total_amount', fn ($v) => (float) $v === 400000.0));

        $this->actingAs($this->admin)->get("/admin/sales/{$document->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/SalesDocuments/Form')
                ->where('document.id', $document->id));
    }

    public function test_product_options_include_selling_price(): void
    {
        $this->actingAs($this->admin)->get('/admin/sales/create')
            ->assertInertia(fn ($p) => $p->has('productOptions.0.selling_price'));
    }

    // ------------------------------------------------------------------ store / validation

    public function test_store_creates_draft_with_price_snapshot_and_redirects(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/sales', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 4],                  // không gửi giá -> snapshot
            ['product_id' => $this->productB->id, 'quantity' => 2, 'unit_price' => 42000],
        ], ['customer_id' => $this->customer->id]));

        $document = SalesDocument::firstOrFail();

        $response->assertRedirect("/admin/sales/{$document->id}")->assertSessionHas('success');
        $this->assertSame('draft', $document->status);
        $this->assertSame($this->customer->id, $document->customer_id);
        $this->assertSame($this->admin->id, $document->created_by);
        $this->assertEqualsWithDelta(100000.0, (float) $document->items[0]->unit_price, 0.001);
        $this->assertEqualsWithDelta(42000.0, (float) $document->items[1]->unit_price, 0.001);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_store_validation_rejects_invalid_input_and_creates_nothing(): void
    {
        $this->actingAs($this->admin)->post('/admin/sales', $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->post('/admin/sales', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 0],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($this->admin)->post('/admin/sales', $this->payload(null, ['warehouse_id' => 999999]))
            ->assertSessionHasErrors('warehouse_id');

        $this->actingAs($this->admin)->post('/admin/sales', $this->payload(null, ['customer_id' => 999999]))
            ->assertSessionHasErrors('customer_id');

        $this->actingAs($this->admin)->post('/admin/sales', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 1, 'unit_price' => -1],
        ]))->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, SalesDocument::count());
    }

    // ------------------------------------------------------------------ update / delete

    public function test_update_and_destroy_draft_via_http(): void
    {
        $document = $this->draft();

        $this->actingAs($this->admin)->put("/admin/sales/{$document->id}", $this->payload([
            ['product_id' => $this->productB->id, 'quantity' => 8],
        ], ['note' => 'Sửa qua HTTP']))
            ->assertRedirect("/admin/sales/{$document->id}")
            ->assertSessionHas('success');

        $this->assertSame('Sửa qua HTTP', $document->fresh()->note);
        $this->assertSame($this->productB->id, $document->fresh()->items->first()->product_id);

        $this->actingAs($this->admin)->delete("/admin/sales/{$document->id}")
            ->assertRedirect('/admin/sales')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sales_documents', ['id' => $document->id]);
    }

    public function test_posted_document_cannot_be_edited_or_deleted_via_http(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->draft();
        $this->salesService()->post($document->id);

        $this->actingAs($this->admin)->get("/admin/sales/{$document->id}/edit")
            ->assertRedirect("/admin/sales/{$document->id}")
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->put("/admin/sales/{$document->id}", $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 1],
        ]))->assertSessionHasErrors('document');

        $this->actingAs($this->admin)->delete("/admin/sales/{$document->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sales_documents', ['id' => $document->id]);
        $this->assertEqualsWithDelta(4.0, (float) $document->fresh()->items->first()->quantity, 0.0005);
    }

    // ------------------------------------------------------------------ post

    public function test_full_http_flow_create_show_post(): void
    {
        $this->receive($this->wh1, $this->productA, 10, now()->subDay());
        $this->receive($this->wh1, $this->productA, 5, now());

        $this->actingAs($this->admin)->post('/admin/sales', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 12],
        ], ['customer_id' => $this->customer->id]))->assertSessionHasNoErrors();

        $document = SalesDocument::firstOrFail();
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count()); // DRAFT chưa xuất kho

        $post = $this->actingAs($this->admin)->post("/admin/sales/{$document->id}/post");
        $post->assertRedirect("/admin/sales/{$document->id}")->assertSessionHas('success');

        $this->assertSame('posted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->posted_at);
        $this->assertEqualsWithDelta(3.0, $this->onHand($this->wh1, $this->productA), 0.0005);

        $movement = StockMovement::where('movement_type', 'out')->firstOrFail();
        $this->assertSame('sales_document', $movement->reference_type);
        $this->assertSame($document->id, (int) $movement->reference_id);
        $this->assertEqualsWithDelta(
            abs((float) $movement->quantity),
            (float) StockAllocation::where('stock_movement_id', $movement->id)->sum('quantity'),
            0.0005
        );

        $this->actingAs($this->admin)->get("/admin/sales/{$document->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('document.status', 'posted')
                ->where('document.creator.name', $this->admin->name));

        // POST lần 2 qua HTTP: bị chặn, kho không đổi.
        $this->actingAs($this->admin)->post("/admin/sales/{$document->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertEqualsWithDelta(3.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertSame(1, StockMovement::where('movement_type', 'out')->count());
    }

    public function test_post_with_insufficient_stock_is_rejected_through_http(): void
    {
        $this->receive($this->wh1, $this->productA, 3);
        $document = $this->draft();   // cần 4, chỉ có 3

        $this->actingAs($this->admin)->post("/admin/sales/{$document->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertSame('draft', $document->fresh()->status);
        $this->assertEqualsWithDelta(3.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
        $this->assertSame(0, StockAllocation::count());
    }

    // ------------------------------------------------------------------ permission

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/sales')->assertRedirect(route('login'));
    }

    public function test_permission_view(): void
    {
        $document = $this->draft();

        $this->actingAs($this->noAccess)->get('/admin/sales')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noAccess)->get("/admin/sales/{$document->id}")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->get('/admin/sales')->assertOk();
        $this->actingAs($this->viewer)->get("/admin/sales/{$document->id}")->assertOk();
    }

    public function test_permission_create(): void
    {
        $this->actingAs($this->viewer)->get('/admin/sales/create')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->post('/admin/sales', $this->payload())->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, SalesDocument::count());
    }

    public function test_permission_update(): void
    {
        $document = $this->draft();

        $this->actingAs($this->viewer)->get("/admin/sales/{$document->id}/edit")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->put("/admin/sales/{$document->id}", $this->payload(null, ['note' => 'Bị sửa']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('Bán test', $document->fresh()->note);
    }

    public function test_permission_delete(): void
    {
        $document = $this->draft();

        $this->actingAs($this->viewer)->delete("/admin/sales/{$document->id}")->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('sales_documents', ['id' => $document->id]);
    }

    public function test_permission_post(): void
    {
        $this->receive($this->wh1, $this->productA, 10);
        $document = $this->draft();

        // viewer và noPost (có view/create/update/delete nhưng KHÔNG có post) đều không được POST
        $this->actingAs($this->viewer)->post("/admin/sales/{$document->id}/post")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noPost)->post("/admin/sales/{$document->id}/post")->assertRedirect(route('admin.dashboard'));

        $this->assertSame('draft', $document->fresh()->status);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->wh1, $this->productA), 0.0005);
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());

        // noPost vẫn tạo được chứng từ
        $this->actingAs($this->noPost)->post('/admin/sales', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(2, SalesDocument::count());
    }
}