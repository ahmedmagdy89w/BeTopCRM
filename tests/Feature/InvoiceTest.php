<?php

use App\Models\Invoice;
use App\Models\User;

it('creates one invoice for a won opportunity linked to its quotation', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();

    $quotation = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data');

    $opportunity->update(['stage' => 'won']);

    $invoice = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.quotation_id', $quotation['id'])
        ->assertJsonPath('data.company.company_name', 'Northwind CRM')
        ->assertJsonPath('data.customer.name', 'Buyer Industries')
        ->assertJsonPath('data.customer.contact_name', 'Jane Buyer')
        ->assertJsonStructure(['data' => ['invoice_number', 'items', 'total']])
        ->json('data');

    expect(Invoice::where('opportunity_id', $opportunity->id)->count())->toBe(1);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertOk()
        ->assertJsonPath('data.id', $invoice['id']);

    $downloadResponse = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice/{$invoice['id']}/pdf");
    $downloadResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($invoice['invoice_number'].'.pdf');

    $previewResponse = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice/{$invoice['id']}/preview");
    $previewResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect($previewResponse->headers->get('content-disposition'))->toContain('inline');

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice');

    $this->actingAs($admin)
        ->getJson('/api/v1/invoices?search=Buyer&status=issued&date_from='.now()->toDateString().'&date_to='.now()->toDateString().'&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.document_type', 'invoice')
        ->assertJsonPath('data.0.id', $invoice['id'])
        ->assertJsonPath('data.0.invoice_number', $invoice['invoice_number'])
        ->assertJsonPath('data.0.customer.display_name', 'Buyer Industries')
        ->assertJsonPath('data.0.opportunity.id', $opportunity->id)
        ->assertJsonStructure([
            'data' => [['created_at', 'status', 'total', 'currency']],
            'meta' => ['total', 'current_page', 'per_page'],
        ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/opportunities/{$opportunity->id}/timeline")
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'invoice',
            'description' => $invoice['invoice_number'],
        ]);
});

it('requires permission to list invoices globally', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->getJson('/api/v1/invoices')
        ->assertForbidden();
});

it('rejects invoice creation for an opportunity that is not won', function () {
    [$admin, $customer, $opportunity] = quotationOpportunity();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('opportunity');
});
