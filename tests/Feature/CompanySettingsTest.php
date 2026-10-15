<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── GET /api/v1/company/settings ──────────────────────────────────────────────

test('admin can view company settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/company/settings')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'company_name',
            'company_email',
            'company_phone',
            'company_website',
            'company_tax_number',
            'company_currency',
            'company_timezone',
            'address',
            'logo_url',
        ]]);
});

test('manager can view company settings', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/company/settings')
        ->assertOk();
});

// ── PUT /api/v1/company/settings ──────────────────────────────────────────────

test('admin can update company settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'company_name' => 'ACME Corp',
            'company_email' => 'info@acme.com',
            'company_currency' => 'USD',
            'company_timezone' => 'America/New_York',
            'address' => [
                'city' => 'New York',
                'country' => 'US',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.company_name', 'ACME Corp')
        ->assertJsonPath('data.company_currency', 'USD')
        ->assertJsonPath('data.address.city', 'New York');
});

test('update persists values to database', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['company_name' => 'Stored Name'])
        ->assertOk();

    expect(CompanySetting::getValue('company_name'))->toBe('Stored Name');
});

test('update company settings fails with invalid email', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'company_email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_email']);
});

test('update company settings fails with invalid currency length', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'company_currency' => 'INVALID',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_currency']);
});

// ── Currency default + backfill ────────────────────────────────────────────────

test('changing company currency backfills existing records', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create(['customer_id' => $customer->id, 'created_by' => $admin->id, 'currency' => 'USD']);
    $product = Product::factory()->create(['created_by' => $admin->id, 'currency' => 'GBP']);
    $quotation = Quotation::factory()->create(['currency' => 'USD']);
    $invoice = Invoice::create([
        'opportunity_id' => $opportunity->id,
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'invoice_number' => 'INV-0001',
        'currency' => 'USD',
        'total' => 100,
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['company_currency' => 'EUR'])
        ->assertOk()
        ->assertJsonPath('data.company_currency', 'EUR');

    expect($opportunity->fresh()->currency)->toBe('EUR')
        ->and($product->fresh()->currency)->toBe('EUR')
        ->and($quotation->fresh()->currency)->toBe('EUR')
        ->and($invoice->fresh()->currency)->toBe('EUR');
});

test('lowercase currency code is stored uppercase', function () {
    $this->actingAs(adminUser())
        ->putJson('/api/v1/company/settings', ['company_currency' => 'eur'])
        ->assertOk()
        ->assertJsonPath('data.company_currency', 'EUR');
});

test('updating other settings does not touch record currencies', function () {
    $admin = adminUser();
    $opportunity = Opportunity::factory()->create(['created_by' => $admin->id, 'currency' => 'GBP']);

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', ['company_name' => 'ACME'])
        ->assertOk();

    expect($opportunity->fresh()->currency)->toBe('GBP');
});

// ── POST /api/v1/company/settings/logo ────────────────────────────────────────

test('admin can upload company logo', function () {
    Storage::fake('public');

    $admin = adminUser();
    $logo = UploadedFile::fake()->image('logo.png', 300, 300);

    $this->actingAs($admin)
        ->postJson('/api/v1/company/settings/logo', ['logo' => $logo])
        ->assertOk()
        ->assertJsonStructure(['data' => ['logo_url']]);

    $logoUrl = $this->actingAs($admin)
        ->getJson('/api/v1/company/settings')
        ->json('data.logo_url');

    expect($logoUrl)->not->toBeNull();
});

test('logo upload fails when file is too large', function () {
    Storage::fake('public');

    $admin = adminUser();
    $bigLogo = UploadedFile::fake()->image('logo.jpg')->size(3000);

    $this->actingAs($admin)
        ->postJson('/api/v1/company/settings/logo', ['logo' => $bigLogo])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo']);
});

test('logo upload fails when no file is provided', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/company/settings/logo', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo']);
});

// ── DELETE /api/v1/company/settings/logo ─────────────────────────────────────

test('admin can delete company logo', function () {
    Storage::fake('public');

    $admin = adminUser();
    $logo = UploadedFile::fake()->image('logo.png');

    $this->actingAs($admin)
        ->postJson('/api/v1/company/settings/logo', ['logo' => $logo])
        ->assertOk();

    $this->actingAs($admin)
        ->deleteJson('/api/v1/company/settings/logo')
        ->assertOk();

    expect(CompanySetting::getValue('company_logo_path'))->toBeNull();
});

// ── Unauthenticated ───────────────────────────────────────────────────────────

test('unauthenticated request to company settings returns 401', function () {
    $this->getJson('/api/v1/company/settings')->assertUnauthorized();
});
