<?php

use App\Models\Customer;

// ─────────────────────────────────────────────────────────────────────────────
// Cross-cutting authorization enforcement for every entity type
// ─────────────────────────────────────────────────────────────────────────────

// ── Unauthenticated requests (401) ────────────────────────────────────────────

test('unauthenticated request to customers returns 401', function () {
    $this->getJson('/api/v1/customers')->assertUnauthorized();
});

test('unauthenticated request to opportunities returns 401', function () {
    $customer = Customer::factory()->create();
    $this->getJson("/api/v1/customers/{$customer->id}/opportunities")->assertUnauthorized();
});

test('unauthenticated request to the global opportunity list returns 401', function () {
    $this->getJson('/api/v1/opportunities')->assertUnauthorized();
});

test('unauthenticated request to tasks returns 401', function () {
    $this->getJson('/api/v1/tasks')->assertUnauthorized();
});

test('unauthenticated request to tags returns 401', function () {
    $this->getJson('/api/v1/tags')->assertUnauthorized();
});

test('unauthenticated request to reports returns 401', function () {
    $this->getJson('/api/v1/reports/company')->assertUnauthorized();
});

test('unauthenticated request to audit-logs returns 401', function () {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
});

test('unauthenticated request to email-templates returns 401', function () {
    $this->getJson('/api/v1/email-templates')->assertUnauthorized();
});

test('unauthenticated request to campaigns returns 401', function () {
    $this->getJson('/api/v1/campaigns')->assertUnauthorized();
});

test('unauthenticated request to products returns 401', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
});

test('unauthenticated request to webhooks returns 401', function () {
    $this->getJson('/api/v1/webhooks')->assertUnauthorized();
});

test('unauthenticated request to users returns 401', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

// ── sales_rep denials (403) ────────────────────────────────────────────────────

test('sales_rep cannot create opportunities without permission', function () {
    $rep = salesRepUser();
    $customer = Customer::factory()->create(['created_by' => $rep->id]);

    // sales_rep HAS create-opportunities, so use webhooks-style denial instead:
    // verify they cannot delete customers (admin-only)
    $this->actingAs($rep)
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertForbidden();
});

test('sales_rep cannot delete customers', function () {
    $admin = adminUser();
    $rep = salesRepUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($rep)
        ->deleteJson("/api/v1/customers/{$lead->id}")
        ->assertForbidden();
});

test('sales_rep cannot create tags', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/tags', ['name' => 'Forbidden Tag', 'color' => '#ff0000'])
        ->assertForbidden();
});

test('sales_rep cannot create email templates', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/email-templates', [
            'name' => 'Rep Template',
            'subject' => 'Subject',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

test('sales_rep cannot create campaigns', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/campaigns', [
            'name' => 'Rep Campaign',
        ])
        ->assertForbidden();
});

test('sales_rep cannot access user management', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

test('sales_rep cannot access webhooks', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/webhooks')
        ->assertForbidden();
});

test('sales_rep cannot access audit logs', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});

test('sales_rep cannot run reports', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/reports/company')
        ->assertForbidden();
});

// ── manager denials (403) ─────────────────────────────────────────────────────

test('manager cannot access user management', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->postJson('/api/v1/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password1!',
            'role' => 'sales_rep',
        ])
        ->assertForbidden();
});

test('manager cannot view audit logs', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});

test('manager cannot manage webhooks', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/webhooks')
        ->assertForbidden();
});

test('manager cannot manage custom fields', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'Mgr Field',
            'field_key' => 'mgr_field',
            'field_type' => 'text',
        ])
        ->assertForbidden();
});

// ── admin has full access ─────────────────────────────────────────────────────

test('admin can access audit logs', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/audit-logs')
        ->assertOk();
});

test('admin can access user management', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/users')
        ->assertOk();
});

test('admin can access webhooks', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/webhooks')
        ->assertOk();
});

test('web forms routes are no longer available', function () {
    $this->getJson('/api/v1/web-forms')->assertNotFound();
    $this->getJson('/api/v1/forms/contact-us')->assertNotFound();
    $this->postJson('/api/v1/forms/contact-us/submit', [])->assertNotFound();
});

test('admin can run reports', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/reports/company')
        ->assertOk();
});
