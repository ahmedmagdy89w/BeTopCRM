<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;

return [
    'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'max_size_mb' => env('ATTACHMENTS_MAX_SIZE_MB', 10),
        'max_per_upload' => 5,
    ],

    'ai' => [
        'model' => env('AI_MODEL', 'gemma3:4b'),

        // Default model hint per text-capable provider for the settings UI.
        // A model stored in company settings (ai_model) always wins over these.
        'provider_models' => [
            'gemini' => 'gemini-2.0-flash',
            'openai' => 'gpt-4o',
            'anthropic' => 'claude-3-5-sonnet-latest',
            'ollama' => 'gemma3:4b',
            'groq' => 'llama-3.3-70b-versatile',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-large-latest',
            'openrouter' => 'openai/gpt-4o',
            'xai' => 'grok-beta',
            'azure' => 'gpt-4o',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Field Entities
    |--------------------------------------------------------------------------
    |
    | The single registry of which models support custom fields, and which of
    | them additionally scope fields to a category. Adding a model is one entry
    | here; the validation, filtering, and rendering layers read this and never
    | hard-code a model list. An entity without a 'category' key simply has no
    | category scope.
    |
    | Model classes are FQCN strings so config:cache stays safe. 'values_column'
    | is the column on the entity table holding the category-scoped values,
    | which is deliberately separate from 'custom_fields' so a model-level and
    | a category-level field may share a name without colliding.
    |
    */

    'custom_fields' => [
        'entities' => [
            'product' => [
                'model' => Product::class,
                'table' => 'products',
                'label' => 'products.title',
                'category' => [
                    'table' => 'product_categories',
                    'foreign_key' => 'category_id',
                    'label' => 'products.categories',
                    'values_column' => 'category_custom_fields',
                ],
            ],

            'customer' => [
                'model' => Customer::class,
                'table' => 'customers',
                'label' => 'customers.title',
            ],

            'opportunity' => [
                'model' => Opportunity::class,
                'table' => 'opportunities',
                'label' => 'opportunities.title',
            ],

        ],
    ],

    'lead_scoring' => [
        'weights' => [
            // Profile completeness (max 25 pts)
            'has_email' => 5,
            'has_phone' => 5,
            'has_organization' => 5,
            'has_title' => 3,
            'has_description' => 2,
            'has_estimated_value' => 5,

            // Engagement (max 50 pts)
            'activity_count' => 2,  // per activity, capped at 20 pts
            'activity_recency' => 15, // full points if activity in last 7 days, decays
            'email_opened' => 5,  // from campaign recipient tracking
            'email_clicked' => 10, // from campaign recipient tracking

            // Pipeline position (max 25 pts)
            'status_contacted' => 5,
            'status_qualified' => 10,
            'status_proposal' => 15,
            'status_negotiation' => 20,
        ],
        'recency_decay_days' => 30,
        'stale_after_hours' => 24,
    ],
];
