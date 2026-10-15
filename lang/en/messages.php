<?php

return [
    // Auth
    'unauthorized' => 'You are not authorized to perform this action.',
    'unauthenticated' => 'You must be logged in.',
    'account_inactive' => 'Your account has been deactivated. Please contact an administrator.',
    'invalid_credentials' => 'The provided credentials are incorrect.',
    'one_time_password_subject' => 'Your verification code',
    'one_time_password_intro' => 'Use the following one-time code to continue:',
    'one_time_password_expiry' => 'This code expires in :minutes minutes.',
    'one_time_password_ignore' => 'If you did not request this code, you can ignore this email.',
    'password_reset_otp_sent' => 'If an active account exists for that email, a password reset code has been sent.',
    'invalid_password_reset_otp' => 'The password reset code is invalid or expired.',
    'password_reset_success' => 'Password reset successfully. Please log in with your new password.',
    'quotation_title' => 'Quotation',
    'quotation_open_opportunity' => 'A quotation can only be created for an open opportunity.',
    'quotation_to' => 'Prepared for',
    'quotation_contact' => 'Contact',
    'quotation_tax_id' => 'Tax ID',
    'quotation_opportunity' => 'Opportunity',
    'quotation_valid_until' => 'Valid until',
    'quotation_item' => 'Item',
    'quotation_item_discount' => 'Discount',
    'quotation_item_tax' => 'Tax',
    'quotation_sku' => 'SKU',
    'quotation_quantity' => 'Quantity',
    'quotation_unit_price' => 'Unit price',
    'quotation_subtotal' => 'Subtotal',
    'quotation_discount' => 'Discount',
    'quotation_tax' => 'Tax',
    'quotation_total' => 'Total',
    'quotation_notes' => 'Notes',
    'quotation_terms' => 'Terms and conditions',
    'quotation_default_terms' => 'Prices are valid until the date shown above. This quotation is subject to confirmation.',
    'quotation_email_subject' => 'Quotation :number',
    'quotation_email_greeting' => 'Hello :name,',
    'quotation_email_intro' => 'Please find quotation :number attached.',
    'quotation_email_attachment' => 'The quotation PDF is attached to this email.',
    'quotation_email_expiry' => 'This quotation is valid until :date.',
    'opportunity_expiring_title' => 'Opportunity Expiring Soon',
    'opportunity_expiring_body' => 'Opportunity ":opportunity" for :customer expires on :date.',
    'quotation_sent' => 'Quotation sent',
    'quotations_disabled' => 'Quotations are disabled in Company Settings.',
    'invoices_disabled' => 'Invoices are disabled in Company Settings.',
    'tax_delete_has_products' => 'This tax cannot be deleted because products are using it.',
    'invoice_created' => 'Invoice created',
    'invoice_title' => 'Invoice',
    'invoice_to' => 'Bill to',
    'invoice_contact' => 'Contact',
    'invoice_tax_id' => 'Tax ID',
    'invoice_issued_on' => 'Issued on',
    'invoice_opportunity' => 'Opportunity',
    'invoice_status' => 'Status',
    'invoice_item' => 'Item',
    'invoice_item_discount' => 'Discount',
    'invoice_item_tax' => 'Tax',
    'invoice_sku' => 'SKU',
    'invoice_quantity' => 'Quantity',
    'invoice_unit_price' => 'Unit price',
    'invoice_subtotal' => 'Subtotal',
    'invoice_discount' => 'Discount',
    'invoice_tax' => 'Tax',
    'invoice_total' => 'Total',

    // WhatsApp
    'whatsapp_quotation_greeting' => 'Hello :name,',
    'whatsapp_quotation_intro' => 'We are pleased to send you quotation :number from :company with a total of :total.',
    'whatsapp_quotation_validity' => 'This quotation is valid until :date.',
    'whatsapp_invoice_greeting' => 'Hello :name,',
    'whatsapp_invoice_intro' => 'Here is invoice :number from :company with a total of :total.',
    'whatsapp_general_greeting' => 'Hello :name,',
    'whatsapp_general_body' => 'Thank you for being in touch with us.',
    'whatsapp_download_pdf' => 'You can view and download the PDF document here: :url',
    'whatsapp_closing' => 'Best regards, :company',
    'whatsapp_activity_subject' => 'WhatsApp message sent to :recipient',
    'whatsapp_invalid_phone' => 'Enter a valid international phone number.',
    'whatsapp_send_failed' => 'The WhatsApp message could not be sent.',
    'invalid_signature' => 'This document link has expired or is invalid.',

    // CRUD
    'created' => 'Created successfully.',
    'updated' => 'Updated successfully.',
    'deleted' => 'Deleted successfully.',
    'not_found' => 'The requested resource was not found.',

    // Lead
    'lead_assigned' => 'Lead has been assigned to you.',
    'lead_status_invalid_transition' => 'Cannot transition from :from to :to.',
    'lead_won_customer_created' => 'Lead converted to customer.',

    // Task
    'task_due' => 'Reminder: Task ":task" is due today.',
    'task_overdue' => 'Task ":task" is overdue.',

    // Activity reminders
    'activity_reminder_title' => 'Activity Reminder',
    'activity_reminder_subject' => 'Reminder: :subject',
    'activity_reminder_body' => 'Reminder: ":subject" for :customer is due :due.',
    'view_activity' => 'View Activity',

    // Settings
    'password_changed' => 'Password changed successfully.',
    'avatar_uploaded' => 'Avatar uploaded successfully.',
    'avatar_deleted' => 'Avatar deleted.',
    'settings_updated' => 'Settings updated successfully.',
    'company_logo_deleted' => 'Company logo deleted.',

    // Tags / sync
    'tags_synced' => 'Tags updated successfully.',

    // Import / Export
    'import_queued' => 'Your import is being processed in the background.',
    'export_queued' => 'Your export file is being prepared.',

    // Webhooks
    'webhook_dispatched' => 'Webhook dispatched successfully.',
    'webhook_url_public_https' => 'The webhook URL must use HTTPS and resolve only to public IP addresses.',
    'webhook_destination_blocked' => 'Webhook delivery was blocked because the destination is not publicly accessible.',
    'webhook_http_failed' => 'Webhook endpoint responded with HTTP status :status.',
    'webhook_secret_minimum' => 'A custom webhook secret must be at least 32 characters long.',

    // Lead Products
    'lead_product_attached' => 'Product attached to lead successfully.',
    'lead_product_updated' => 'Lead product updated successfully.',
    'lead_product_detached' => 'Product detached from lead successfully.',
    'lead_products_synced' => 'Lead products updated successfully.',

    // Async Jobs
    'async_job_not_ready' => 'The job has not completed yet.',
    'file_not_found' => 'The requested file could not be found.',

    // Roles
    'role_system_delete' => 'System roles (admin, manager, sales_rep) cannot be deleted.',

    // Product Categories
    'product_category_delete_has_relations' => 'Cannot delete a category that has :products or sub-categories assigned to it.',

    // AI
    'ai_generation_failed' => 'AI generation failed. Please try again.',
];
