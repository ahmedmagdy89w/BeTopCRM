<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

// ── Reports ───────────────────────────────────────────────────────────────────

test('admin can access user performance report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/user-performance')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('user performance includes users assigned leads without opportunities', function () {
    $assignee = User::factory()->create();
    Customer::factory()->lead()->create(['assigned_to' => $assignee->id]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-performance?user_id='.$assignee->id)
        ->assertOk()
        ->assertJsonPath('data.0.user.id', $assignee->id)
        ->assertJsonPath('data.0.leads_assigned', 1)
        ->assertJsonPath('data.0.leads_won', 0)
        ->assertJsonPath('data.0.total_won_value', '0.00')
        ->assertJsonPath('data.0.avg_opportunity_value', '0.00')
        ->assertJsonPath('summary.total_leads_assigned', 1);
});

test('user performance win rate uses assigned opportunities as its denominator', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->lead()->create(['assigned_to' => $owner->id]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'created_at' => now()->subDays(10),
        'closed_at' => now()->subDays(2),
        'updated_at' => now(),
    ]);
    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'stage' => 'proposal',
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-performance?user_id='.$owner->id)
        ->assertOk()
        ->assertJsonPath('data.0.leads_assigned', 1)
        ->assertJsonPath('data.0.opportunities_assigned', 2)
        ->assertJsonPath('data.0.opportunities_won', 1)
        ->assertJsonPath('data.0.opportunity_win_rate', '50.00')
        ->assertJsonPath('data.0.avg_days_to_close', 8)
        ->assertJsonPath('summary.overall_opportunity_win_rate', '50.00');
});

test('user performance reports won values separately by currency', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->lead()->create(['assigned_to' => $owner->id]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'amount' => 125,
        'currency' => 'USD',
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'amount' => 200,
        'currency' => 'EUR',
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-performance?user_id='.$owner->id)
        ->assertOk()
        ->assertJsonPath('data.0.total_won_value', null)
        ->assertJsonPath('data.0.total_won_value_by_currency.0.currency', 'EUR')
        ->assertJsonPath('data.0.total_won_value_by_currency.0.value', '200.00')
        ->assertJsonPath('data.0.total_won_value_by_currency.1.currency', 'USD')
        ->assertJsonPath('data.0.total_won_value_by_currency.1.value', '125.00')
        ->assertJsonPath('data.0.total_won_value_by_currency.0.avg_opportunity_value', '200.00')
        ->assertJsonPath('data.0.avg_opportunity_value', null)
        ->assertJsonPath('summary.total_won_value', null)
        ->assertJsonCount(2, 'summary.total_won_value_by_currency');
});

test('admin can access company report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/company')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('company report uses filtered opportunity and customer activity data', function () {
    $company = Customer::factory()->company()->customer()->create([
        'industry' => 'technology',
        'contacts' => [['first_name' => 'Ada', 'last_name' => 'Lovelace']],
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $company->id,
        'amount' => 1250,
    ]);

    Customer::factory()->company()->customer()->create(['industry' => 'healthcare']);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/company?industry=technology')
        ->assertOk()
        ->assertJsonPath('data.0.total_leads', 1)
        ->assertJsonPath('data.0.leads_by_status.won', 1)
        ->assertJsonPath('data.0.leads_by_status.prospecting', 0)
        ->assertJsonPath('data.0.contacts_count', 1)
        ->assertJsonPath('summary.total_organizations', 1)
        ->assertJsonPath('summary.top_industries.0.industry', 'technology');
});

