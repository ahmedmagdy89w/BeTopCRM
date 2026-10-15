# 08 — File Structure

> This file contains the original planned Leads & Organizations file inventory. Those legacy entities are no longer active. Current custom-field group files are listed in the addendum at the end; use the repository as the source of truth for other newer modules.

## Migrations

```
database/migrations/2026_03_08_000001_create_organizations_table.php
database/migrations/2026_03_08_000002_create_leads_table.php
database/migrations/2026_03_08_000003_create_lead_activities_table.php
database/migrations/2026_03_08_000004_create_contacts_table.php
database/migrations/2026_03_08_000005_create_customers_table.php
database/migrations/2026_03_08_000006_create_customer_activities_table.php
database/migrations/2026_03_08_000007_create_deals_table.php
database/migrations/2026_03_08_000008_create_tasks_table.php
database/migrations/2026_03_08_000009_create_tags_table.php
database/migrations/2026_03_08_000010_create_taggables_table.php
database/migrations/2026_03_08_000011_create_email_templates_table.php
database/migrations/2026_03_08_000012_create_campaigns_table.php
database/migrations/2026_03_08_000013_create_campaign_recipients_table.php
database/migrations/2026_03_08_000014_create_audit_logs_table.php
database/migrations/2026_03_08_000015_create_attachments_table.php
database/migrations/2026_03_08_000016_create_imports_table.php
database/migrations/2026_03_08_000017_add_settings_columns_to_users_table.php
database/migrations/2026_03_09_000007_create_custom_field_definitions_table.php
database/migrations/2026_03_09_000008_add_custom_fields_column_to_entities.php
database/migrations/2026_09_25_171914_create_custom_field_groups_table.php
database/migrations/2026_09_25_171914_restructure_custom_field_definitions_for_groups.php
database/migrations/2026_09_25_171915_add_custom_fields_to_products_table.php
database/migrations/2026_03_08_000020_add_scoring_columns_to_leads_table.php
database/migrations/2026_03_08_000021_create_products_table.php
database/migrations/2026_03_08_000021b_create_product_categories_table.php
database/migrations/2026_03_08_000022_create_deal_items_table.php
database/migrations/2026_03_08_000023_create_webhook_endpoints_table.php
database/migrations/2026_03_08_000024_create_webhook_deliveries_table.php
database/migrations/2026_03_08_000027_create_notes_table.php
database/migrations/2026_03_13_095549_create_async_jobs_table.php
database/migrations/2026_09_25_112516_create_one_time_passwords_table.php
```

> Also run `php artisan notifications:table` for the notifications migration.

## Models

```
app/Models/Organization.php
app/Models/Lead.php
app/Models/LeadActivity.php
app/Models/Contact.php
app/Models/Customer.php
app/Models/CustomerActivity.php
app/Models/Deal.php
app/Models/Task.php
app/Models/Tag.php
app/Models/EmailTemplate.php
app/Models/Campaign.php
app/Models/CampaignRecipient.php
app/Models/AuditLog.php
app/Models/Traits/HasTags.php
app/Models/Traits/Auditable.php
app/Models/Traits/HasAttachments.php
app/Models/Traits/HasCustomFields.php
app/Models/Traits/HasNotes.php
app/Models/Attachment.php
app/Models/Import.php
app/Models/CustomFieldDefinition.php
app/Models/CustomFieldGroup.php
app/Models/Product.php
app/Models/ProductCategory.php
app/Models/Tax.php
app/Models/DealItem.php
app/Models/WebhookEndpoint.php
app/Models/WebhookDelivery.php
app/Models/Note.php
app/Models/AsyncJob.php
```

## Controllers

