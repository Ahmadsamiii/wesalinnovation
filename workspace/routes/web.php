<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserActivationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Executive\DecisionLogController;
use App\Http\Controllers\Executive\ProjectApprovalController;
use App\Http\Controllers\Executive\TeamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectWorkflowController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskMoveController;
use Illuminate\Support\Facades\Route;

/* نظام داخلي بلا صفحة تعريفية عامة — الجذر يوجّه مباشرة للوحة أو الدخول. */
Route::get('/', function () {
    return redirect()->to(auth()->check() ? '/dashboard' : '/login');
});

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

    /* المدير التنفيذي. */
    Route::middleware('role:executive')->group(function () {
        Route::get('approvals/projects', [ProjectApprovalController::class, 'index'])->name('approvals.projects');
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
    });
});

require __DIR__.'/auth.php';