test('company report keeps opportunity totals separate by currency', function () {
    $company = Customer::factory()->company()->customer()->create(['industry' => 'technology']);
    Opportunity::factory()->won()->create([
        'customer_id' => $company->id,
        'amount' => 125,
        'currency' => 'USD',
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $company->id,
        'amount' => 200,
        'currency' => 'EUR',
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/company?organization_id='.$company->id)
        ->assertOk()
        ->assertJsonPath('data.0.total_estimated_value', null)
        ->assertJsonPath('data.0.total_won_value', null)
        ->assertJsonPath('data.0.total_won_value_by_currency.0.currency', 'EUR')
        ->assertJsonPath('data.0.total_won_value_by_currency.0.value', '200.00')
        ->assertJsonPath('data.0.total_won_value_by_currency.1.currency', 'USD')
        ->assertJsonPath('data.0.total_won_value_by_currency.1.value', '125.00')
        ->assertJsonPath('summary.total_won_value', null)
        ->assertJsonCount(2, 'summary.total_won_value_by_currency');
});

test('admin can access customer report', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/customer')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('lead pipeline report aggregates only matching lead filters', function () {
    $owner = User::factory()->create();
    Customer::factory()->lead()->create([
        'assigned_to' => $owner->id,
        'lead_status' => 'qualified',
        'source' => 'referral',
        'score' => 82,
    ]);
    Customer::factory()->lead()->create([
        'assigned_to' => User::factory(),
        'lead_status' => 'new',
        'source' => 'website',
        'score' => 40,
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/lead-pipeline?assigned_to='.$owner->id.'&min_score=70')
        ->assertOk()
        ->assertJsonPath('summary.total_leads', 1)
        ->assertJsonPath('summary.open_leads', 1)
        ->assertJsonPath('summary.scored_leads', 1)
        ->assertJsonPath('data.by_status.qualified', 1)
        ->assertJsonPath('data.by_source.0.source', 'referral')
        ->assertJsonPath('data.top_scored_leads.0.score', 82);
});

test('product performance separates booked sales by currency and open interest', function () {
    $product = Product::factory()->create(['name' => 'CRM license']);
    $addOn = Product::factory()->create(['name' => 'CRM support']);
    $customer = Customer::factory()->customer()->create();
    $opportunity = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'currency' => 'EUR',
    ]);
    $opportunity->products()->attach($product->id, ['quantity' => 2, 'unit_price' => 45]);
    $opportunity = Opportunity::factory()->create(['stage' => 'proposal']);
    $opportunity->products()->attach($product->id, ['quantity' => 3]);
    $opportunity->products()->attach($addOn->id, ['quantity' => 1]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/product-performance')
        ->assertOk()
        ->assertJsonPath('data.0.booked_sales.0.currency', 'EUR')
        ->assertJsonPath('data.0.booked_sales.0.booked_value', '90.00')
        ->assertJsonPath('data.0.open_pipeline.opportunities_count', 1)
        ->assertJsonPath('data.0.open_pipeline.quantity', 3)
        ->assertJsonPath('summary.open_pipeline_opportunities', 1)
        ->assertJsonPath('summary.open_pipeline_quantity', 4)
        ->assertJsonPath('summary.booked_sales_by_currency.0.currency', 'EUR');
});

test('customer report summary respects the applied filters', function () {
    Customer::factory()->customer()->create([
        'status' => 'at_risk',
        'health_score' => 25,
    ]);
    Customer::factory()->customer()->create([
        'status' => 'active',
        'health_score' => 90,
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?status=at_risk')
        ->assertOk()
        ->assertJsonPath('summary.total_customers', 1)
        ->assertJsonPath('summary.by_status.at_risk', 1)
        ->assertJsonPath('summary.by_status.active', 0)
        ->assertJsonPath('summary.avg_health_score', 25)
        ->assertJsonPath('data.0.customer.status', 'at_risk');
});

test('customer report keeps won opportunity totals separate by currency', function () {
    $customer = Customer::factory()->customer()->create();
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'amount' => 125,
        'currency' => 'USD',
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'amount' => 200,
        'currency' => 'EUR',
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?customer_id='.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.0.total_won_opportunity_value', null)
        ->assertJsonPath('data.0.total_won_opportunity_value_by_currency.0.currency', 'EUR')
        ->assertJsonPath('data.0.total_won_opportunity_value_by_currency.0.value', '200.00')
        ->assertJsonPath('data.0.total_won_opportunity_value_by_currency.1.currency', 'USD')
        ->assertJsonPath('data.0.total_won_opportunity_value_by_currency.1.value', '125.00')
        ->assertJsonPath('summary.total_won_opportunity_revenue', null)
        ->assertJsonCount(2, 'summary.total_won_opportunity_revenue_by_currency');
});

test('sales_rep cannot access reports', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/reports/user-performance')
        ->assertForbidden();
});

test('manager can access reports', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/reports/company')
        ->assertOk();
});

test('unauthenticated request on reports returns 401', function () {
    $this->getJson('/api/v1/reports/company')->assertUnauthorized();
});

// ── Report Exports ────────────────────────────────────────────────────────────

test('admin can export user performance report as xlsx', function () {
    Excel::fake();

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/user-performance/export?format=xlsx')
        ->assertOk();

    Excel::assertDownloaded('user-performance-report.xlsx');
});

test('admin can export company report as xlsx', function () {
    Excel::fake();

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/company/export?format=xlsx')
        ->assertOk();

    Excel::assertDownloaded('company-report.xlsx');
});

test('admin can export customer report as xlsx', function () {
    Excel::fake();

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/customer/export?format=xlsx')
        ->assertOk();

    Excel::assertDownloaded('customer-report.xlsx');
});

test('admin can export lead and product reports as xlsx', function () {
    Excel::fake();
    $admin = adminUser();

    $this->actingAs($admin)->get('/api/v1/reports/lead-pipeline/export?format=xlsx')->assertOk();
    $this->actingAs($admin)->get('/api/v1/reports/product-performance/export?format=xlsx')->assertOk();

    Excel::assertDownloaded('lead-pipeline-report.xlsx');
    Excel::assertDownloaded('product-performance-report.xlsx');
});

test('admin can export lead and product reports as pdf', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/lead-pipeline/export?format=pdf')
        ->assertOk()
        ->assertDownload('lead-pipeline-report.pdf');

    $this->actingAs($admin)
        ->get('/api/v1/reports/product-performance/export?format=pdf')
        ->assertOk()
        ->assertDownload('product-performance-report.pdf');
});

