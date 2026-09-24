<?php

/*
|--------------------------------------------------------------------------
| أدوار نظام إدارة المشاريع
|--------------------------------------------------------------------------
| المصدر الوحيد لتعريف الأدوار السبعة ولوحاتها — القسم 3 من الوثيقة
| التنفيذية. RoleSeeder يقرأ المفاتيح لإنشاء أدوار Spatie، وشريط التبويبات
| (App\View\Components\RoleTabs) يقرأ tabs. لا يوجد تكرار لهذه القائمة في مكان آخر.
|
| لكل تبويب:
|   label   ما يظهر للمستخدم.
|   route   اسم المسار الذي يفتحه. تبويب لم يُبنَ مساره بعد يفتح صفحة
|           «قيد البناء» تلقائياً، فيُضاف المسار لاحقاً دون لمس هذا الملف.
|   active  (اختياري) أنماط أسماء المسارات التي يُبرَز معها التبويب. الافتراض:
|           «projects.*» لمسار «projects.index»، والاسم نفسه لغيره.
|
| العميل وعضو الفريق داخل نفس هذا الجدول؛ الوثيقة تصفهما كطرفين خارج الهيكل
| التنظيمي الإداري لكنهما مستخدمان بنفس نظام الحسابات والصلاحيات هنا.
*/

return [

    'executive' => [
        'label' => 'المدير التنفيذي',
        'tabs' => [
            'overview' => ['label' => 'نظرة عامة', 'route' => 'dashboard'],
            'projects_approval' => ['label' => 'اعتماد المشاريع', 'route' => 'approvals.projects'],
            'financial_approvals' => ['label' => 'الاعتمادات المالية', 'route' => 'approvals.financial'],
            'hiring_requests' => ['label' => 'طلبات التوظيف', 'route' => 'hiring-requests.index'],
            'team' => ['label' => 'الفريق', 'route' => 'team.index'],
            'reports' => ['label' => 'التقارير الشاملة', 'route' => 'reports.executive', 'active' => ['reports.executive', 'reports.finance']],
            'decisions_log' => ['label' => 'سجل القرارات', 'route' => 'decisions.index'],
        ],
    ],

    'pm' => [
        'label' => 'مدير المشاريع',
        'tabs' => [
            'my_projects' => ['label' => 'مشاريعي', 'route' => 'projects.index'],
            'tasks_team' => ['label' => 'المهام والفريق', 'route' => 'tasks.index'],
            'contracts_po' => ['label' => 'العقود وأوامر الشراء', 'route' => 'contracts.index', 'active' => ['contracts.*', 'purchase-orders.*']],
            'completion_certs' => ['label' => 'شهادات الإنجاز', 'route' => 'certificates.index'],
            'reports' => ['label' => 'التقارير', 'route' => 'reports.pm'],
        ],
    ],

    'finance' => [
        'label' => 'المدير المالي',
        'tabs' => [
            'invoices' => ['label' => 'الفواتير', 'route' => 'invoices.index'],
            'purchase_orders' => ['label' => 'أوامر الشراء', 'route' => 'purchase-orders.index'],
            'contracts' => ['label' => 'العقود', 'route' => 'contracts.index'],
            'financial_reports' => ['label' => 'التقارير المالية', 'route' => 'reports.finance'],
        ],
    ],

    'sysadmin' => [
        'label' => 'مدير النظام',
        'tabs' => [
            'content' => ['label' => 'إدارة المحتوى', 'route' => 'content.index'],
            'roles_permissions' => ['label' => 'الأدوار والصلاحيات', 'route' => 'users.index'],
            'system_health' => ['label' => 'صحة النظام', 'route' => 'system.health'],
            'ai_integration' => ['label' => 'تكامل الذكاء الاصطناعي', 'route' => 'system.ai'],
            'email_integration' => ['label' => 'تكامل البريد الإلكتروني', 'route' => 'system.mail'],
            'audit_log' => ['label' => 'السجلات والتدقيق', 'route' => 'audit.index'],
            'domains_deploy' => ['label' => 'النطاقات والنشر', 'route' => 'system.deployment'],
            'tech_reports' => ['label' => 'التقارير التقنية', 'route' => 'reports.technical'],
        ],
    ],

    'medical' => [
        'label' => 'المدير الطبي',
        'tabs' => [
            'content_review' => ['label' => 'قائمة مراجعة المحتوى الصحي', 'route' => 'medical.review', 'active' => ['medical.review', 'medical.review.*']],
            'content_log' => ['label' => 'سجل المحتوى المعتمد والمرفوض', 'route' => 'medical.log'],
            'sensitive_alerts' => ['label' => 'تنبيهات الأسئلة عالية الحساسية', 'route' => 'medical.alerts'],
            'review_reports' => ['label' => 'تقارير المراجعة', 'route' => 'reports.medical'],
        ],
    ],

    'team_member' => [
        'label' => 'عضو الفريق',
        'tabs' => [
            'my_tasks' => ['label' => 'مهامي', 'route' => 'tasks.mine', 'active' => ['tasks.*']],
            'my_card' => ['label' => 'بطاقتي الرقمية وشهاداتي', 'route' => 'card.show'],
            'reference_request' => ['label' => 'طلب إفادة', 'route' => 'reference-letters.index'],
            'my_report' => ['label' => 'تقرير مهامي', 'route' => 'reports.mine'],
        ],
    ],

    'client' => [
        'label' => 'العميل',
        'tabs' => [
            'project_status' => ['label' => 'حالة مشروعي', 'route' => 'projects.index'],
            'my_contracts' => ['label' => 'عقودي', 'route' => 'contracts.index'],
            'my_invoices' => ['label' => 'فواتيري', 'route' => 'invoices.index'],
            'completion_cert' => ['label' => 'شهادة إنجازي', 'route' => 'certificates.index'],
            'status_report' => ['label' => 'تقرير حالة مشروعي', 'route' => 'reports.client'],
        ],
    ],

];
