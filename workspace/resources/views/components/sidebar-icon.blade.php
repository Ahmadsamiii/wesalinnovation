@props(['name'])

@php
/*
 * أيقونة لكل مفتاح تبويب في config/roles.php — مفتاح غير معروف يأخذ أيقونة
 * عامة بدل أن يكسر القائمة، فإضافة تبويب جديد للإعدادات لا تحتاج تعديل هنا.
 */
$iconForTab = [
    'overview' => 'grid',
    'projects_approval' => 'badge-check', 'financial_approvals' => 'badge-check',
    'completion_certs' => 'award', 'completion_cert' => 'award',
    'hiring_requests' => 'user-plus',
    'team' => 'users', 'tasks_team' => 'users',
    'reports' => 'chart', 'financial_reports' => 'chart', 'tech_reports' => 'chart',
    'review_reports' => 'chart', 'my_report' => 'chart', 'status_report' => 'chart',
    'decisions_log' => 'history', 'audit_log' => 'history', 'content_log' => 'history',
    'my_projects' => 'folder', 'project_status' => 'folder',
    'contracts_po' => 'file', 'contracts' => 'file', 'my_contracts' => 'file', 'purchase_orders' => 'file',
    'invoices' => 'receipt', 'my_invoices' => 'receipt',
    'content' => 'edit', 'content_review' => 'edit',
    'roles_permissions' => 'key',
    'system_health' => 'activity',
    'ai_integration' => 'sparkles',
    'email_integration' => 'mail',
    'domains_deploy' => 'globe',
    'sensitive_alerts' => 'alert',
    'my_tasks' => 'check-square',
    'my_card' => 'id-card',
    'reference_request' => 'send',
    'dashboard' => 'grid', 'profile' => 'user', 'logout' => 'logout',
];

$paths = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'badge-check' => '<path d="M12 3l2.4 1.8 3-.2.9 2.9 2.4 1.8-1 2.8 1 2.8-2.4 1.8-.9 2.9-3-.2L12 21l-2.4-1.8-3 .2-.9-2.9-2.4-1.8 1-2.8-1-2.8 2.4-1.8.9-2.9 3 .2z"/><path d="M9 12l2 2 4-4"/>',
    'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14.5L7 22l5-3 5 3-1.5-7.5"/>',
    'user-plus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M19 8v6M16 11h6"/>',
    'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
    'chart' => '<path d="M3 3v18h18"/><path d="M7 15v2M11 10v7M15 12v5M19 7v10"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
    'receipt' => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    'key' => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M18 5l2 2"/>',
    'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    'sparkles' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    'alert' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
    'check-square' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l3 3 5-6"/>',
    'id-card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M14 10h4M14 14h3"/>',
    'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    'dot' => '<circle cx="12" cy="12" r="3"/>',
];

$icon = $iconForTab[$name] ?? 'dot';
@endphp

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$icon] !!}</svg>
