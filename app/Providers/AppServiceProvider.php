<?php

namespace App\Providers;

use App\AI\AiChat;
use App\Models\AsyncJob;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldGroup;
use App\Models\EmailTemplate;
use App\Models\Import;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Tax;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\AiChatPolicy;
use App\Policies\AsyncJobPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\CustomerActivityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomFieldDefinitionPolicy;
use App\Policies\CustomFieldGroupPolicy;
use App\Policies\EmailTemplatePolicy;
use App\Policies\ImportPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\NotePolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaxPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(CompanySetting::class, CompanySettingPolicy::class);
        Gate::policy(AiChat::class, AiChatPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(CustomerActivity::class, CustomerActivityPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Tax::class, TaxPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(EmailTemplate::class, EmailTemplatePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(CustomFieldDefinition::class, CustomFieldDefinitionPolicy::class);
        Gate::policy(CustomFieldGroup::class, CustomFieldGroupPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(AsyncJob::class, AsyncJobPolicy::class);
        Gate::policy(Import::class, ImportPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
    }
}
