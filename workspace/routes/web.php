<?php

use App\Http\Controllers\Admin\AiIntegrationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DeploymentController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserActivationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Executive\DecisionLogController;
use App\Http\Controllers\Executive\FinancialApprovalController;
use App\Http\Controllers\Executive\ProjectApprovalController;
use App\Http\Controllers\Executive\TeamController;
use App\Http\Controllers\HealthContentController;
use App\Http\Controllers\HiringRequestController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\Medical\ContentReviewController;
use App\Http\Controllers\Medical\QuestionAlertController;
use App\Http\Controllers\Medical\ReviewLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectWorkflowController;
use App\Http\Controllers\PurchaseOrderApprovalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReferenceLetterController;
use App\Http\Controllers\Reports\ClientStatusReportController;
use App\Http\Controllers\Reports\ExecutiveReportController;
use App\Http\Controllers\Reports\FinanceReportController;
use App\Http\Controllers\Reports\MedicalReportController;
use App\Http\Controllers\Reports\MyTasksReportController;
use App\Http\Controllers\Reports\ProjectManagerReportController;
use App\Http\Controllers\Reports\TechnicalReportController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskMoveController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/* نظام داخلي بلا صفحة تعريفية عامة — الجذر يوجّه مباشرة للوحة أو الدخول. */
Route::get('/', function () {
    return redirect()->to(auth()->check() ? '/dashboard' : '/login');
});

/* التحقق العام من الشهادات والإفادات والبطاقات برمزها المطبوع — بلا دخول. */
Route::get('verify/{code?}', VerificationController::class)
    ->middleware('throttle:30,1')
    ->name('verify.show');

