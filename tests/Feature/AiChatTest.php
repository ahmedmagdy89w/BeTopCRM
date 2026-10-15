<?php

use App\AI\Agents\CrmAnalyticsAgent;
use App\Jobs\ProcessAsyncJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;

// -- Happy path ---------------------------------------------------------------

test('admin can dispatch AI chat job and receives pending async job', function () {
    Queue::fake();

    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'What is our conversion rate?',
            'scope' => 'all',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'type', 'status', 'progress', 'created_at']])
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(ProcessAsyncJob::class);
});

test('completed chat job result contains answer and sources', function () {
    config(['queue.default' => 'sync']);

    Ai::fakeAgent(CrmAnalyticsAgent::class, [
        'Conversion rate is 24%. Insights: pipeline is healthy. Recommendations: follow up with at-risk customers.',
    ]);

    $jobId = $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'What is our conversion rate?',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs(adminUser())
        ->getJson("/api/v1/async-jobs/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonStructure(['data' => ['result' => ['answer', 'sources', 'locale', 'generated_at']]]);
});

test('manager can dispatch AI chat job', function () {
    Queue::fake();

    $this->actingAs(managerUser())
        ->postJson('/api/v1/ai-chat/async', ['question' => 'Top performers?'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('admin can fetch suggested questions', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/ai-chat/suggested-questions')
        ->assertOk()
        ->assertJsonStructure(['data' => ['questions']]);
});

test('suggested questions follow the Accept-Language header, not the stored user locale', function () {
    $user = adminUser();
    $user->update(['locale' => 'en']);

    $questions = $this->actingAs($user)
        ->getJson('/api/v1/ai-chat/suggested-questions', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->json('data.questions');

    expect($questions[0])->toBe('ما هو معدل تحويل العملاء المحتملين هذا الشهر؟');
});

test('product snapshot includes per-product names and booked sales', function () {
    $product = Product::factory()->create(['name' => 'CRM license']);
    $customer = Customer::factory()->customer()->create();
    $opportunity = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'currency' => 'EUR',
    ]);
    $opportunity->products()->attach($product->id, ['quantity' => 2, 'unit_price' => 45]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'Which products drive booked sales?'],
        adminUser(),
        'all'
    );

    expect($snapshot['products']['top_products'][0]['name'])->toBe('CRM license')
        ->and($snapshot['products']['top_products'][0]['booked_sales'][0]['booked_value'])->toBe('90.00');
});

test('referenced opportunity carries its stored amount, not the lead estimate', function () {
    $customer = Customer::factory()->customer()->create(['estimated_value' => 1000]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'amount' => 400,
        'currency' => 'USD',
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => "What is the value of /customers/{$customer->id}/opportunities/{$opportunity->id}?"],
        adminUser(),
        'all'
    );

    expect($snapshot['referenced_opportunities'][0]['amount'])->toBe('400.00')
        ->and($snapshot['referenced_opportunities'][0]['currency'])->toBe('USD');
});

test('opportunity search hits include the stored amount', function () {
    $customer = Customer::factory()->customer()->create(['company_name' => 'Zebra Holdings']);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Zebra Enterprise License',
        'amount' => 400,
        'currency' => 'USD',
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'Zebra Enterprise License'],
        adminUser(),
        'all'
    );

    $hit = collect($snapshot['search_hits'])->firstWhere('type', 'opportunity');

    expect($hit)->not->toBeNull()
        ->and($hit['amount'])->toBe('400.00');
});

test('open opportunities list carries stored amounts for name-based questions', function () {
    $customer = Customer::factory()->customer()->create(['estimated_value' => 1000]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Cairo Office Deal',
        'stage' => 'proposal',
        'amount' => 400,
        'currency' => 'EGP',
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'What is the value of the incomplete opportunity?'],
        adminUser(),
        'all'
    );

    $entry = collect($snapshot['open_opportunities'])->firstWhere('id', $opportunity->id);

    expect($entry)->not->toBeNull()
        ->and($entry['amount'])->toBe('400.00')
        ->and($entry['currency'])->toBe('EGP');
});

test('arabic opportunity reference resolves the record by id', function () {
    $customer = Customer::factory()->customer()->create(['estimated_value' => 1000]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'amount' => 400,
        'currency' => 'EGP',
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => "ما هي قيمة الفرصة {$opportunity->id}؟"],
        adminUser(),
        'all'
    );

    expect($snapshot['referenced_opportunities'][0]['amount'])->toBe('400.00');
});

// -- Authorization ------------------------------------------------------------

test('unauthenticated chat request returns 401', function () {
    $this->postJson('/api/v1/ai-chat/async', ['question' => 'Test'])
        ->assertUnauthorized();
});

test('sales_rep cannot use AI chat', function () {
    $this->actingAs(salesRepUser())
        ->postJson('/api/v1/ai-chat/async', ['question' => 'Test question'])
        ->assertForbidden();
});

test('sales_rep cannot fetch suggested questions', function () {
    $this->actingAs(salesRepUser())
        ->getJson('/api/v1/ai-chat/suggested-questions')
        ->assertForbidden();
});

// -- Validation ---------------------------------------------------------------

test('chat requires question', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

test('chat validates scope values', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'Test',
            'scope' => 'invalid-scope',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scope']);
});

test('chat validates language is en or ar', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'Test',
            'language' => 'fr',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);
});

// -- Localized answer structure -------------------------------------------------

test('analytics agent requires section headings in the selected language', function () {
    $arabic = CrmAnalyticsAgent::make(languageName: 'Arabic', scope: 'all')->instructions();
    $english = CrmAnalyticsAgent::make(languageName: 'English', scope: 'all')->instructions();

    expect($arabic)->toContain('الرؤى:')
        ->and($arabic)->toContain('التوصيات:')
        ->and($arabic)->not->toContain('Insights:')
        ->and($english)->toContain('Insights:')
        ->and($english)->toContain('Recommendations:');
});