test('admin can export user performance report as pdf', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/user-performance/export?format=pdf')
        ->assertOk()
        ->assertDownload('user-performance-report.pdf');
});

test('admin can export company report as pdf', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/company/export?format=pdf')
        ->assertOk()
        ->assertDownload('company-report.pdf');
});

test('admin can export customer report as pdf', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/customer/export?format=pdf')
        ->assertOk()
        ->assertDownload('customer-report.pdf');
});

test('export defaults to xlsx when no format given', function () {
    Excel::fake();

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/api/v1/reports/user-performance/export')
        ->assertOk();

    Excel::assertDownloaded('user-performance-report.xlsx');
});

test('export returns 422 for invalid format', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/user-performance/export?format=csv')
        ->assertUnprocessable();
});

test('sales_rep cannot export reports', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/reports/user-performance/export')
        ->assertForbidden();
});

test('pdf export uses arabic labels when user locale is ar', function () {
    $admin = adminUser();
    $admin->update(['locale' => 'ar']);

    $response = $this->actingAs($admin)
        ->get('/api/v1/reports/user-performance/export?format=pdf')
        ->assertOk()
        ->assertDownload('user-performance-report.pdf');
});

// ── User × product performance ──────────────────────────────────────────────

test('user product performance breaks sales down per user and product', function () {
    $owner = User::factory()->create(['name' => 'Aaron Seller']);
    $other = User::factory()->create(['name' => 'Zed Seller']);
    $product = Product::factory()->create(['name' => 'CRM license']);
    $customer = Customer::factory()->customer()->create();

    $won = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'currency' => 'EUR',
    ]);
    $won->products()->attach($product->id, ['quantity' => 2, 'unit_price' => 45]);

    $open = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $other->id,
        'stage' => 'proposal',
    ]);
    $open->products()->attach($product->id, ['quantity' => 3]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-product-performance')
        ->assertOk()
        ->assertJsonPath('data.0.user.name', $owner->name)
        ->assertJsonPath('data.0.product.name', 'CRM license')
        ->assertJsonPath('data.0.opportunities_count', 1)
        ->assertJsonPath('data.0.won_count', 1)
        ->assertJsonPath('data.0.booked_value_by_currency.0.currency', 'EUR')
        ->assertJsonPath('data.0.booked_value_by_currency.0.value', '90.00')
        ->assertJsonPath('data.1.user.name', $other->name)
        ->assertJsonPath('data.1.open_count', 1)
        ->assertJsonPath('data.1.quantity', 3)
        ->assertJsonPath('summary.users', 2)
        ->assertJsonPath('summary.products', 1);
});

test('user product performance respects user and product filters', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create(['name' => 'CRM license']);
    $addOn = Product::factory()->create(['name' => 'CRM support']);
    $customer = Customer::factory()->customer()->create();

    $opp = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
    ]);
    $opp->products()->attach($product->id, ['quantity' => 1, 'unit_price' => 100]);
    $opp->products()->attach($addOn->id, ['quantity' => 1, 'unit_price' => 50]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-product-performance?user_id='.$owner->id.'&product_id='.$addOn->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.name', 'CRM support')
        ->assertJsonPath('data.0.booked_value_by_currency.0.value', '50.00')
        ->assertJsonPath('summary.users', 1)
        ->assertJsonPath('summary.products', 1);
});

test('user product performance export downloads xlsx', function () {
    $this->actingAs(adminUser())
        ->get('/api/v1/reports/user-product-performance/export?format=xlsx')
        ->assertOk()
        ->assertDownload('user-product-performance-report.xlsx');
});

// ── Customer filter on lead pipeline ────────────────────────────────────────