```
app/Http/Controllers/Api/OrganizationController.php
app/Http/Controllers/Api/ContactController.php
app/Http/Controllers/Api/LeadController.php
app/Http/Controllers/Api/LeadActivityController.php
app/Http/Controllers/Api/CustomerController.php
app/Http/Controllers/Api/CustomerActivityController.php
app/Http/Controllers/Api/DealController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/ReportController.php
app/Http/Controllers/Api/NotificationController.php
app/Http/Controllers/Api/TaskController.php
app/Http/Controllers/Api/TagController.php
app/Http/Controllers/Api/EmailTemplateController.php
app/Http/Controllers/Api/CampaignController.php
app/Http/Controllers/Api/AuditLogController.php
app/Http/Controllers/Api/AttachmentController.php
app/Http/Controllers/Api/ImportController.php
app/Http/Controllers/Api/ExportController.php
app/Http/Controllers/Api/SearchController.php
app/Http/Controllers/Api/UserSettingsController.php
app/Http/Controllers/Api/UserController.php
app/Http/Controllers/Api/CustomFieldDefinitionController.php
app/Http/Controllers/Api/CustomFieldGroupController.php
app/Http/Controllers/Api/ProductController.php
app/Http/Controllers/Api/ProductCategoryController.php
app/Http/Controllers/Api/TaxController.php
app/Http/Controllers/Api/DealItemController.php
app/Http/Controllers/Api/WebhookEndpointController.php
app/Http/Controllers/Api/NoteController.php
app/Http/Controllers/Api/TimelineController.php
app/Http/Controllers/Api/AsyncJobController.php
app/Http/Controllers/Api/AiChatController.php
```

## Form Requests

```
app/Http/Requests/Organization/StoreOrganizationRequest.php
app/Http/Requests/Organization/UpdateOrganizationRequest.php
app/Http/Requests/Contact/StoreContactRequest.php
app/Http/Requests/Contact/UpdateContactRequest.php
app/Http/Requests/Lead/StoreLeadRequest.php
app/Http/Requests/Lead/UpdateLeadRequest.php
app/Http/Requests/Lead/UpdateLeadStatusRequest.php
app/Http/Requests/Lead/AssignLeadRequest.php
app/Http/Requests/LeadActivity/StoreLeadActivityRequest.php
app/Http/Requests/LeadActivity/UpdateLeadActivityRequest.php
app/Http/Requests/Customer/UpdateCustomerRequest.php
app/Http/Requests/CustomerActivity/StoreCustomerActivityRequest.php
app/Http/Requests/CustomerActivity/UpdateCustomerActivityRequest.php
app/Http/Requests/CustomerActivity/CompleteCustomerActivityRequest.php
app/Http/Requests/Deal/StoreDealRequest.php
app/Http/Requests/Deal/UpdateDealRequest.php
app/Http/Requests/Task/StoreTaskRequest.php
app/Http/Requests/Task/UpdateTaskRequest.php
app/Http/Requests/Tag/StoreTagRequest.php
app/Http/Requests/Tag/UpdateTagRequest.php
app/Http/Requests/Tag/SyncTagsRequest.php
app/Http/Requests/EmailTemplate/StoreEmailTemplateRequest.php
app/Http/Requests/EmailTemplate/UpdateEmailTemplateRequest.php
app/Http/Requests/EmailTemplate/GenerateEmailTemplateRequest.php
app/Http/Requests/AiChat/ChatAiRequest.php
app/Http/Requests/Campaign/StoreCampaignRequest.php
app/Http/Requests/Campaign/UpdateCampaignRequest.php
app/Http/Requests/Campaign/ScheduleCampaignRequest.php
app/Http/Requests/Attachment/StoreAttachmentRequest.php
app/Http/Requests/Import/StoreImportRequest.php
app/Http/Requests/UserSettings/UpdateSettingsRequest.php
app/Http/Requests/UserSettings/ChangePasswordRequest.php
app/Http/Requests/UserSettings/UploadAvatarRequest.php
app/Http/Requests/User/StoreUserRequest.php
app/Http/Requests/User/UpdateUserRequest.php
app/Http/Requests/User/UpdateUserRoleRequest.php
app/Http/Requests/Auth/RequestPasswordResetOtpRequest.php
app/Http/Requests/Auth/ResetPasswordOtpRequest.php
app/Http/Requests/CustomField/StoreCustomFieldDefinitionRequest.php
app/Http/Requests/CustomField/UpdateCustomFieldDefinitionRequest.php
app/Http/Requests/CustomField/StoreCustomFieldGroupRequest.php
app/Http/Requests/CustomField/UpdateCustomFieldGroupRequest.php
app/Http/Requests/CustomField/ValidatesCustomFields.php
app/Http/Requests/Product/StoreProductRequest.php
app/Http/Requests/Product/UpdateProductRequest.php
app/Http/Requests/ProductCategory/StoreProductCategoryRequest.php
app/Http/Requests/ProductCategory/UpdateProductCategoryRequest.php
app/Http/Requests/Tax/StoreTaxRequest.php
app/Http/Requests/Tax/UpdateTaxRequest.php
app/Http/Requests/DealItem/StoreDealItemRequest.php
app/Http/Requests/DealItem/UpdateDealItemRequest.php
app/Http/Requests/Webhook/StoreWebhookEndpointRequest.php
app/Http/Requests/Webhook/UpdateWebhookEndpointRequest.php
app/Http/Requests/Note/StoreNoteRequest.php
app/Http/Requests/Note/UpdateNoteRequest.php
```

