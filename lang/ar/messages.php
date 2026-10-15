<?php

return [
    // Auth
    'unauthorized' => 'غير مصرح لك بالوصول.',
    'unauthenticated' => 'يجب تسجيل الدخول أولاً.',
    'account_inactive' => 'تم تعطيل حسابك. يرجى التواصل مع المسؤول.',
    'invalid_credentials' => 'بيانات الاعتماد غير صحيحة.',
    'one_time_password_subject' => 'رمز التحقق الخاص بك',
    'one_time_password_intro' => 'استخدم الرمز التالي لمرة واحدة للمتابعة:',
    'one_time_password_expiry' => 'تنتهي صلاحية هذا الرمز خلال :minutes دقائق.',
    'one_time_password_ignore' => 'إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.',
    'password_reset_otp_sent' => 'إذا كان هناك حساب نشط بهذا البريد، فسيتم إرسال رمز استعادة كلمة المرور.',
    'invalid_password_reset_otp' => 'رمز استعادة كلمة المرور غير صالح أو منتهي الصلاحية.',
    'password_reset_success' => 'تمت استعادة كلمة المرور بنجاح. يرجى تسجيل الدخول بكلمة المرور الجديدة.',
    'quotation_title' => 'عرض سعر',
    'quotation_open_opportunity' => 'يمكن إنشاء عرض سعر فقط لفرصة مفتوحة.',
    'quotation_to' => 'مقدم إلى',
    'quotation_contact' => 'جهة الاتصال',
    'quotation_tax_id' => 'الرقم الضريبي',
    'quotation_opportunity' => 'الفرصة',
    'quotation_valid_until' => 'صالح حتى',
    'quotation_item' => 'البند',
    'quotation_item_discount' => 'الخصم',
    'quotation_item_tax' => 'الضريبة',
    'quotation_sku' => 'رمز المنتج',
    'quotation_quantity' => 'الكمية',
    'quotation_unit_price' => 'سعر الوحدة',
    'quotation_subtotal' => 'المجموع الفرعي',
    'quotation_discount' => 'الخصم',
    'quotation_tax' => 'الضريبة',
    'quotation_total' => 'الإجمالي',
    'quotation_notes' => 'ملاحظات',
    'quotation_terms' => 'الشروط والأحكام',
    'quotation_default_terms' => 'الأسعار صالحة حتى التاريخ الموضح أعلاه. يخضع عرض السعر للتأكيد.',
    'quotation_email_subject' => 'عرض السعر :number',
    'quotation_email_greeting' => 'مرحباً :name،',
    'quotation_email_intro' => 'يرجى العثور على عرض السعر :number مرفقاً.',
    'quotation_email_attachment' => 'تم إرفاق ملف PDF الخاص بعرض السعر بهذه الرسالة.',
    'quotation_email_expiry' => 'عرض السعر صالح حتى :date.',
    'opportunity_expiring_title' => 'اقتراب انتهاء الفرصة',
    'opportunity_expiring_body' => 'ستنتهي الفرصة ":opportunity" للعميل :customer بتاريخ :date.',
    'quotation_sent' => 'تم إرسال عرض السعر',
    'quotations_disabled' => 'عروض الأسعار معطلة في إعدادات الشركة.',
    'invoices_disabled' => 'الفواتير معطلة في إعدادات الشركة.',
    'tax_delete_has_products' => 'لا يمكن حذف هذه الضريبة لأن منتجات تستخدمها.',
    'invoice_created' => 'تم إنشاء الفاتورة',
    'invoice_title' => 'فاتورة',
    'invoice_to' => 'فاتورة إلى',
    'invoice_contact' => 'جهة الاتصال',
    'invoice_tax_id' => 'الرقم الضريبي',
    'invoice_issued_on' => 'تاريخ الإصدار',
    'invoice_opportunity' => 'الفرصة',
    'invoice_status' => 'الحالة',
    'invoice_item' => 'البند',
    'invoice_item_discount' => 'الخصم',
    'invoice_item_tax' => 'الضريبة',
    'invoice_sku' => 'رمز المنتج',
    'invoice_quantity' => 'الكمية',
    'invoice_unit_price' => 'سعر الوحدة',
    'invoice_subtotal' => 'المجموع الفرعي',
    'invoice_discount' => 'الخصم',
    'invoice_tax' => 'الضريبة',
    'invoice_total' => 'الإجمالي',

    // WhatsApp
    'whatsapp_quotation_greeting' => 'مرحباً :name،',
    'whatsapp_quotation_intro' => 'يسرنا أن نرسل لكم عرض السعر :number من :company بإجمالي قدره :total.',
    'whatsapp_quotation_validity' => 'عرض السعر صالح حتى :date.',
    'whatsapp_invoice_greeting' => 'مرحباً :name،',
    'whatsapp_invoice_intro' => 'تجدون الفاتورة رقم :number من :company بإجمالي قدره :total.',
    'whatsapp_general_greeting' => 'مرحباً :name،',
    'whatsapp_general_body' => 'شكراً لتواصلك معنا، يسعدنا دائماً خدمتك.',
    'whatsapp_download_pdf' => 'يمكنك استعراض وتحميل ملف PDF عبر الرابط التالي: :url',
    'whatsapp_closing' => 'مع أطيب التحيات، :company',
    'whatsapp_activity_subject' => 'تم إرسال رسالة واتساب إلى :recipient',
    'whatsapp_invalid_phone' => 'أدخل رقم هاتف دولياً صالحاً.',
    'whatsapp_send_failed' => 'تعذر إرسال رسالة واتساب.',
    'invalid_signature' => 'هذا الرابط منتهي الصلاحية أو غير صالح.',

    // CRUD
    'created' => 'تم الإنشاء بنجاح.',
    'updated' => 'تم التحديث بنجاح.',
    'deleted' => 'تم الحذف بنجاح.',
    'not_found' => 'العنصر المطلوب غير موجود.',

    // Lead
    'lead_assigned' => 'تم تعيين العميل المحتمل لك.',
    'lead_status_invalid_transition' => 'لا يمكن الانتقال من الحالة :from إلى الحالة :to.',
    'lead_won_customer_created' => 'تم تحويل العميل المحتمل إلى عميل فعلي.',

    // Task
    'task_due' => 'تذكير: المهمة ":task" تستحق اليوم.',
    'task_overdue' => 'المهمة ":task" متأخرة.',

    // Activity reminders
    'activity_reminder_title' => 'تذكير بنشاط',
    'activity_reminder_subject' => 'تذكير: :subject',
    'activity_reminder_body' => 'تذكير: ":subject" للعميل :customer مستحق بتاريخ :due.',
    'view_activity' => 'عرض النشاط',

    // Settings
    'password_changed' => 'تم تغيير كلمة المرور بنجاح.',
    'avatar_uploaded' => 'تم رفع الصورة الشخصية بنجاح.',
    'avatar_deleted' => 'تم حذف الصورة الشخصية.',
    'settings_updated' => 'تم تحديث الإعدادات بنجاح.',
    'company_logo_deleted' => 'تم حذف شعار الشركة.',

    // Tags / sync
    'tags_synced' => 'تم تحديث الوسوم بنجاح.',

    // Import / Export
    'import_queued' => 'جاري معالجة الاستيراد في الخلفية.',
    'export_queued' => 'جاري إعداد ملف التصدير.',

    // Webhooks
    'webhook_dispatched' => 'تم إرسال الـ Webhook بنجاح.',
    'webhook_url_public_https' => 'يجب أن يستخدم رابط الويب هوك HTTPS وأن يشير حصراً إلى عناوين IP عامة.',
    'webhook_destination_blocked' => 'تم حظر تسليم الويب هوك لأن الوجهة غير متاحة للعامة.',
    'webhook_http_failed' => 'استجابت نقطة الويب هوك برمز HTTP :status.',
    'webhook_secret_minimum' => 'يجب ألا يقل المفتاح السري المخصص للويب هوك عن 32 حرفاً.',

    // Lead Products
    'lead_product_attached' => 'تم ربط المنتج بالعميل المحتمل بنجاح.',
    'lead_product_updated' => 'تم تحديث منتج العميل المحتمل بنجاح.',
    'lead_product_detached' => 'تم إلغاء ربط المنتج من العميل المحتمل.',
    'lead_products_synced' => 'تم تحديث منتجات العميل المحتمل بنجاح.',

    // Async Jobs
    'async_job_not_ready' => 'لم تكتمل المهمة بعد.',
    'file_not_found' => 'لم يتم العثور على الملف المطلوب.',

    // Roles
    'role_system_delete' => 'لا يمكن حذف الأدوار النظامية (admin، manager، sales_rep).',

    // Product Categories
    'product_category_delete_has_relations' => 'لا يمكن حذف فئة مرتبطة بـ :products أو فئات فرعية.',

    // AI
    'ai_generation_failed' => 'فشل إنشاء المحتوى بالذكاء الاصطناعي. يرجى المحاولة مرة أخرى.',
];
