<?php

use App\Jobs\SendCampaignEmailJob;
use App\Mail\CampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Queue;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list campaigns', function () {
    $admin = adminUser();
    Campaign::factory(3)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/campaigns')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create campaign', function () {
    $admin = adminUser();
    $template = EmailTemplate::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/api/v1/campaigns', [
            'name' => 'Spring Sale Campaign',
            'email_template_id' => $template->id,
            'segment_filters' => [
                'is_lead' => true,
                'type' => 'company',
                'lead_status' => 'qualified',
                'source' => 'referral',
                'score_min' => 20,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Spring Sale Campaign')
        ->assertJsonPath('data.segment_filters.type', 'company');
});

test('admin can update campaign', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/campaigns/{$campaign->id}", ['name' => 'Updated Campaign'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Campaign');
});

test('admin can update campaigns in every status', function (string $status) {
    $admin = adminUser();
    $campaign = Campaign::factory()->create([
        'created_by' => $admin->id,
        'status' => $status,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/campaigns/{$campaign->id}", ['name' => 'Updated Campaign'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Campaign');
})->with(['draft', 'scheduled', 'sending', 'sent', 'cancelled']);

test('admin can delete campaign', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/campaigns/{$campaign->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
});

// ── Send & Schedule ──────────────────────────────────────────────────────────

test('admin can send campaign', function () {
    $admin = adminUser();
    Customer::factory()->company()->customer()->create([
        'account_manager_id' => $admin->id,
        'company_name' => 'Acme Ltd',
        'email' => 'new-contact@acme.test',
        'phone' => '+1-555-0100',
        'contact_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'contacts' => [],
    ]);
    $campaign = Campaign::factory()->create(['created_by' => $admin->id]);

    Queue::fake();

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/send")
        ->assertOk();

    $recipient = CampaignRecipient::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($recipient->email)->toBe('new-contact@acme.test');

    $this->actingAs($admin)
        ->getJson("/api/v1/campaigns/{$campaign->id}/recipients")
        ->assertOk()
        ->assertJsonPath('data.0.customer.name', 'Acme Ltd');
});

test('campaign email uses unified company contact fields for placeholders', function () {
    $customer = Customer::factory()->company()->customer()->create([
        'company_name' => 'Acme Ltd',
        'email' => 'hello@acme.test',
        'phone' => '+1-555-0100',
        'contact_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'contacts' => [],
    ]);
    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello {{contact_name}}',
        'body' => '{{company_name}}|{{contact_email}}|{{contact_phone}}',
    ]);
    $campaign = Campaign::factory()->create(['email_template_id' => $template->id]);
    $recipient = CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'customer_id' => $customer->id,
        'email' => $customer->email,
    ]);

    $email = new CampaignEmail($recipient->load(['campaign.template', 'customer']));

    expect($email->envelope()->subject)->toBe('Hello Acme Ltd')
        ->and($email->content()->htmlString)->toBe('Acme Ltd|hello@acme.test|+1-555-0100');
});

test('campaign recipients match combined lead and customer filters', function () {
    $admin = adminUser();
    $matchingLead = Customer::factory()->company()->lead()->create([
        'lead_status' => 'qualified',
        'source' => 'referral',
        'email' => 'lead@acme.test',
    ]);
    Customer::factory()->company()->customer()->create([
        'company_name' => 'Different record kind',
        'email' => 'customer@acme.test',
        'source' => 'referral',
    ]);
    Customer::factory()->individual()->lead()->create([
        'lead_status' => 'qualified',
        'source' => 'referral',
        'email' => 'individual@acme.test',
    ]);
    Customer::factory()->company()->lead()->create([
        'lead_status' => 'new',
        'source' => 'referral',
        'email' => 'new-lead@acme.test',
    ]);
    Customer::factory()->company()->lead()->create([
        'lead_status' => 'qualified',
        'source' => 'web',
        'email' => 'web-lead@acme.test',
    ]);
    $campaign = Campaign::factory()->create([
        'created_by' => $admin->id,
        'segment_filters' => [
            'is_lead' => true,
            'type' => 'company',
            'lead_status' => 'qualified',
            'source' => 'referral',
        ],
    ]);

    Queue::fake();

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/send")
        ->assertOk();

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('customer_id')->all())
        ->toBe([$matchingLead->id]);
});

test('campaign effective status selects the matching lead or customer status', function () {
    $admin = adminUser();
    $activeCustomer = Customer::factory()->company()->customer()->create([
        'status' => 'active',
        'email' => 'active@acme.test',
    ]);
    Customer::factory()->company()->lead()->create([
        'lead_status' => 'new',
        'email' => 'lead@acme.test',
    ]);
    $campaign = Campaign::factory()->create([
        'created_by' => $admin->id,
        'segment_filters' => ['effective_status' => 'active'],
    ]);

    Queue::fake();

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/send")
        ->assertOk();

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->pluck('customer_id')->all())
        ->toBe([$activeCustomer->id]);
});

test('admin can schedule campaign', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/schedule", [
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');
});

test('admin can reschedule a scheduled campaign', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->scheduled()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/schedule", [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');
});

test('scheduled campaign command queues only campaigns that are due', function () {
    Customer::factory()->company()->customer()->create([
        'company_name' => 'Acme Ltd',
        'email' => 'hello@acme.test',
    ]);
    $dueCampaign = Campaign::factory()->scheduled()->create([
        'scheduled_at' => now()->subMinute(),
    ]);
    $futureCampaign = Campaign::factory()->scheduled()->create([
        'scheduled_at' => now()->addHour(),
    ]);

    Queue::fake();

    $this->artisan('crm:send-scheduled-campaigns')->assertSuccessful();

    Queue::assertPushed(SendCampaignEmailJob::class, 1);
    expect($dueCampaign->fresh()->status)->toBe('sending')
        ->and($futureCampaign->fresh()->status)->toBe('scheduled');
});

test('admin can cancel campaign', function () {
    $admin = adminUser();
    $campaign = Campaign::factory()->scheduled()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/campaigns/{$campaign->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on campaigns returns 401', function () {
    $this->getJson('/api/v1/campaigns')->assertUnauthorized();
});

test('sales_rep cannot create campaign', function () {
    $template = EmailTemplate::factory()->create();
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/campaigns', [
            'name' => 'Test',
            'email_template_id' => $template->id,
        ])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create campaign requires name and email_template_id', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/campaigns', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email_template_id']);
});