## API Resources

```
app/Http/Resources/Organization/OrganizationResource.php
app/Http/Resources/Organization/OrganizationCollection.php
app/Http/Resources/Lead/LeadResource.php
app/Http/Resources/Lead/LeadCollection.php
app/Http/Resources/LeadActivity/LeadActivityResource.php
app/Http/Resources/Contact/ContactResource.php
app/Http/Resources/Customer/CustomerResource.php
app/Http/Resources/Customer/CustomerCollection.php
app/Http/Resources/CustomerActivity/CustomerActivityResource.php
app/Http/Resources/Deal/DealResource.php
app/Http/Resources/Task/TaskResource.php
app/Http/Resources/Tag/TagResource.php
app/Http/Resources/Notification/NotificationResource.php
app/Http/Resources/EmailTemplate/EmailTemplateResource.php
app/Http/Resources/Campaign/CampaignResource.php
app/Http/Resources/Campaign/CampaignRecipientResource.php
app/Http/Resources/AuditLog/AuditLogResource.php
app/Http/Resources/Attachment/AttachmentResource.php
app/Http/Resources/Import/ImportResource.php
app/Http/Resources/Search/SearchResultResource.php
app/Http/Resources/User/UserResource.php
app/Http/Resources/CustomField/CustomFieldDefinitionResource.php
app/Http/Resources/CustomField/CustomFieldEntityResource.php
app/Http/Resources/CustomField/CustomFieldGroupResource.php
app/Http/Resources/CustomField/CustomFieldGroupWithDefinitionsResource.php
app/Http/Resources/Product/ProductResource.php
app/Http/Resources/Product/ProductCategoryResource.php
app/Http/Resources/Tax/TaxResource.php
app/Http/Resources/Product/DealItemResource.php
app/Http/Resources/Webhook/WebhookEndpointResource.php
app/Http/Resources/Webhook/WebhookDeliveryResource.php
app/Http/Resources/Note/NoteResource.php
app/Http/Resources/Timeline/TimelineEventResource.php
app/Http/Resources/AsyncJob/AsyncJobResource.php
app/Http/Resources/AiChat/AiChatSuggestedQuestionsResource.php
```

## AI Agents

```
app/AI/Agents/EmailTemplateGeneratorAgent.php
app/AI/AiChat.php
app/AI/Agents/CrmAnalyticsAgent.php
```

## Services

