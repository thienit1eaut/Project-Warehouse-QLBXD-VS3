<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase, CustomerFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCustomerFixtures();
    }

    private function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'khach@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides);
    }

    // ---- Tạo account (back-office) -------------------------------------------

    public function test_admin_can_create_account_for_customer(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload())
            ->assertRedirect("/admin/customers/{$customer->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_accounts', [
            'customer_id' => $customer->id,
            'email' => 'khach@example.com',
            'status' => 'active',
        ]);
    }

    public function test_account_email_is_independent_from_customer_email(): void
    {
        $customer = $this->makeCustomer(['email' => 'lienhe@example.com']);

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload([
            'email' => 'dangky@example.com',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('lienhe@example.com', $customer->fresh()->email);
        $this->assertSame('dangky@example.com', $customer->fresh()->account->email);
    }

    public function test_customer_cannot_have_two_accounts(): void
    {
        $customer = $this->makeCustomer();
        $this->makeAccount($customer);

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload())
            ->assertSessionHasErrors('account');

        $this->assertSame(1, CustomerAccount::count());
    }

    public function test_database_enforces_one_account_per_customer(): void
    {
        $customer = $this->makeCustomer();
        $this->makeAccount($customer);

        $this->expectException(QueryException::class);

        CustomerAccount::create([
            'customer_id' => $customer->id,
            'email' => 'khac@example.com',
            'password_hash' => 'Secret123',
            'status' => 'active',
        ]);
    }

    public function test_account_email_must_be_unique(): void
    {
        $first = $this->makeCustomer(['customer_code' => 'KH001']);
        $this->makeAccount($first, ['email' => 'khach@example.com']);
        $second = $this->makeCustomer(['customer_code' => 'KH002']);

        $this->actingAs($this->admin)->post("/admin/customers/{$second->id}/account", $this->accountPayload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, CustomerAccount::count());
    }

    public function test_database_enforces_unique_account_email(): void
    {
        $first = $this->makeCustomer(['customer_code' => 'KH001']);
        $second = $this->makeCustomer(['customer_code' => 'KH002']);
        $this->makeAccount($first, ['email' => 'khach@example.com']);

        $this->expectException(QueryException::class);

        $this->makeAccount($second, ['email' => 'khach@example.com']);
    }

    // ---- Password ------------------------------------------------------------

    public function test_password_is_hashed_and_not_stored_as_plaintext(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload());

        $account = CustomerAccount::firstOrFail();
        $this->assertNotSame('Secret123', $account->password_hash);
        $this->assertTrue(Hash::check('Secret123', $account->password_hash));
        $this->assertDatabaseMissing('customer_accounts', ['password_hash' => 'Secret123']);
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload([
            'password' => 'abc', 'password_confirmation' => 'abc',
        ]))->assertSessionHasErrors('password');

        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload([
            'password_confirmation' => 'Khac12345',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, CustomerAccount::count());
    }

    public function test_password_is_never_exposed_in_responses(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload());

        $response = $this->actingAs($this->admin)->get("/admin/customers/{$customer->id}");

        $response->assertOk()
            ->assertDontSee('Secret123')
            ->assertInertia(fn ($page) => $page
                ->where('customer.account.email', 'khach@example.com')
                ->missing('customer.account.password')
                ->missing('customer.account.password_hash'));

        $this->assertStringNotContainsString(
            CustomerAccount::firstOrFail()->getRawOriginal('password_hash'),
            $response->getContent()
        );
    }

    // ---- Status / verification fields ----------------------------------------

    public function test_account_status_can_be_active_or_inactive(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($this->admin)->post("/admin/customers/{$customer->id}/account", $this->accountPayload([
            'status' => 'inactive',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('inactive', $customer->fresh()->account->status);

        $this->actingAs($this->admin)->patch("/admin/customers/{$customer->id}/account/status", ['status' => 'active'])
            ->assertSessionHas('success');
        $this->assertSame('active', $customer->fresh()->account->status);

        $this->actingAs($this->admin)->patch("/admin/customers/{$customer->id}/account/status", ['status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $customer->fresh()->account->status);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $this->makeAccount($customer);

        $this->actingAs($this->admin)->patch("/admin/customers/{$customer->id}/account/status", ['status' => 'banned'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $customer->fresh()->account->status);
    }

    public function test_status_change_without_account_fails(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin)->patch("/admin/customers/{$customer->id}/account/status", ['status' => 'inactive'])
            ->assertSessionHasErrors('account');
    }

    public function test_email_verified_at_is_nullable(): void
    {
        $customer = $this->makeCustomer();
        $account = $this->makeAccount($customer);

        $this->assertNull($account->fresh()->email_verified_at);

        $account->update(['email_verified_at' => now()]);
        $this->assertNotNull($account->fresh()->email_verified_at);
    }

    // ---- Permission ----------------------------------------------------------

    public function test_account_actions_require_customer_update_permission(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->viewer)->post("/admin/customers/{$customer->id}/account", $this->accountPayload())
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, CustomerAccount::count());

        $this->makeAccount($customer);

        $this->actingAs($this->viewer)->patch("/admin/customers/{$customer->id}/account/status", ['status' => 'inactive'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('active', $customer->fresh()->account->status);
    }

    // ---- Phạm vi: back-office, KHÔNG có đăng nhập khách ----------------------

    public function test_no_customer_login_portal_or_api_exists(): void
    {
        $this->get('/customer/login')->assertNotFound();
        $this->get('/customer/account')->assertNotFound();
        $this->post('/customer/login')->assertNotFound();
        $this->post('/api/customer/login')->assertNotFound();

        $this->assertArrayNotHasKey('customer', config('auth.guards'));
        $this->assertArrayNotHasKey('customer_accounts', config('auth.providers'));
    }

    public function test_admin_authentication_is_unaffected(): void
    {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('web');
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_customer_account_credentials_cannot_login_to_back_office(): void
    {
        $customer = $this->makeCustomer();
        $this->makeAccount($customer, ['email' => 'khach@example.com']);

        $this->post('/login', ['email' => 'khach@example.com', 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }
}