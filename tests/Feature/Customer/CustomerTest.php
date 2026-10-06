<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase, CustomerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCustomerFixtures();
    }

    public function test_admin_can_create_customer(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/customers', $this->customerPayload());

        $customer = Customer::where('customer_code', 'KH001')->firstOrFail();
        $response->assertRedirect("/admin/customers/{$customer->id}");
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', ['customer_code' => 'KH001', 'phone' => '0901234567']);
    }

    public function test_admin_can_update_customer(): void
    {
        $customer = $this->makeCustomer(['customer_code' => 'KH001', 'phone' => '0901234567']);

        $this->actingAs($this->admin)->put(
            "/admin/customers/{$customer->id}",
            $this->customerPayload(['name' => 'Tên mới', 'customer_type' => 'business'])
        )->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Tên mới', 'customer_type' => 'business']);
    }

    public function test_update_may_keep_own_code_phone_and_email(): void
    {
        $customer = $this->makeCustomer(['customer_code' => 'KH001', 'phone' => '0901234567', 'email' => 'a@example.com']);

        $this->actingAs($this->admin)->put("/admin/customers/{$customer->id}", $this->customerPayload())
            ->assertSessionHasNoErrors();
    }

    public function test_customer_code_is_required_and_unique(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/customers', $this->customerPayload(['customer_code' => '']))
            ->assertSessionHasErrors('customer_code');

        $this->makeCustomer(['customer_code' => 'KH001']);

        $this->actingAs($this->admin)
            ->post('/admin/customers', $this->customerPayload(['phone' => '0911111111', 'email' => 'b@example.com']))
            ->assertSessionHasErrors('customer_code');

        $this->assertSame(1, Customer::count());
    }

    public function test_phone_and_email_must_be_unique_when_present(): void
    {
        $this->makeCustomer(['customer_code' => 'KH999', 'phone' => '0901234567', 'email' => 'a@example.com']);

        $this->actingAs($this->admin)
            ->post('/admin/customers', $this->customerPayload(['email' => 'b@example.com']))
            ->assertSessionHasErrors('phone');

        $this->actingAs($this->admin)
            ->post('/admin/customers', $this->customerPayload(['phone' => '0911111111']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Customer::count());
    }

    public function test_phone_and_email_are_optional_and_multiple_nulls_allowed(): void
    {
        foreach (['KH001', 'KH002'] as $code) {
            $this->actingAs($this->admin)->post('/admin/customers', $this->customerPayload([
                'customer_code' => $code, 'phone' => '', 'email' => '',
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Customer::count());
        $this->assertNull(Customer::where('customer_code', 'KH001')->first()->phone);
    }

    public function test_validation_rejects_missing_name_and_invalid_type_and_email(): void
    {
        $this->actingAs($this->admin)->post('/admin/customers', $this->customerPayload([
            'name' => '', 'customer_type' => 'vip', 'email' => 'khong-hop-le',
        ]))->assertSessionHasErrors(['name', 'customer_type', 'email']);

        $this->assertSame(0, Customer::count());
    }

    public function test_unused_customer_can_be_deleted(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin)->delete("/admin/customers/{$customer->id}")
            ->assertRedirect('/admin/customers');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_account_cannot_be_deleted(): void
    {
        $customer = $this->makeCustomer();
        $this->makeAccount($customer);

        $this->actingAs($this->admin)->delete("/admin/customers/{$customer->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customer_accounts', ['customer_id' => $customer->id]);
    }

    public function test_index_and_show_render(): void
    {
        $withAccount = $this->makeCustomer(['customer_code' => 'KH001']);
        $this->makeAccount($withAccount);
        $withoutAccount = $this->makeCustomer(['customer_code' => 'KH002']);

        $this->actingAs($this->admin)->get('/admin/customers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Index')
                ->has('customers.data', 2));

        $this->actingAs($this->admin)->get("/admin/customers/{$withAccount->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Show')
                ->where('customer.account.status', 'active'));

        $this->actingAs($this->admin)->get("/admin/customers/{$withoutAccount->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('customer.account', null));
    }

    // ---- Permission ----------------------------------------------------------

    public function test_permission_view(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->viewer)->get('/admin/customers')->assertOk();
        $this->actingAs($this->viewer)->get("/admin/customers/{$customer->id}")->assertOk();
        $this->actingAs($this->noAccess)->get('/admin/customers')->assertRedirect(route('admin.dashboard'));
    }

    public function test_permission_create(): void
    {
        $this->actingAs($this->viewer)->get('/admin/customers/create')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->post('/admin/customers', $this->customerPayload())
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, Customer::count());
    }

    public function test_permission_update(): void
    {
        $customer = $this->makeCustomer(['name' => 'Gốc']);

        $this->actingAs($this->viewer)->get("/admin/customers/{$customer->id}/edit")
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->viewer)->put("/admin/customers/{$customer->id}", $this->customerPayload(['name' => 'Bị sửa']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('Gốc', $customer->fresh()->name);
    }

    public function test_permission_delete(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->viewer)->delete("/admin/customers/{$customer->id}")
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    // ---- Relationship / future compatibility ---------------------------------

    public function test_customer_can_exist_without_account(): void
    {
        $customer = $this->makeCustomer();

        $this->assertNull($customer->account);
        $this->assertSame(0, \App\Models\CustomerAccount::count());
    }

    public function test_customer_has_one_account_and_account_belongs_to_customer(): void
    {
        $customer = $this->makeCustomer();
        $account = $this->makeAccount($customer);

        $this->assertTrue($customer->account->is($account));
        $this->assertTrue($account->customer->is($customer));
    }
}