```
app/Services/OrganizationService.php
app/Services/LeadService.php
app/Services/CustomerService.php
app/Services/ReportService.php
app/Services/CustomerActivityService.php
app/Services/DealService.php
app/Services/TaskService.php
app/Services/TagService.php
app/Services/CampaignService.php
app/Services/AuditLogService.php
app/Services/AttachmentService.php
app/Services/ImportService.php
app/Services/ExportService.php
app/Services/SearchService.php
app/Services/DashboardService.php
app/Services/UserSettingsService.php
app/Services/UserService.php
app/Services/CustomFieldService.php
app/Services/CustomFieldEntityRegistry.php
app/Services/LeadScoringService.php
app/Services/ProductService.php
app/Services/ProductCategoryService.php
app/Services/TaxService.php
app/Services/DocumentTotals.php
app/Services/DealItemService.php
app/Services/WebhookService.php
app/Services/WebhookDispatcher.php
app/Services/NoteService.php
app/Services/TimelineService.php
app/Services/AsyncJobService.php
app/Services/AiChatService.php
app/Services/ReportExportService.php
app/Services/ArabicPdfService.php
app/Services/OneTimePasswordService.php
app/Services/PasswordResetService.php
```

## Filters

```
app/Filters/OrganizationFilter.php
app/Filters/LeadFilter.php
app/Filters/CustomerFilter.php
app/Filters/TaskFilter.php
app/Filters/UserFilter.php
app/Filters/ImportFilter.php
app/Filters/ProductFilter.php
app/Filters/OpportunityFilter.php
app/Filters/CustomFieldFilter.php
```

## Policies

```
app/Policies/OrganizationPolicy.php
app/Policies/LeadPolicy.php
app/Policies/ContactPolicy.php
app/Policies/LeadActivityPolicy.php
app/Policies/CustomerPolicy.php
app/Policies/DealPolicy.php
app/Policies/TaskPolicy.php
app/Policies/TagPolicy.php
app/Policies/CampaignPolicy.php
app/Policies/AttachmentPolicy.php
app/Policies/ImportPolicy.php
app/Policies/UserPolicy.php
app/Policies/CustomFieldDefinitionPolicy.php
app/Policies/CustomFieldGroupPolicy.php
app/Policies/ProductPolicy.php
app/Policies/ProductCategoryPolicy.php
app/Policies/TaxPolicy.php
app/Policies/WebhookEndpointPolicy.php
app/Policies/NotePolicy.php
app/Policies/AsyncJobPolicy.php
app/Policies/AiChatPolicy.php
```

## Events & Listeners

```
app/Events/LeadStatusChanged.php
app/Listeners/LogLeadStatusChange.php
```

## Notifications

```
app/Notifications/LeadAssignedNotification.php
app/Notifications/LeadStatusChangedNotification.php
app/Notifications/FollowUpReminderNotification.php
app/Notifications/DealExpiringNotification.php
app/Notifications/CustomerHealthAlertNotification.php
app/Notifications/TaskDueNotification.php
app/Notifications/MentionedInNoteNotification.php
```

## Mail

```
app/Mail/OneTimePasswordMail.php
resources/views/emails/one-time-password.blade.php
```

## Jobs

```
app/Jobs/SendCampaignEmailJob.php
app/Jobs/ProcessImportJob.php
app/Jobs/DispatchWebhookJob.php
app/Jobs/ProcessAsyncJob.php
```

## Commands

```
app/Console/Commands/SendFollowUpReminders.php
app/Console/Commands/SendDealExpiryAlerts.php
app/Console/Commands/SendTaskReminders.php
app/Console/Commands/SendScheduledCampaigns.php
app/Console/Commands/PruneAuditLogs.php
app/Console/Commands/RecalculateLeadScores.php
```

## Config

```
config/crm.php
```

## Factories

