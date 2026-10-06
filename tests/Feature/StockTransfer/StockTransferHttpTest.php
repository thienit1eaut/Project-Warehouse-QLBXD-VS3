<?php

namespace Tests\Feature\StockTransfer;

use App\Models\StockAllocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferHttpTest extends TestCase
{
    use RefreshDatabase, StockTransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTransferFixtures();
    }

    private function draft(array $items = null): StockTransfer
    {
        return $this->transferService()->createDraft($this->payload($items), $this->admin->id);
    }

    // ------------------------------------------------------------------ pages

    public function test_index_create_show_edit_pages_render(): void
    {
        $transfer = $this->draft();

        $this->actingAs($this->admin)->get('/admin/stock-transfer')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/StockTransfers/Index')->has('transfers.data', 1));

        $this->actingAs($this->admin)->get('/admin/stock-transfer/create')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/StockTransfers/Form')
                ->where('transfer', null)
                ->has('warehouseOptions')
                ->has('productOptions'));

        $this->actingAs($this->admin)->get("/admin/stock-transfer/{$transfer->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/StockTransfers/Show')
                ->where('transfer.status', 'draft')
                ->where('transfer.from_warehouse.code', 'WH-A')
                ->where('transfer.to_warehouse.code', 'WH-B'));

        $this->actingAs($this->admin)->get("/admin/stock-transfer/{$transfer->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/StockTransfers/Form')
                ->where('transfer.id', $transfer->id));
    }

    public function test_warehouse_options_only_contain_active_warehouses(): void
    {
        $this->actingAs($this->admin)->get('/admin/stock-transfer/create')
            ->assertInertia(fn ($p) => $p->has('warehouseOptions', 3)); // A, B, C — không có kho ngừng hoạt động
    }

    // ------------------------------------------------------------------ store / validation

    public function test_store_creates_draft_and_redirects(): void
    {
        $this->receive($this->whA, $this->productA, 10);

        $response = $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload());

        $transfer = StockTransfer::firstOrFail();

        $response->assertRedirect("/admin/stock-transfer/{$transfer->id}")->assertSessionHas('success');
        $this->assertSame('draft', $transfer->status);
        $this->assertSame($this->admin->id, $transfer->created_by);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005); // DRAFT không đổi tồn
        $this->assertSame(0, StockMovement::where('movement_type', 'out')->count());
    }

    public function test_store_validation_rejects_invalid_input_and_creates_nothing(): void
    {
        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload(null, ['to_warehouse_id' => $this->whA->id]))
            ->assertSessionHasErrors('to_warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload(null, ['from_warehouse_id' => $this->whInactive->id]))
            ->assertSessionHasErrors('from_warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload(null, ['to_warehouse_id' => $this->whInactive->id]))
            ->assertSessionHasErrors('to_warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload(null, ['from_warehouse_id' => null]))
            ->assertSessionHasErrors('from_warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 0],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload([
            ['product_id' => 999999, 'quantity' => 1],
        ]))->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(0, StockTransfer::count());
    }

    // ------------------------------------------------------------------ update / delete

    public function test_update_and_destroy_draft_via_http(): void
    {
        $transfer = $this->draft();

        $this->actingAs($this->admin)->put("/admin/stock-transfer/{$transfer->id}", $this->payload([
            ['product_id' => $this->productB->id, 'quantity' => 8],
        ], ['to_warehouse_id' => $this->whC->id, 'note' => 'Sửa qua HTTP']))
            ->assertRedirect("/admin/stock-transfer/{$transfer->id}")
            ->assertSessionHas('success');

        $fresh = $transfer->fresh();
        $this->assertSame('Sửa qua HTTP', $fresh->note);
        $this->assertSame($this->whC->id, $fresh->to_warehouse_id);
        $this->assertSame($this->productB->id, $fresh->items->first()->product_id);

        $this->actingAs($this->admin)->delete("/admin/stock-transfer/{$transfer->id}")
            ->assertRedirect('/admin/stock-transfer')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('stock_transfers', ['id' => $transfer->id]);
    }

    public function test_posted_transfer_cannot_be_edited_or_deleted_via_http(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();
        $this->transferService()->post($transfer->id);

        $this->actingAs($this->admin)->get("/admin/stock-transfer/{$transfer->id}/edit")
            ->assertRedirect("/admin/stock-transfer/{$transfer->id}")
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->put("/admin/stock-transfer/{$transfer->id}", $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 1],
        ]))->assertSessionHasErrors('document');

        $this->actingAs($this->admin)->delete("/admin/stock-transfer/{$transfer->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id]);
        $this->assertEqualsWithDelta(4.0, (float) $transfer->fresh()->items->first()->quantity, 0.0005);
    }

    // ------------------------------------------------------------------ post

    public function test_full_http_flow_create_show_post_preserves_lot_age(): void
    {
        $this->seedAgedLots();

        $this->actingAs($this->admin)->post('/admin/stock-transfer', $this->payload([
            ['product_id' => $this->productA->id, 'quantity' => 12],
        ]))->assertSessionHasNoErrors();

        $transfer = StockTransfer::firstOrFail();
        $this->assertSame(0, StockMovement::where('reference_type', 'stock_transfer')->count()); // DRAFT chưa chuyển

        $post = $this->actingAs($this->admin)->post("/admin/stock-transfer/{$transfer->id}/post");
        $post->assertRedirect("/admin/stock-transfer/{$transfer->id}")->assertSessionHas('success');

        $this->assertSame('posted', $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->posted_at);
        $this->assertEqualsWithDelta(3.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(12.0, $this->onHand($this->whB, $this->productA), 0.0005);

        $dest = $this->lots($this->whB, $this->productA);
        $this->assertCount(2, $dest);
        $this->assertLot($dest[0], 10, 10, $this->ageOld(), null);
        $this->assertLot($dest[1], 2, 2, $this->ageNew(), '2027-01-01');

        $out = StockMovement::where('movement_type', 'out')->firstOrFail();
        $this->assertSame('stock_transfer', $out->reference_type);
        $this->assertSame($transfer->id, (int) $out->reference_id);
        $this->assertEqualsWithDelta(
            abs((float) $out->quantity),
            (float) StockAllocation::where('stock_movement_id', $out->id)->sum('quantity'),
            0.0005
        );

        $this->actingAs($this->admin)->get("/admin/stock-transfer/{$transfer->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('transfer.status', 'posted')
                ->where('transfer.creator.name', $this->admin->name));

        // POST lần 2 qua HTTP: bị chặn, kho không đổi.
        $this->actingAs($this->admin)->post("/admin/stock-transfer/{$transfer->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertEqualsWithDelta(3.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(12.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertSame(1, StockMovement::where('movement_type', 'out')->count());
    }

    public function test_post_with_insufficient_stock_is_rejected_through_http(): void
    {
        $this->receive($this->whA, $this->productA, 3);
        $transfer = $this->draft();   // cần 4, chỉ có 3
        $movements = StockMovement::count();

        $this->actingAs($this->admin)->post("/admin/stock-transfer/{$transfer->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertSame('draft', $transfer->fresh()->status);
        $this->assertEqualsWithDelta(3.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertEqualsWithDelta(0.0, $this->onHand($this->whB, $this->productA), 0.0005);
        $this->assertSame($movements, StockMovement::count());
        $this->assertSame(0, StockAllocation::count());
    }

    // ------------------------------------------------------------------ permission

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/stock-transfer')->assertRedirect(route('login'));
    }

    public function test_permission_view(): void
    {
        $transfer = $this->draft();

        $this->actingAs($this->noAccess)->get('/admin/stock-transfer')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noAccess)->get("/admin/stock-transfer/{$transfer->id}")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->get('/admin/stock-transfer')->assertOk();
        $this->actingAs($this->viewer)->get("/admin/stock-transfer/{$transfer->id}")->assertOk();
    }

    public function test_permission_create(): void
    {
        $this->actingAs($this->viewer)->get('/admin/stock-transfer/create')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->post('/admin/stock-transfer', $this->payload())->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, StockTransfer::count());
    }

    public function test_permission_update(): void
    {
        $transfer = $this->draft();

        $this->actingAs($this->viewer)->get("/admin/stock-transfer/{$transfer->id}/edit")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->put("/admin/stock-transfer/{$transfer->id}", $this->payload(null, ['note' => 'Bị sửa']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('Chuyển test', $transfer->fresh()->note);
    }

    public function test_permission_delete(): void
    {
        $transfer = $this->draft();

        $this->actingAs($this->viewer)->delete("/admin/stock-transfer/{$transfer->id}")->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id]);
    }

    public function test_permission_post(): void
    {
        $this->receive($this->whA, $this->productA, 10);
        $transfer = $this->draft();

        // viewer và noPost (có view/create/update/delete nhưng KHÔNG có post) đều không được POST
        $this->actingAs($this->viewer)->post("/admin/stock-transfer/{$transfer->id}/post")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noPost)->post("/admin/stock-transfer/{$transfer->id}/post")->assertRedirect(route('admin.dashboard'));

        $this->assertSame('draft', $transfer->fresh()->status);
        $this->assertEqualsWithDelta(10.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame(0, StockMovement::where('reference_type', 'stock_transfer')->count());

        // noPost vẫn tạo được chứng từ
        $this->actingAs($this->noPost)->post('/admin/stock-transfer', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(2, StockTransfer::count());
    }
}