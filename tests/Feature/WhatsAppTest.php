<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

function whatsappQuotationSetup(): array
{
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'type' => 'company',
        'company_name' => 'WhatsApp Buyer',
        'created_by' => $admin->id,
        'account_manager_id' => $admin->id,
        'phone' => '+1-555-0200',
        'contacts' => [[
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'phone' => '+1-555-0201',
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
    $opportunity->products()->attach($product, ['quantity' => 1, 'unit_price' => 100]);

    return [$admin, $customer, $opportunity];
}

it('prepares a quotation WhatsApp message with a temporary PDF link', function () {
    [$admin, $customer, $opportunity] = whatsappQuotationSetup();
    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $response = $this->postJson(
        "/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotation['id']}/whatsapp/prepare"
    );

    $response->assertOk()
        ->assertJsonPath('data.phone', '15550201')
        ->assertJsonPath('data.document_number', $quotation['quotation_number'])
        ->assertJsonPath('data.cloud_api_available', false);

    expect($response->json('data.message'))->toContain($quotation['quotation_number'])
        ->and($response->json('data.pdf_url'))->toContain('signature=')
        ->and($response->json('data.click_to_chat_url'))->toStartWith('https://wa.me/15550201?text=');
});

it('rejects preparing a quotation for a different customer', function () {
    [$admin, $customer, $opportunity] = whatsappQuotationSetup();
    $otherCustomer = Customer::factory()->create(['created_by' => $admin->id]);
    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $this->postJson(
        "/api/v1/customers/{$otherCustomer->id}/opportunities/{$opportunity->id}/quotations/{$quotation['id']}/whatsapp/prepare"
    )->assertNotFound();
});

it('sends a direct WhatsApp message and logs it to customer and opportunity activities', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'created_by' => $admin->id,
        'account_manager_id' => $admin->id,
        'phone' => '+1 (555) 222-3344',
    ]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/whatsapp/send", [
        'phone' => '+1 (555) 222-3344',
        'message' => 'Your updated quotation is ready.',
        'subject' => 'Quotation follow-up',
    ])->assertOk()
        ->assertJsonPath('data.dispatched_via', 'click_to_chat')
        ->assertJsonPath('data.phone', '15552223344');

    $this->assertDatabaseHas('customer_activities', [
        'customer_id' => $customer->id,
        'user_id' => $admin->id,
        'type' => 'whatsapp',
        'subject' => 'Quotation follow-up',
        'description' => 'Your updated quotation is ready.',
    ]);
    $this->assertDatabaseHas('opportunity_activities', [
        'opportunity_id' => $opportunity->id,
        'user_id' => $admin->id,
        'type' => 'whatsapp',
        'subject' => 'Quotation follow-up',
    ]);

    expect(CustomerActivity::where('customer_id', $customer->id)->where('type', 'whatsapp')->first()->completed_at)->not->toBeNull()
        ->and($customer->fresh()->last_contacted_at)->not->toBeNull();
});

it('does not log a WhatsApp activity if Cloud API delivery fails', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'created_by' => $admin->id,
        'account_manager_id' => $admin->id,
    ]);
    CompanySetting::setMany([
        'whatsapp_access_token' => 'test-token',
        'whatsapp_phone_number_id' => 'test-phone-id',
    ]);
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Provider unavailable']], 500),
    ]);

    $this->actingAs($admin)->postJson("/api/v1/customers/{$customer->id}/whatsapp/send", [
        'phone' => '+1 (555) 222-3344',
        'message' => 'Please see the update.',
        'via_cloud_api' => true,
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('customer_activities', [
        'customer_id' => $customer->id,
        'type' => 'whatsapp',
    ]);
});

it('serves valid signed quotation and invoice PDF links and rejects expired links', function () {
    [$admin, $customer, $opportunity] = whatsappQuotationSetup();
    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $quotationUrl = app(WhatsAppService::class)->quotationSignedUrl(Quotation::findOrFail($quotation['id']));
    $this->get($quotationUrl)->assertOk()->assertHeader('content-type', 'application/pdf');

    $expiredQuotationUrl = URL::temporarySignedRoute(
        'public.quotations.download',
        now()->subMinute(),
        ['quotation' => $quotation['id']]
    );
    $this->get($expiredQuotationUrl)->assertForbidden();

    $opportunity->update(['stage' => 'won']);
    $invoice = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->json('data');
    $invoiceUrl = app(WhatsAppService::class)->invoiceSignedUrl(Invoice::findOrFail($invoice['id']));

    $this->get($invoiceUrl)->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('rejects WhatsApp sending for a user without activity permissions', function () {
    $user = User::factory()->create(['is_active' => true]);
    $customer = Customer::factory()->create();

    $this->actingAs($user)->postJson("/api/v1/customers/{$customer->id}/whatsapp/send", [
        'phone' => '+1 555 222 3344',
        'message' => 'Hello',
    ])->assertForbidden();
});

it('rejects invalid WhatsApp phone numbers', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create([
        'created_by' => $admin->id,
        'account_manager_id' => $admin->id,
    ]);

    $this->actingAs($admin)->postJson("/api/v1/customers/{$customer->id}/whatsapp/send", [
        'phone' => 'not-a-phone',
        'message' => 'Hello',
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');
});
