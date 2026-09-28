<?php

/*
|--------------------------------------------------------------------------
| إعدادات مساحة العمل
|--------------------------------------------------------------------------
| بيانات المنشأة تظهر في رأس الفاتورة المطبوعة؛ تُضبط في .env الإنتاج.
| الفاتورة المطبوعة هنا مستند داخلي للعميل، لا فاتورة إلكترونية مسجّلة لدى
| هيئة الزكاة والضريبة والجمارك (فاتورة) — ذلك تكامل مستقل لم يُبنَ.
*/

return [

    'company' => [
        'name' => env('COMPANY_NAME', 'وصال الابتكار'),
        'vat_number' => env('COMPANY_VAT_NUMBER'),
        'cr_number' => env('COMPANY_CR_NUMBER'),
        'address' => env('COMPANY_ADDRESS'),
        'email' => env('COMPANY_EMAIL'),
        'phone' => env('COMPANY_PHONE'),
    ],

    // ضريبة القيمة المضافة في السعودية (٪)، الافتراض لكل فاتورة وأمر شراء جديد.
    'vat_rate' => (float) env('VAT_RATE', 15),

    // أمر شراء إجماليه أعلى من هذا (ريال شامل الضريبة) يحتاج اعتماد المدير
    // التنفيذي بعد مراجعة المالية؛ ما دونه تعتمده المالية وحدها.
    'executive_approval_threshold' => (float) env('PO_EXECUTIVE_THRESHOLD', 50000),

    'invoice_payment_terms_days' => (int) env('INVOICE_PAYMENT_TERMS_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | الدخول الموحد مع المنصة العامة
    |--------------------------------------------------------------------------
    | true: الحساب والجلسة والدور من المنصة (App\Support\PlatformSession). الدخول
    | من شاشة واحدة، والخروج أو الإيقاف أو الخمول في أحد النظامين يُخرج الآخر.
    | يحتاج قاعدة المنصة (PLATFORM_DB_*) وحسابات مربوطة بأداة الربط. مطفأً (الافتراضي)
    | يبقى الدخول المحلي بكلمة مرور مساحة العمل كما كان.
    */
    'unified_auth' => (bool) env('UNIFIED_AUTH', false),
    // اسم كوكي الجلسة ثابت في عقد المنصة (api/session-lib.php)، فلا يُضبط من البيئة.
    'auth_cookie' => 'wesal_auth',
    'auth_cookie_domain' => env('UNIFIED_AUTH_COOKIE_DOMAIN'),
    'login_url' => env('UNIFIED_LOGIN_URL', 'https://wesalinnovation.sa/login'),
    'home_url' => env('WORKSPACE_HOME_URL', '/'),
    // رابط مساعد وصال في قائمة المستخدم. /chat ثابت قبل التبديل وبعده.
    'chat_url' => env('UNIFIED_CHAT_URL', 'https://wesalinnovation.sa/chat'),
    'session_grace_seconds' => 120,

    /*
    |--------------------------------------------------------------------------
    | العنوان: نطاق فرعي، أو مسار داخل الموقع الرئيسي
    |--------------------------------------------------------------------------
    | hosts: المضيفات التي يقبلها التطبيق (WORKSPACE_HOSTS بفواصل). فارغة: مضيف APP_URL
    |   وحده، أي السلوك القائم. القبول بلا تحويل هو وضع التجربة قبل التبديل.
    | canonical_url: العنوان المعتمد (https://wesalinnovation.sa/workspace). مع ضبطه يُحوَّل
    |   إليه كل طلب قراءة من مضيف آخر بمساره واستعلامه (RedirectToCanonicalHost).
    | canonical_status: 302 في أول أيام التبديل، ثم 301.
    */
    'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('WORKSPACE_HOSTS', ''))))),
    'canonical_url' => env('WORKSPACE_CANONICAL_URL'),
    'canonical_status' => (int) env('WORKSPACE_CANONICAL_STATUS', 302),

];