test('lead pipeline scopes to a single customer', function () {
    $wanted = Customer::factory()->lead()->create(['lead_status' => 'qualified', 'score' => 82]);
    Customer::factory()->lead()->create(['lead_status' => 'qualified', 'score' => 40]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/lead-pipeline?customer_id='.$wanted->id)
        ->assertOk()
        ->assertJsonPath('summary.total_leads', 1)
        ->assertJsonPath('data.top_scored_leads.0.customer.id', $wanted->id);
});

// ── Server-side pagination ──────────────────────────────────────────────────

test('customer report paginates while summary stays full-scope', function () {
    Customer::factory()->customer()->create(['health_score' => 90]);
    Customer::factory()->customer()->create(['health_score' => 70]);
    Customer::factory()->customer()->create(['health_score' => 50]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?per_page=2&page=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('summary.total_customers', 3);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('summary.total_customers', 3);
});

test('customer report sorts server-side with nulls last', function () {
    Customer::factory()->customer()->create(['health_score' => 50]);
    Customer::factory()->customer()->create(['health_score' => 90]);

    $response = $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?per_page=10&sort_by=health_score&sort_dir=desc')
        ->assertOk();

    expect($response->json('data.0.customer.health_score'))->toBe(90)
        ->and($response->json('data.1.customer.health_score'))->toBe(50);
});

test('user product performance paginates and sorts server-side', function () {
    $owner = User::factory()->create(['name' => 'Aaron Seller']);
    $other = User::factory()->create(['name' => 'Zed Seller']);
    $product = Product::factory()->create(['name' => 'CRM license']);
    $customer = Customer::factory()->customer()->create();

    foreach ([$owner, $other] as $rep) {
        $opp = Opportunity::factory()->won()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $rep->id,
        ]);
        $opp->products()->attach($product->id, ['quantity' => 1, 'unit_price' => 100]);
    }

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/user-product-performance?per_page=1&page=2&sort_by=user&sort_dir=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.name', 'Zed Seller')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('summary.users', 2);
});

test('report pagination clamps per_page and ignores unknown sort keys', function () {
    Customer::factory()->customer()->create();

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?per_page=500&sort_by=nope')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.total', 1);
});

test('customer report without paging returns the full list', function () {
    Customer::factory()->customer()->create();
    Customer::factory()->customer()->create();

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta');
});

test('customer report filters by customer type', function () {
    Customer::factory()->customer()->create(['type' => 'company']);
    Customer::factory()->customer()->create(['type' => 'individual']);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?type=company')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('summary.total_customers', 1);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/customer?type=individual')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('summary.total_customers', 1);
});

test('company report paginates with full-scope summary and sorts server-side', function () {
    Customer::factory()->company()->create(['company_name' => 'Zulu Corp']);
    Customer::factory()->company()->create(['company_name' => 'Alpha Corp']);
    Customer::factory()->company()->create(['company_name' => 'Mike Corp']);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/company?per_page=2&page=1&sort_by=organization&sort_dir=asc')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.organization.name', 'Alpha Corp')
        ->assertJsonPath('data.1.organization.name', 'Mike Corp')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('summary.total_organizations', 3);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/company?per_page=2&page=2&sort_by=organization&sort_dir=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.organization.name', 'Zulu Corp')
        ->assertJsonPath('summary.total_organizations', 3);
});

test('company report without paging returns the full list', function () {
    Customer::factory()->company()->create();
    Customer::factory()->company()->create();

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/company')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta');
});

// ── Sales report (monthly/yearly) ───────────────────────────────────────────

test('sales report groups won revenue by month', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->customer()->create();

    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'amount' => 1000,
        'currency' => 'USD',
        'updated_at' => now()->startOfMonth(),
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'amount' => 2000,
        'currency' => 'USD',
        'updated_at' => now()->subMonth()->startOfMonth(),
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/sales?group_by=month')
        ->assertOk()
        ->assertJsonPath('summary.group_by', 'month')
        ->assertJsonPath('summary.periods', 2)
        ->assertJsonPath('summary.total_won_count', 2)
        ->assertJsonPath('data.0.period', now()->subMonth()->format('Y-m'))
        ->assertJsonPath('data.0.revenue_by_currency.0.value', '2000.00')
        ->assertJsonPath('data.1.revenue_by_currency.0.value', '1000.00')
        ->assertJsonPath('summary.total_revenue_by_currency.0.value', '3000.00');
});

test('sales report groups by year and respects the user filter', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->customer()->create();

    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'amount' => 500,
        'updated_at' => now()->subYear(),
    ]);
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $other->id,
        'amount' => 700,
        'updated_at' => now(),
    ]);

    $this->actingAs(adminUser())
        ->getJson('/api/v1/reports/sales?group_by=year&user_id='.$owner->id)
        ->assertOk()
        ->assertJsonPath('summary.group_by', 'year')
        ->assertJsonPath('summary.total_won_count', 1)
        ->assertJsonPath('data.0.period', now()->subYear()->format('Y'));
});

test('sales report export downloads xlsx', function () {
    $this->actingAs(adminUser())
        ->get('/api/v1/reports/sales/export?format=xlsx')
        ->assertOk()
        ->assertDownload('sales-report.xlsx');
});
