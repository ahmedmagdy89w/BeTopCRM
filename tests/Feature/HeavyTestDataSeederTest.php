<?php

use App\Models\Customer;
use App\Models\Opportunity;
use Database\Seeders\HeavyTestDataSeeder;
use Illuminate\Support\Facades\DB;

// ── HeavyTestDataSeeder (small scale) ─────────────────────────────────────────

beforeEach(function () {
    putenv('HEAVY_SEED_SCALE=small');
    $this->seed(HeavyTestDataSeeder::class);
});

afterEach(function () {
    putenv('HEAVY_SEED_SCALE');
});

test('heavy seeder creates the core real-estate graph', function () {
    expect(Customer::count())->toBe(120);
    expect(Opportunity::count())->toBeGreaterThan(100);
    expect(DB::table('products')->count())->toBe(20);
    expect(DB::table('customer_activities')->count())->toBe(120 * 3);
    expect(DB::table('tags')->count())->toBe(8);
    expect(DB::table('campaigns')->count())->toBe(2);
    expect(DB::table('campaign_recipients')->count())->toBe(40);
});

test('heavy seeder spreads customer dates over the past 3 years', function () {
    $min = Customer::min('created_at');
    $max = Customer::max('created_at');

    expect(strtotime($min))->toBeLessThan(strtotime('-2 years'));
    expect(strtotime($max))->toBeGreaterThan(strtotime('-30 days'));
});

test('heavy seeder skips audit logs and uses the project custom field', function () {
    expect(DB::table('audit_logs')->count())->toBe(0);

    $product = DB::table('products')->whereNotNull('custom_fields')->first();
    $fields = json_decode($product->custom_fields, true);

    expect($fields)->toHaveKey('project');
    expect(DB::table('custom_field_definitions')->where('name', 'project')->exists())->toBeTrue();
});

test('heavy seeder converts lead customers on won opportunities', function () {
    $wonCustomerIds = Opportunity::where('stage', 'won')->pluck('customer_id')->unique();

    expect($wonCustomerIds->isNotEmpty())->toBeTrue();

    foreach ($wonCustomerIds as $id) {
        $this->assertDatabaseHas('customers', ['id' => $id, 'is_lead' => false]);
    }
});

test('heavy seeder company currency is a single base', function () {
    expect(DB::table('company_settings')->where('key', 'company_currency')->value('value'))->toBe('EGP');
    expect(Opportunity::where('currency', '!=', 'EGP')->count())->toBe(0);
});

test('heavy seeder refuses a second run without force flag', function () {
    $this->seed(HeavyTestDataSeeder::class);
})->throws(RuntimeException::class, 'already ran');

test('heavy seeder invoices are broker commissions, not property bills', function () {
    $wonOpps = Opportunity::where('stage', 'won')->get(['id', 'amount']);

    expect($wonOpps->isNotEmpty())->toBeTrue();

    foreach ($wonOpps as $opp) {
        $invoice = DB::table('invoices')->where('opportunity_id', $opp->id)->first();

        expect($invoice)->not->toBeNull();

        // 2-3% commission + 14% VAT, far below the deal value.
        $rate = round($invoice->subtotal / $opp->amount * 100, 2);

        expect($rate)->toBeGreaterThanOrEqual(2.0)
            ->and($rate)->toBeLessThanOrEqual(3.0)
            ->and((float) $invoice->total)->toBeLessThan((float) $opp->amount * 0.1)
            ->and((float) $invoice->tax_percent)->toBe(14.0);

        $item = DB::table('invoice_items')->where('invoice_id', $invoice->id)->first();

        expect($item->notes)->toContain('Brokerage commission')
            ->and($item->product_id)->not->toBeNull();
    }
});