```
database/factories/OrganizationFactory.php
database/factories/LeadFactory.php
database/factories/LeadActivityFactory.php
database/factories/ContactFactory.php
database/factories/CustomerFactory.php
database/factories/CustomerActivityFactory.php
database/factories/DealFactory.php
database/factories/TaskFactory.php
database/factories/TagFactory.php
database/factories/EmailTemplateFactory.php
database/factories/CampaignFactory.php
database/factories/AttachmentFactory.php
database/factories/ImportFactory.php
database/factories/CustomFieldDefinitionFactory.php
database/factories/CustomFieldGroupFactory.php
database/factories/ProductFactory.php
database/factories/ProductCategoryFactory.php
database/factories/TaxFactory.php
database/factories/DealItemFactory.php
database/factories/WebhookEndpointFactory.php
database/factories/NoteFactory.php
database/factories/AsyncJobFactory.php
```

## Seeders

```
database/seeders/RolesAndPermissionsSeeder.php
database/seeders/OrganizationSeeder.php
database/seeders/LeadSeeder.php
```

## Tests

```
tests/Feature/Api/OrganizationTest.php
tests/Feature/Api/LeadTest.php
tests/Feature/Api/LeadActivityTest.php
tests/Feature/Api/ContactTest.php
tests/Feature/Api/DashboardTest.php
tests/Feature/Api/CustomerTest.php
tests/Feature/Api/CustomerActivityTest.php
tests/Feature/Api/DealTest.php
tests/Feature/Api/ReportTest.php
tests/Feature/Api/NotificationTest.php
tests/Feature/Api/TaskTest.php
tests/Feature/Api/TagTest.php
tests/Feature/Api/EmailTemplateTest.php
tests/Feature/GenerateEmailTemplateTest.php
tests/Feature/AiChatTest.php
tests/Feature/Api/CampaignTest.php
tests/Feature/Api/AuditLogTest.php
tests/Feature/Api/AuthorizationTest.php
tests/Feature/Api/AttachmentTest.php
tests/Feature/Api/ImportExportTest.php
tests/Feature/Api/SearchTest.php
tests/Feature/Api/DashboardTest.php
tests/Feature/Api/UserSettingsTest.php
tests/Feature/Api/UserManagementTest.php
tests/Feature/CustomFieldTest.php
tests/Feature/CustomFieldGroupTest.php
tests/Feature/CustomFieldGroupApiTest.php
tests/Feature/CustomFieldValidationTest.php
tests/Feature/CustomFieldCategoryReconciliationTest.php
tests/Feature/CustomFieldFilterTest.php
tests/Feature/ProductDetailTest.php
tests/Feature/Api/LeadScoringTest.php
tests/Feature/Api/ProductTest.php
tests/Feature/Api/ProductCategoryTest.php
tests/Feature/TaxTest.php
tests/Feature/DocumentLineTotalsTest.php
tests/Feature/DocumentLinePercentTest.php
tests/Feature/SalesFeatureFlagTest.php
tests/Feature/Api/DealItemTest.php
tests/Feature/Api/WebhookTest.php
tests/Feature/Api/NoteTest.php
tests/Feature/Api/TimelineTest.php
tests/Feature/Api/AsyncJobTest.php
tests/Feature/Api/AsyncExportTest.php
```

## Modified Files

```
app/Models/User.php                     — add HasRoles trait + CRM relationships
app/Providers/AppServiceProvider.php    — register event listener
routes/api.php                          — register all new routes
routes/console.php                      — register scheduled commands
composer.json                           — add spatie/laravel-permission
config/filesystems.php                  — add 'attachments' disk
```

## Current custom-field groups frontend files

```
resources/js/composables/useCustomFields.ts
resources/js/services/customFieldService.ts
resources/js/stores/customFields.ts
resources/js/utils/customFieldTypes.ts
resources/js/components/shared/CustomFieldValue.vue
resources/js/components/shared/CustomFieldFilterBar.vue
resources/js/components/shared/CustomFieldDefinitionEditor.vue
resources/js/views/settings/CustomFieldGroupsView.vue
resources/js/views/products/ProductDetailView.vue
```
