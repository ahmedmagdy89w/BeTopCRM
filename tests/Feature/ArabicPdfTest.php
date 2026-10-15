<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\ArabicPdfService;
use App\Services\SalesDocumentService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\App;

function arabicQuotationSetup(): array
{
    $admin = adminUser();
    CompanySetting::setMany([
        'company_name' => 'شركة الاختبار',
        'company_email' => 'hello@northwind.test',
        'company_phone' => '+1-555-0100',
        'address_city' => 'الرياض',
        'address_country' => 'السعودية',
    ]);
    $customer = Customer::factory()->create([
        'type' => 'company',
        'company_name' => 'Buyer Industries',
        'created_by' => $admin->id,
    ]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'currency' => 'USD',
    ]);
    $product = Product::factory()->create([
        'name' => 'خدمة استشارات',
        'created_by' => $admin->id,
        'unit_price' => 1250,
        'currency' => 'USD',
    ]);
    $opportunity->products()->attach($product, [
        'quantity' => 2,
        'unit_price' => 1250,
    ]);

    return [$admin, $customer, $opportunity];
}

function totalRow(string $html): string
{
    preg_match('/<tr class="total">(.*?)<\/tr>/s', $html, $matches);

    return html_entity_decode($matches[1] ?? '', ENT_QUOTES, 'UTF-8');
}

test('quotation pdf renders shaped arabic instead of reversed letters', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();
    App::setLocale('ar');

    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    $response = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotationId}/pdf", ['Accept-Language' => 'ar']);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $body = $response->getContent();
    expect($body)->not->toBeFalse()->not->toBeEmpty();

    // The shaped product name (visual-order presentation forms) must be
    // embedded; the raw logical-order string must not leak through.
    // (PDF streams are compressed and DOM output is entity-encoded,
    // so assert on the decoded shaped HTML instead.)
    $quotation = Quotation::with(['items', 'customer', 'opportunity'])->findOrFail($quotationId);
    $document = app(SalesDocumentService::class)->forCustomer($quotation->customer);
    App::setLocale('ar');
    $html = html_entity_decode(ArabicPdfService::renderShapedHtml('quotations.pdf', compact('quotation', 'document')), ENT_QUOTES, 'UTF-8');

    $shaped = ArabicPdfService::shapeText('خدمة استشارات');
    expect($shaped)->not->toBe('خدمة استشارات')
        ->and($html)->toContain('lang="ar"')
        ->and($html)->toContain($shaped)
        ->and($html)->not->toContain('خدمة استشارات');
});

test('quotation default terms follow the display locale instead of creation locale', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();

    // Created without terms (frontend no longer snapshots the default).
    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    expect(Quotation::findOrFail($quotationId)->terms)->toBeNull();

    App::setLocale('ar');
    $arHtml = html_entity_decode(ArabicPdfService::renderShapedHtml('quotations.pdf', [
        'quotation' => Quotation::with(['items', 'customer', 'opportunity'])->findOrFail($quotationId),
        'document' => app(SalesDocumentService::class)->forCustomer($customer),
    ]), ENT_QUOTES, 'UTF-8');
    expect($arHtml)->toContain(ArabicPdfService::shapeText(__('messages.quotation_default_terms')));

    App::setLocale('en');
    $enHtml = ArabicPdfService::renderShapedHtml('quotations.pdf', [
        'quotation' => Quotation::with(['items', 'customer', 'opportunity'])->findOrFail($quotationId),
        'document' => app(SalesDocumentService::class)->forCustomer($customer),
    ]);
    expect($enHtml)->toContain('Prices are valid until the date shown above');
});

test('pdf embeds the cairo typeface including bold', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();

    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    $body = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations/{$quotationId}/pdf")
        ->assertOk()
        ->getContent();

    // Font descriptors live in uncompressed PDF objects.
    expect($body)->toContain('Cairo-Regular')
        ->and($body)->toContain('Cairo-Bold');
});

test('totals mirror cell order in rtl so labels come first', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated();

    $opportunity->update(['stage' => 'won']);

    $invoiceId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->json('data.id');

    $invoice = Invoice::with(['items', 'customer', 'opportunity'])->findOrFail($invoiceId);

    $document = app(SalesDocumentService::class)->forCustomer($customer);

    App::setLocale('ar');
    $arRow = totalRow(ArabicPdfService::renderShapedHtml('invoices.pdf', compact('invoice', 'document')));
    $arLabel = ArabicPdfService::shapeText(__('messages.invoice_total'));
    expect($arRow)->toContain('USD 2,500.00')
        ->and(strpos($arRow, 'USD 2,500.00') < strpos($arRow, $arLabel))->toBeTrue();

    App::setLocale('en');
    $enRow = totalRow(ArabicPdfService::renderShapedHtml('invoices.pdf', compact('invoice', 'document')));
    expect(strpos($enRow, '>Total<') < strpos($enRow, 'USD 2,500.00'))->toBeTrue();
});

test('signed quotation link carries the sender locale and downloads as guest', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();

    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    App::setLocale('ar');
    $signedUrl = app(WhatsAppService::class)
        ->quotationSignedUrl(Quotation::findOrFail($quotationId));

    expect($signedUrl)->toContain('lang=ar');

    // Guest (no session, browser-like headers): signature must stay valid
    // with the extra lang param and the PDF must render in Arabic.
    $response = $this->get($signedUrl);
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('invoice pdf renders shaped arabic instead of reversed letters', function () {
    [$admin, $customer, $opportunity] = arabicQuotationSetup();
    App::setLocale('ar');

    $quotationId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/quotations")
        ->assertCreated()
        ->json('data.id');

    $opportunity->update(['stage' => 'won']);

    $invoiceId = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice")
        ->assertCreated()
        ->json('data.id');

    $invoice = Invoice::findOrFail($invoiceId);

    $response = $this->actingAs($admin)
        ->get("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}/invoice/{$invoice->id}/pdf", ['Accept-Language' => 'ar']);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $body = $response->getContent();
    expect($body)->not->toBeFalse()->not->toBeEmpty();

    App::setLocale('ar');
    $html = html_entity_decode(ArabicPdfService::renderShapedHtml('invoices.pdf', ['invoice' => $invoice, 'document' => app(SalesDocumentService::class)->forCustomer($invoice->customer)]), ENT_QUOTES, 'UTF-8');
    $shaped = ArabicPdfService::shapeText('خدمة استشارات');
    expect($html)->toContain('lang="ar"')
        ->and($html)->toContain($shaped)
        ->and($html)->not->toContain('خدمة استشارات');
});
