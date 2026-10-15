<?php

use App\Models\Customer;
use App\Models\Opportunity;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list customers', function () {
    $admin = adminUser();
    Customer::factory(3)->create(['account_manager_id' => $admin->id, 'is_lead' => false]);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?is_lead=0')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create company customer with contacts and addresses', function () {
    $admin = adminUser();

    $payload = [
        'type' => 'company',
        'company_name' => 'Acme Corp',
        'industry' => 'technology',
        'website' => 'https://acme.example.com',
        'contacts' => [
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@acme.com', 'is_primary' => true],
        ],
        'addresses' => [
            ['label' => 'HQ', 'type' => 'office', 'line_1' => '123 Main St', 'city' => 'Springfield', 'country' => 'USA', 'is_primary' => true],
        ],
    ];

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', $payload)
        ->assertCreated()
        ->assertJsonPath('data.type', 'company')
        ->assertJsonPath('data.is_lead', true)
        ->assertJsonPath('data.lead_status', 'new');
});

test('admin can create individual customer', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'type' => 'individual',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'individual');
});

test('creating company without company_name returns 422', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', ['type' => 'company'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);
});

test('admin can show customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id);
});

test('show customer includes contacts and addresses entries', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'account_manager_id' => $admin->id,
        'contacts' => [
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@acme.com', 'is_primary' => true],
        ],
        'addresses' => [
            ['label' => 'HQ', 'type' => 'office', 'line_1' => '123 Main St', 'city' => 'Springfield', 'country' => 'USA', 'is_primary' => true],
        ],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.contacts.0.first_name', 'Jane')
        ->assertJsonPath('data.contacts.0.is_primary', true)
        ->assertJsonPath('data.addresses.0.label', 'HQ')
        ->assertJsonPath('data.addresses.0.city', 'Springfield');
});

test('creating customer with contacts populates contact snapshot', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'type' => 'company',
            'company_name' => 'Snapshot Corp',
            'contacts' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@acme.com', 'phone' => '+10000000001'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contact_name', 'Jane Doe')
        ->assertJsonPath('data.contact_email', 'jane@acme.com')
        ->assertJsonPath('data.contact_phone', '+10000000001');
});

test('explicit contact snapshot wins over contacts entries on create', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'type' => 'company',
            'company_name' => 'Explicit Corp',
            'contact_email' => 'boss@explicit.com',
            'contacts' => [
                ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@acme.com'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contact_email', 'boss@explicit.com');
});

test('updating contacts refreshes contact snapshot', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'account_manager_id' => $admin->id,
        'contacts' => [['first_name' => 'Old', 'last_name' => 'Name', 'email' => 'old@example.com']],
        'contact_email' => 'old@example.com',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", [
            'contacts' => [['first_name' => 'New', 'last_name' => 'Name', 'email' => 'new@example.com']],
        ])
        ->assertOk()
        ->assertJsonPath('data.contact_name', 'New Name')
        ->assertJsonPath('data.contact_email', 'new@example.com');
});

test('updating without contacts keeps contact snapshot', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'account_manager_id' => $admin->id,
        'contacts' => [['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@acme.com']],
        'contact_email' => 'jane@acme.com',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['notes' => 'Just a note'])
        ->assertOk()
        ->assertJsonPath('data.contact_email', 'jane@acme.com');
});

test('admin can set account manager on create', function () {
    $admin = adminUser();
    $manager = managerUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'type' => 'company',
            'company_name' => 'Managed Corp',
            'account_manager_id' => $manager->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.account_manager.id', $manager->id);
});

test('admin can change and clear account manager on update', function () {
    $admin = adminUser();
    $manager = managerUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['account_manager_id' => $manager->id])
        ->assertOk()
        ->assertJsonPath('data.account_manager.id', $manager->id);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['account_manager_id' => null])
        ->assertOk()
        ->assertJsonPath('data.account_manager', null);
});

test('admin can update customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", [
            'contact_name' => 'New Contact Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.contact_name', 'New Contact Name');
});

test('admin can delete customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

// ── Lead status transitions ──────────────────────────────────────────────────

test('lead customer can transition new to contacted', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['lead_status' => 'new', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/lead-status", ['lead_status' => 'contacted'])
        ->assertOk()
        ->assertJsonPath('data.lead_status', 'contacted');
});

test('invalid lead transition returns 422', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['lead_status' => 'new', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/lead-status", ['lead_status' => 'proposal'])
        ->assertUnprocessable();
});

// ── Conversion via won opportunity ───────────────────────────────────────────

test('customer is converted when opportunity is marked won', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['lead_status' => 'negotiation', 'created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'stage' => 'negotiation',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/stage", ['stage' => 'won'])
        ->assertOk();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_lead' => false]);
});

// ── Filters ──────────────────────────────────────────────────────────────────

test('admin can filter customers by status', function () {
    $admin = adminUser();
    Customer::factory()->customer()->create(['account_manager_id' => $admin->id, 'status' => 'active']);
    Customer::factory()->customer()->create(['account_manager_id' => $admin->id, 'status' => 'at_risk']);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?is_lead=0&status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can filter leads vs customers via is_lead', function () {
    $admin = adminUser();
    Customer::factory()->lead()->create(['created_by' => $admin->id]);
    Customer::factory()->customer()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)->getJson('/api/v1/customers?is_lead=1')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson('/api/v1/customers?is_lead=0')->assertOk()->assertJsonCount(1, 'data');
});

test('admin can filter by type', function () {
    $admin = adminUser();
    Customer::factory()->create(['type' => 'company', 'company_name' => 'TypeCo', 'created_by' => $admin->id]);
    Customer::factory()->individual()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?type=company&search=TypeCo')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can search customers by company name', function () {
    $admin = adminUser();
    Customer::factory()->create(['type' => 'company', 'company_name' => 'FindMe Corp', 'account_manager_id' => $admin->id]);
    Customer::factory()->create(['type' => 'company', 'company_name' => 'Other Company', 'account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?search=FindMe')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can search customers by contacts JSON', function () {
    $admin = adminUser();
    Customer::factory()->create([
        'account_manager_id' => $admin->id,
        'contacts' => [['first_name' => 'UniqueContact99', 'last_name' => 'X', 'email' => 'uc99@example.com']],
    ]);
    Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?search=UniqueContact99')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can search customers by addresses JSON', function () {
    $admin = adminUser();
    Customer::factory()->create([
        'account_manager_id' => $admin->id,
        'addresses' => [['label' => 'HQ', 'city' => 'UniqueCity99', 'country' => 'USA']],
    ]);
    Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/customers?search=UniqueCity99')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on customers returns 401', function () {
    $this->getJson('/api/v1/customers')->assertUnauthorized();
});

test('sales_rep cannot delete customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('update customer with invalid status returns 422', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}", ['status' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
