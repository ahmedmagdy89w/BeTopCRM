<?php

use App\Mail\QuotationMail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Tax;
use App\Models\User;
use App\Services\SalesDocumentService;
use Illuminate\Support\Facades\Mail;

function quotationOpportunity(): array
{
    $admin = adminUser();
    CompanySetting::setMany([
        'company_name' => 'Northwind CRM',
        'company_email' => 'hello@northwind.test',
        'company_phone' => '+1-555-0100',
        'company_website' => 'https://northwind.test',
        'company_tax_number' => 'NW-12345',
        'company_logo_path' => 'company/logo/northwind.png',
        'address_street' => '100 Main Street',
        'address_city' => 'New York',
        'address_state' => 'NY',
        'address_country' => 'US',
        'address_zip' => '10001',
    ]);
    $customer = Customer::factory()->create([
        'type' => 'company',
        'company_name' => 'Buyer Industries',
        'created_by' => $admin->id,
        'email' => 'accounts@buyer.test',
        'phone' => '+1-555-0200',
        'website' => 'https://buyer.test',
        'tax_id' => 'BUY-67890',
        'contacts' => [[
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@buyer.test',
            'phone' => '+1-555-0201',
            'title' => 'Procurement Manager',
            'is_primary' => true,
        ]],
        'addresses' => [[
            'label' => 'HQ',
            'line_1' => '200 Buyer Avenue',
            'line_2' => 'Floor 5',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02110',
            'country' => 'US',
            'is_primary' => true,
        ]],
    ]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'currency' => 'USD',
    ]);
    $product = Product::factory()->create([
        'created_by' => $admin->id,
        'unit_price' => 100,
        'currency' => 'USD',
    ]);
    $opportunity->products()->attach($product, [
        'quantity' => 2,
        'unit_price' => 90,
        'notes' => 'Configured package',
    ]);

    return [$admin, $customer, $opportunity];
}

it('creates a quotation from opportunity products with line discounts and taxes', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $product = $opportunity->products()->first();

    $tax = Tax::factory()->create(['name' => 'VAT 14', 'percent' => 14, 'created_by' => $admin->id]);
    $product->update(['tax_id' => $tax->id]);
    $opportunity->products()->updateExistingPivot($product->id, ['discount_amount' => 20]);

    $response = $this->actingAs($admin)->postJson(
        "/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations",
        [
            'valid_until' => now()->addDays(30)->toDateString(),
        ],
    );

    $response->assertCreated()
        ->assertJsonPath('data.quotation_number', 'QT-'.now()->format('Y').'-'.str_pad((string) $response->json('data.id'), 4, '0', STR_PAD_LEFT))
        ->assertJsonPath('data.subtotal', '180.00')
        ->assertJsonPath('data.discount_amount', '20.00')
        ->assertJsonPath('data.tax_amount', '22.40')
        ->assertJsonPath('data.total', '182.40')
        ->assertJsonPath('data.items.0.discount_amount', '20.00')
        ->assertJsonPath('data.items.0.tax_name', 'VAT 14')
        ->assertJsonPath('data.items.0.tax_percent', '14.00')
        ->assertJsonPath('data.items.0.tax_amount', '22.40')
        ->assertJsonPath('data.company.company_name', 'Northwind CRM')
        ->assertJsonPath('data.company.company_tax_number', 'NW-12345')
        ->assertJsonPath('data.customer.name', 'Buyer Industries')
        ->assertJsonPath('data.customer.contact_name', 'Jane Buyer')
        ->assertJsonPath('data.customer.email', 'jane@buyer.test')
        ->assertJsonPath('data.customer.address.line_1', '200 Buyer Avenue')
        ->assertJsonMissingPath('data.company.logo_file_path')
        ->assertJsonStructure(['data' => ['items' => [['product_name', 'quantity', 'unit_price', 'subtotal']]]]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertOk()
        ->assertJsonPath('data.0.company.company_name', 'Northwind CRM')
        ->assertJsonPath('data.0.customer.name', 'Buyer Industries');

    $downloadResponse = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$response->json('data.id')}/pdf");
    $downloadResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($response->json('data.quotation_number').'.pdf');

    $previewResponse = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$response->json('data.id')}/preview");
    $previewResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect($previewResponse->headers->get('content-disposition'))->toContain('inline');

    expect($response->json('data.company.logo_url'))->not->toBeNull()
        ->and(Quotation::first()->items)->toHaveCount(1);
});

it('lists quotations globally and filters by search and status', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin)
        ->getJson('/api/v1/quotations?search=Buyer&status=draft&date_from='.now()->toDateString().'&date_to='.now()->addDays(40)->toDateString().'&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.document_type', 'quotation')
        ->assertJsonPath('data.0.id', $quotation['id'])
        ->assertJsonPath('data.0.quotation_number', $quotation['quotation_number'])
        ->assertJsonPath('data.0.customer.display_name', 'Buyer Industries')
        ->assertJsonPath('data.0.opportunity.id', $opportunity->id)
        ->assertJsonStructure([
            'data' => [['created_at', 'status', 'total', 'currency']],
            'meta' => ['total', 'current_page', 'per_page'],
        ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/quotations?search=missing-document')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires permission to list quotations globally', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->getJson('/api/v1/quotations')
        ->assertForbidden();
});

it('queues a quotation pdf email and marks the quotation sent', function () {
    Mail::fake();
    [$admin, $customer, $opportunity] = quotationOpportunity();

    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotation['id']}/send")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent');

    Mail::assertQueued(QuotationMail::class, fn (QuotationMail $mail): bool => $mail->quotation->id === $quotation['id']
        && $mail->document['company']['company_name'] === 'Northwind CRM'
        && $mail->document['buyer']['name'] === 'Buyer Industries');
    expect(Quotation::find($quotation['id'])->status)->toBe('sent')
        ->and(Quotation::find($quotation['id'])->sent_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->getJson("/api/v1/opportunities/{$opportunity->id}/timeline")
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'quotation',
            'title' => __('messages.quotation_sent'),
            'description' => $quotation['quotation_number'],
        ]);
});

it('includes company and buyer details in the quotation pdf', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');
    $quotation = Quotation::with(['items', 'customer', 'opportunity'])->findOrFail($quotationId);
    $document = app(SalesDocumentService::class)->forCustomer($customer);

    $html = view('quotations.pdf', compact('quotation', 'document'))->render();
    $emailHtml = view('emails.quotation', compact('quotation', 'document'))->render();

    expect($html)
        ->toContain('Northwind CRM')
        ->toContain('100 Main Street')
        ->toContain('Buyer Industries')
        ->toContain('Jane Buyer')
        ->toContain('200 Buyer Avenue')
        ->and($emailHtml)
        ->toContain('Northwind CRM')
        ->toContain('Jane Buyer');
});

it('requires products before creating a quotation', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create(['customer_id' => $customer->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('products');
});

it('rejects quotation creation for a closed opportunity', function (string $stage) {
    [$admin, $customer, $opportunity] = quotationOpportunity();
    $opportunity->update(['stage' => $stage]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('opportunity');

    expect(Quotation::where('opportunity_id', $opportunity->id)->exists())->toBeFalse();
})->with(['won', 'lost']);