// -- Product terminology ------------------------------------------------------

test('suggested questions use custom product labels', function () {
    CompanySetting::setMany([
        'product_plural_en' => 'Services',
        'product_plural_ar' => 'خدمات',
    ]);

    $english = $this->actingAs(adminUser())
        ->getJson('/api/v1/ai-chat/suggested-questions', ['Accept-Language' => 'en'])
        ->assertOk()
        ->json('data.questions');

    $arabic = $this->actingAs(adminUser())
        ->getJson('/api/v1/ai-chat/suggested-questions', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->json('data.questions');

    expect($english)->toContain('Which Services drive booked sales versus open pipeline?')
        ->and($arabic)->toContain('ما هي خدمات التي تقود المبيعات المحققة مقابل مراحل العمل المفتوحة؟');
});

test('analytics agent instructions use custom product terminology', function () {
    $instructions = CrmAnalyticsAgent::make(
        languageName: 'English',
        scope: 'all',
        productSingular: 'Service',
        productPlural: 'Services',
    )->instructions();

    expect($instructions)->toContain('"Services"')
        ->and($instructions)->toContain('"Service"');
});

test('analytics agent instructions teach ranking and forecasting', function () {
    $instructions = CrmAnalyticsAgent::make(languageName: 'English', scope: 'all')->instructions();

    expect($instructions)->toContain('REASONING OVER ROWS')
        ->and($instructions)->toContain('FORECASTS')
        ->and($instructions)->toContain('user_product_performance');
});

test('analytics agent instructions catalog all reports with filters', function () {
    $instructions = CrmAnalyticsAgent::make(languageName: 'English', scope: 'all')->instructions();

    expect($instructions)->toContain('REPORTS CATALOG')
        ->and($instructions)->toContain('user-performance')
        ->and($instructions)->toContain('user-product-performance')
        ->and($instructions)->toContain('health_below')
        ->and($instructions)->toContain('min_score');
});

// -- User scoping -------------------------------------------------------------

test('ai chat rejects an unknown user_id', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'How is Ahmed doing?',
            'user_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

test('ai chat rejects an unknown customer_id', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'How is this customer doing?',
            'customer_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

test('ai chat rejects an invalid customer_type', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/ai-chat/async', [
            'question' => 'How are companies doing?',
            'customer_type' => 'alien',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_type']);
});

test('ai snapshot scopes metrics to the selected user', function () {
    $owner = User::factory()->create(['name' => 'Scoped Rep']);
    $other = User::factory()->create(['name' => 'Other Rep']);
    $customer = Customer::factory()->customer()->create();
    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $owner->id,
        'stage' => 'proposal',
    ]);
    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $other->id,
        'stage' => 'proposal',
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'How is the team doing?', 'user_id' => $owner->id],
        adminUser(),
        'all'
    );

    expect($snapshot['user_performance']['total_users'])->toBe(1)
        ->and($snapshot['top_performers'])->toHaveCount(1)
        ->and($snapshot['top_performers'][0]['user']['name'])->toBe('Scoped Rep')
        ->and($snapshot['open_opportunities'])->toHaveCount(1)
        ->and($snapshot['open_opportunities'][0]['stage'])->toBe('proposal');
});

test('ai snapshot scopes to the selected customer and includes user product sales', function () {
    $wanted = Customer::factory()->customer()->create();
    Customer::factory()->customer()->create();
    $owner = User::factory()->create(['name' => 'Scoped Rep']);
    $product = Product::factory()->create(['name' => 'CRM license']);

    $opp = Opportunity::factory()->won()->create([
        'customer_id' => $wanted->id,
        'assigned_to' => $owner->id,
        'currency' => 'EUR',
    ]);
    $opp->products()->attach($product->id, ['quantity' => 2, 'unit_price' => 45]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'How is this customer doing?', 'customer_id' => $wanted->id],
        adminUser(),
        'all'
    );

    expect($snapshot['lead_pipeline']['summary']['total_leads'])->toBe(0)
        ->and($snapshot['customers']['total_customers'])->toBe(1)
        ->and($snapshot['user_product_performance']['summary']['users'])->toBe(1)
        ->and($snapshot['user_product_performance']['top_rows'][0]['user'])->toBe('Scoped Rep')
        ->and($snapshot['user_product_performance']['top_rows'][0]['product'])->toBe('CRM license')
        ->and($snapshot['user_product_performance']['top_rows'][0]['quantity'])->toBe(2);
});

test('ai snapshot honors the customer_type filter', function () {
    Customer::factory()->customer()->create(['type' => 'company']);
    Customer::factory()->customer()->create(['type' => 'individual']);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'How are companies doing?', 'customer_type' => 'company'],
        adminUser(),
        'customers'
    );

    expect($snapshot['customers']['total_customers'])->toBe(1);
});

test('ai snapshot includes monthly sales won revenue', function () {
    $customer = Customer::factory()->customer()->create();
    Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'amount' => 1500,
        'currency' => 'USD',
        'updated_at' => now()->startOfMonth(),
    ]);

    $method = new ReflectionMethod(AiChatService::class, 'buildSnapshot');
    $snapshot = $method->invoke(
        app(AiChatService::class),
        ['question' => 'What were our sales per month?'],
        adminUser(),
        'all'
    );

    expect($snapshot['sales']['summary']['total_won_count'])->toBe(1)
        ->and($snapshot['sales']['summary']['total_revenue_by_currency'][0]['value'])->toBe('1500.00')
        ->and($snapshot['sales']['periods'][0]['period'])->toBe(now()->format('Y-m'));
});
