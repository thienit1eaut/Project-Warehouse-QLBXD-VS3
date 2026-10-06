<?php

namespace Tests\Feature\Stocktake;

use App\Models\Stocktake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StocktakeHttpTest extends TestCase
{
    use RefreshDatabase, StocktakeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createStocktakeFixtures();
    }

    private function draft(array $items = null): Stocktake
    {
        return $this->stocktakeService()->createDraft($this->payload($items), $this->admin->id);
    }

    // ------------------------------------------------------------------ pages

    public function test_index_create_show_edit_pages_render(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft();

        $this->actingAs($this->admin)->get('/admin/stocktake')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Stocktakes/Index')->has('stocktakes.data', 1));

        $this->actingAs($this->admin)->get('/admin/stocktake/create')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Stocktakes/Form')
                ->where('stocktake', null)
                ->has('warehouseOptions')
                ->has('productOptions'));

        $this->actingAs($this->admin)->get("/admin/stocktake/{$stocktake->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Stocktakes/Show')
                ->where('stocktake.status', 'draft')
                ->where('stocktake.items.0.system_quantity', fn ($v) => (float) $v === 20.0)
                ->where('stocktake.items.0.actual_quantity', fn ($v) => (float) $v === 18.0)
                ->where('stocktake.items.0.difference', fn ($v) => (float) $v === -2.0)
                ->where('stocktake.items.0.is_stale', false)
                ->where('stocktake.has_stale_items', false));

        $this->actingAs($this->admin)->get("/admin/stocktake/{$stocktake->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Stocktakes/Form')
                ->where('stocktake.id', $stocktake->id));
    }

    // ------------------------------------------------------------------ store / validation

    public function test_store_creates_draft_with_snapshot_and_redirects(): void
    {
        $this->receive($this->whA, $this->productA, 20);

        $response = $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload());

        $stocktake = Stocktake::firstOrFail();

        $response->assertRedirect("/admin/stocktake/{$stocktake->id}")->assertSessionHas('success');
        $this->assertSame('draft', $stocktake->status);
        $this->assertSame($this->admin->id, $stocktake->created_by);
        $this->assertEqualsWithDelta(20.0, (float) $stocktake->items->first()->system_quantity, 0.0005);
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->whA, $this->productA), 0.0005); // DRAFT không đổi tồn
        $this->assertSame(0, $this->adjustmentMovements()->count());
    }

    public function test_store_validation_rejects_invalid_input_and_creates_nothing(): void
    {
        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload(null, ['warehouse_id' => null]))
            ->assertSessionHasErrors('warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload(null, ['warehouse_id' => $this->whInactive->id]))
            ->assertSessionHasErrors('warehouse_id');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload(null, ['stocktake_date' => 'khong-hop-le']))
            ->assertSessionHasErrors('stocktake_date');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload([$this->line($this->productA, -1)]))
            ->assertSessionHasErrors('items.0.actual_quantity');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload([
            ['product_id' => $this->productA->id, 'actual_quantity' => 'abc'],
        ]))->assertSessionHasErrors('items.0.actual_quantity');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload([
            $this->line($this->productA, 5),
            $this->line($this->productA, 6),
        ]))->assertSessionHasErrors('items.1.product_id');

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload([
            ['product_id' => 999999, 'actual_quantity' => 1],
        ]))->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(0, Stocktake::count());
    }

    // ------------------------------------------------------------------ update / delete

    public function test_update_and_destroy_draft_via_http(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $this->receive($this->whB, $this->productB, 7);
        $stocktake = $this->draft();

        $this->actingAs($this->admin)->put("/admin/stocktake/{$stocktake->id}", $this->payload([
            $this->line($this->productB, 9),
        ], ['warehouse_id' => $this->whB->id, 'note' => 'Sửa qua HTTP']))
            ->assertRedirect("/admin/stocktake/{$stocktake->id}")
            ->assertSessionHas('success');

        $fresh = $stocktake->fresh();
        $this->assertSame('Sửa qua HTTP', $fresh->note);
        $this->assertSame($this->whB->id, $fresh->warehouse_id);
        $this->assertSame($this->productB->id, $fresh->items->first()->product_id);
        $this->assertEqualsWithDelta(7.0, (float) $fresh->items->first()->system_quantity, 0.0005); // snapshot theo kho B

        $this->actingAs($this->admin)->delete("/admin/stocktake/{$stocktake->id}")
            ->assertRedirect('/admin/stocktake')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('stocktakes', ['id' => $stocktake->id]);
    }

    public function test_posted_stocktake_cannot_be_edited_or_deleted_via_http(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft();
        $this->stocktakeService()->post($stocktake->id);

        $this->actingAs($this->admin)->get("/admin/stocktake/{$stocktake->id}/edit")
            ->assertRedirect("/admin/stocktake/{$stocktake->id}")
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->put("/admin/stocktake/{$stocktake->id}", $this->payload([$this->line($this->productA, 1)]))
            ->assertSessionHasErrors('document');

        $this->actingAs($this->admin)->delete("/admin/stocktake/{$stocktake->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stocktakes', ['id' => $stocktake->id]);
        $this->assertEqualsWithDelta(18.0, (float) $stocktake->fresh()->items->first()->actual_quantity, 0.0005);
    }

    // ------------------------------------------------------------------ post (shortage / surplus / no diff)

    public function test_post_shortage_surplus_and_no_difference_through_http(): void
    {
        // Thiếu: 20 -> 18
        $this->receive($this->whA, $this->productA, 20);
        $short = $this->draft([$this->line($this->productA, 18)]);
        $this->actingAs($this->admin)->post("/admin/stocktake/{$short->id}/post")
            ->assertRedirect("/admin/stocktake/{$short->id}")->assertSessionHas('success');
        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);

        // Thừa: 10 -> 14
        $this->receive($this->whA, $this->productB, 10);
        $over = $this->draft([$this->line($this->productB, 14)]);
        $this->actingAs($this->admin)->post("/admin/stocktake/{$over->id}/post")->assertSessionHas('success');
        $this->assertEqualsWithDelta(14.0, $this->onHand($this->whA, $this->productB), 0.0005);

        // Không chênh lệch: 5 -> 5
        $this->receive($this->whA, $this->productC, 5);
        $same = $this->draft([$this->line($this->productC, 5)]);
        $movementsBefore = $this->adjustmentMovements()->count();
        $this->actingAs($this->admin)->post("/admin/stocktake/{$same->id}/post")->assertSessionHas('success');
        $this->assertSame($movementsBefore, $this->adjustmentMovements()->count()); // không có ADJUSTMENT 0

        foreach ([$this->productA, $this->productB, $this->productC] as $product) {
            $this->assertStockMatchesLots($this->whA, $product);
        }

        $movement = $this->adjustmentMovements()->where('reference_id', $short->id)->firstOrFail();
        $this->assertSame('stocktake', $movement->reference_type);
        $this->assertEqualsWithDelta(-2.0, (float) $movement->quantity, 0.0005);
    }

    public function test_full_http_flow_with_stale_snapshot_then_refresh_then_post(): void
    {
        $this->receive($this->whA, $this->productA, 20);

        $this->actingAs($this->admin)->post('/admin/stocktake', $this->payload())->assertSessionHasNoErrors();
        $stocktake = Stocktake::firstOrFail();

        // Người khác xuất 3 sau khi phiếu được tạo -> current = 17
        $this->issue($this->whA, $this->productA, 3);

        $this->actingAs($this->admin)->get("/admin/stocktake/{$stocktake->id}")
            ->assertInertia(fn ($p) => $p
                ->where('stocktake.has_stale_items', true)
                ->where('stocktake.items.0.is_stale', true)
                ->where('stocktake.items.0.current_quantity', fn ($v) => (float) $v === 17.0)
                ->where('stocktake.items.0.system_quantity', fn ($v) => (float) $v === 20.0));

        // POST bị từ chối, phiếu vẫn DRAFT, tồn không đổi
        $movementsBefore = \App\Models\StockMovement::count();
        $this->actingAs($this->admin)->post("/admin/stocktake/{$stocktake->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertSame('draft', $stocktake->fresh()->status);
        $this->assertEqualsWithDelta(17.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame($movementsBefore, \App\Models\StockMovement::count());
        $this->assertSame(0, $this->adjustmentMovements()->count());

        // Người dùng chủ động lưu lại phiếu để chụp lại snapshot
        $this->actingAs($this->admin)->put("/admin/stocktake/{$stocktake->id}", $this->payload())
            ->assertRedirect("/admin/stocktake/{$stocktake->id}");

        $this->actingAs($this->admin)->get("/admin/stocktake/{$stocktake->id}")
            ->assertInertia(fn ($p) => $p
                ->where('stocktake.has_stale_items', false)
                ->where('stocktake.items.0.system_quantity', fn ($v) => (float) $v === 17.0)
                ->where('stocktake.items.0.difference', fn ($v) => (float) $v === 1.0));

        // POST thành công
        $this->actingAs($this->admin)->post("/admin/stocktake/{$stocktake->id}/post")
            ->assertRedirect("/admin/stocktake/{$stocktake->id}")->assertSessionHas('success');

        $this->assertSame('posted', $stocktake->fresh()->status);
        $this->assertNotNull($stocktake->fresh()->posted_at);
        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertStockMatchesLots($this->whA, $this->productA);

        // POST lần 2 qua HTTP: bị chặn, tồn không đổi
        $this->actingAs($this->admin)->post("/admin/stocktake/{$stocktake->id}/post")
            ->assertSessionHasErrors('document');

        $this->assertEqualsWithDelta(18.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame(1, $this->adjustmentMovements()->count());
    }

    // ------------------------------------------------------------------ permission

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/stocktake')->assertRedirect(route('login'));
    }

    public function test_permission_view(): void
    {
        $stocktake = $this->draft();

        $this->actingAs($this->noAccess)->get('/admin/stocktake')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noAccess)->get("/admin/stocktake/{$stocktake->id}")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->get('/admin/stocktake')->assertOk();
        $this->actingAs($this->viewer)->get("/admin/stocktake/{$stocktake->id}")->assertOk();
    }

    public function test_permission_create(): void
    {
        $this->actingAs($this->viewer)->get('/admin/stocktake/create')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->post('/admin/stocktake', $this->payload())->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, Stocktake::count());
    }

    public function test_permission_update(): void
    {
        $stocktake = $this->draft();

        $this->actingAs($this->viewer)->get("/admin/stocktake/{$stocktake->id}/edit")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->put("/admin/stocktake/{$stocktake->id}", $this->payload(null, ['note' => 'Bị sửa']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('Kiểm kê test', $stocktake->fresh()->note);
    }

    public function test_permission_delete(): void
    {
        $stocktake = $this->draft();

        $this->actingAs($this->viewer)->delete("/admin/stocktake/{$stocktake->id}")->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('stocktakes', ['id' => $stocktake->id]);
    }

    public function test_permission_post(): void
    {
        $this->receive($this->whA, $this->productA, 20);
        $stocktake = $this->draft();

        // viewer và noPost (có view/create/update/delete nhưng KHÔNG có post) đều không được POST
        $this->actingAs($this->viewer)->post("/admin/stocktake/{$stocktake->id}/post")->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->noPost)->post("/admin/stocktake/{$stocktake->id}/post")->assertRedirect(route('admin.dashboard'));

        $this->assertSame('draft', $stocktake->fresh()->status);
        $this->assertEqualsWithDelta(20.0, $this->onHand($this->whA, $this->productA), 0.0005);
        $this->assertSame(0, $this->adjustmentMovements()->count());

        // noPost vẫn tạo được phiếu
        $this->actingAs($this->noPost)->post('/admin/stocktake', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(2, Stocktake::count());
    }
}