/* المحتوى الصحي المعتمد: صفحة عامة يستشهد بها مساعد المنصة حين يجيب منه. */
Route::get('kb/{content}', KnowledgeBaseController::class)->name('kb.show');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sections/{tab}', SectionController::class)->name('sections.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /* المشاريع: الصلاحيات على مستوى السجل في ProjectPolicy. */
    Route::resource('projects', ProjectController::class);
    Route::prefix('projects/{project}')->name('projects.')->scopeBindings()->group(function () {
        Route::post('submit', [ProjectWorkflowController::class, 'submit'])->name('submit');
        Route::post('decisions', [ProjectWorkflowController::class, 'decide'])->name('decide');
        Route::post('start', [ProjectWorkflowController::class, 'start'])->name('start');
        Route::post('complete', [ProjectWorkflowController::class, 'complete'])->name('complete');

        Route::get('members', [ProjectMemberController::class, 'index'])->name('members.index');
        Route::post('members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::patch('members/{member}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::get('milestones', [ProjectMilestoneController::class, 'index'])->name('milestones.index');
        Route::post('milestones', [ProjectMilestoneController::class, 'store'])->name('milestones.store');
        Route::put('milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy');
        Route::post('milestones/{milestone}/toggle', [ProjectMilestoneController::class, 'toggle'])->name('milestones.toggle');

        Route::get('tasks', [ProjectTaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/create', [ProjectTaskController::class, 'create'])->name('tasks.create');
        Route::post('tasks', [ProjectTaskController::class, 'store'])->name('tasks.store');

        Route::get('files', [AttachmentController::class, 'index'])->name('files.index');
        Route::post('files', [AttachmentController::class, 'storeForProject'])->name('files.store');
    });

    /* المهام خارج سياق مشروع واحد. */
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('my-tasks', [TaskController::class, 'mine'])->name('tasks.mine');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('tasks/{task}/move', TaskMoveController::class)->name('tasks.move');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('tasks/{task}/files', [AttachmentController::class, 'storeForTask'])->name('tasks.files.store');

    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /* المالية: العقود وأوامر الشراء والفواتير. الصلاحيات في سياسات كل مستند. */
    Route::resource('contracts', ContractController::class);
    Route::post('contracts/{contract}/activate', [ContractController::class, 'activate'])->name('contracts.activate');
    Route::post('contracts/{contract}/close', [ContractController::class, 'close'])->name('contracts.close');
    Route::post('contracts/{contract}/files', [AttachmentController::class, 'storeForContract'])->name('contracts.files.store');

    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
    Route::post('purchase-orders/{purchase_order}/review', [PurchaseOrderApprovalController::class, 'review'])->name('purchase-orders.review');
    Route::post('purchase-orders/{purchase_order}/decision', [PurchaseOrderApprovalController::class, 'decide'])->name('purchase-orders.decide');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchase_order}/files', [AttachmentController::class, 'storeForPurchaseOrder'])->name('purchase-orders.files.store');

    Route::resource('invoices', InvoiceController::class);
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');

    /* الشهادات والإفادات والبطاقة الرقمية. */
    Route::resource('certificates', CertificateController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');
    Route::resource('reference-letters', ReferenceLetterController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('reference-letters/{reference_letter}/approve', [ReferenceLetterController::class, 'approve'])->name('reference-letters.approve');
    Route::post('reference-letters/{reference_letter}/reject', [ReferenceLetterController::class, 'reject'])->name('reference-letters.reject');
    Route::get('my-card', CardController::class)->name('card.show');

    /* طلبات التوظيف: يطلبها مسؤولو الفرق ويقرّرها المدير التنفيذي. */
    Route::resource('hiring-requests', HiringRequestController::class)->except(['destroy']);
    Route::post('hiring-requests/{hiring_request}/approve', [HiringRequestController::class, 'approve'])->name('hiring-requests.approve');
    Route::post('hiring-requests/{hiring_request}/reject', [HiringRequestController::class, 'reject'])->name('hiring-requests.reject');
    Route::post('hiring-requests/{hiring_request}/fill', [HiringRequestController::class, 'fill'])->name('hiring-requests.fill');
    Route::post('hiring-requests/{hiring_request}/cancel', [HiringRequestController::class, 'cancel'])->name('hiring-requests.cancel');

    /* التقارير: لكل دور تقريره، والمدير التنفيذي يطّلع على المالي أيضاً. */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('executive', ExecutiveReportController::class)->middleware('role:executive')->name('executive');
        Route::get('projects', ProjectManagerReportController::class)->middleware('role:pm')->name('pm');
        Route::get('finance', FinanceReportController::class)->middleware('role:finance|executive')->name('finance');
        Route::get('my-tasks', MyTasksReportController::class)->middleware('role:team_member')->name('mine');
        Route::get('project-status', ClientStatusReportController::class)->middleware('role:client')->name('client');
        Route::get('technical', TechnicalReportController::class)->middleware('role:sysadmin')->name('technical');
        Route::get('medical', MedicalReportController::class)->middleware('role:medical')->name('medical');
    });

    /* المحتوى الصحي: يحرّره مدير النظام ويعتمده المدير الطبي قبل نشره. */
    Route::middleware('role:sysadmin|medical')->group(function () {
        Route::resource('content', HealthContentController::class);
        Route::post('content/{content}/submit', [HealthContentController::class, 'submit'])->name('content.submit');
        Route::post('content/{content}/withdraw', [HealthContentController::class, 'withdraw'])->name('content.withdraw');
    });

    /* المدير الطبي: المراجعة وسجلها والتنبيهات. */
    Route::middleware('role:medical')->prefix('medical')->name('medical.')->group(function () {
        Route::get('review', [ContentReviewController::class, 'index'])->name('review');
        Route::get('review/{content}', [ContentReviewController::class, 'show'])->name('review.show');
        Route::post('review/{content}/approve', [ContentReviewController::class, 'approve'])->name('review.approve');
        Route::post('review/{content}/reject', [ContentReviewController::class, 'reject'])->name('review.reject');
        Route::post('review/{content}/renew', [ContentReviewController::class, 'renew'])->name('review.renew');
        Route::get('log', [ReviewLogController::class, 'index'])->name('log');
        Route::get('alerts', [QuestionAlertController::class, 'index'])->name('alerts');
        Route::post('alerts/{chatLog}/review', [QuestionAlertController::class, 'review'])->whereNumber('chatLog')->name('alerts.review');
        Route::post('alerts/terms', [QuestionAlertController::class, 'storeTerm'])->name('terms.store');
        Route::delete('alerts/terms/{term}', [QuestionAlertController::class, 'destroyTerm'])->name('terms.destroy');
    });

    /* المدير التنفيذي. */
    Route::middleware('role:executive')->group(function () {
        Route::get('approvals/projects', [ProjectApprovalController::class, 'index'])->name('approvals.projects');
        Route::get('approvals/financial', [FinancialApprovalController::class, 'index'])->name('approvals.financial');
        Route::get('decisions', [DecisionLogController::class, 'index'])->name('decisions.index');
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
    });

    /* مدير النظام: الحسابات والسجل. */
    Route::middleware('role:sysadmin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/deactivate', [UserActivationController::class, 'destroy'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserActivationController::class, 'store'])->name('users.reactivate');
        Route::post('users/{user}/invitation', [UserInvitationController::class, 'store'])->name('users.invitation');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('system/health', SystemHealthController::class)->name('system.health');
        Route::get('system/mail', [MailSettingsController::class, 'show'])->name('system.mail');
        Route::post('system/mail/test', [MailSettingsController::class, 'send'])->middleware('throttle:5,1')->name('system.mail.test');
        Route::get('system/deployment', DeploymentController::class)->name('system.deployment');
        Route::get('system/ai', AiIntegrationController::class)->name('system.ai');
    });
});

require __DIR__.'/auth.php';
