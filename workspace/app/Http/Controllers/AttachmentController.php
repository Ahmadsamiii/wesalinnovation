<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مرفقات المشروع ومهامه. تُحفظ على القرص الخاص ولا تُقدَّم إلا من هنا بعد فحص
 * الصلاحية، وتُحمَّل دائماً كملف لا كصفحة تُعرض في المتصفح.
 */
class AttachmentController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewInternals', $project);

        $taskIds = $project->tasks()->pluck('id');

        $attachments = Attachment::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('attachable_type', 'project')->where('attachable_id', $project->id))
                ->orWhere(fn ($query) => $query->where('attachable_type', 'task')->whereIn('attachable_id', $taskIds)))
            ->with(['uploader', 'attachable'])
            ->latest('id')
            ->get();

        return view('projects.files', ['project' => $project, 'attachments' => $attachments]);
    }

    public function storeForProject(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('upload', $project);

        return $this->store($request, $project, $project);
    }

    public function storeForTask(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('upload', $task->project);

        return $this->store($request, $task, $task->project);
    }

    public function storeForContract(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize('upload', $contract);

        return $this->store($request, $contract, $contract->project);
    }

    public function storeForPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('upload', $purchaseOrder);

        return $this->store($request, $purchaseOrder, $purchaseOrder->project);
    }

    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        abort_unless(Storage::disk(Attachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(Attachment::DISK)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        AuditLog::record(AuditAction::AttachmentDeleted, $attachment->project(), ['file' => $attachment->original_name]);
        $attachment->deleteWithFile();

        return back()->with('status', 'حُذف المرفق.');
    }

    private function store(Request $request, Model $attachable, Project $project): RedirectResponse
    {
        $request->validate([
            'file' => ['required', File::types(Attachment::ALLOWED_EXTENSIONS)->max(Attachment::MAX_KILOBYTES)],
        ], [
            'file.mimes' => 'نوع الملف غير مقبول. المقبول: '.implode('، ', Attachment::ALLOWED_EXTENSIONS).'.',
        ]);

        $attachment = Attachment::storeUpload($request->file('file'), $attachable, $request->user());

        AuditLog::record(AuditAction::AttachmentUploaded, $project, ['file' => $attachment->original_name]);

        return back()->with('status', 'رُفع الملف «'.$attachment->original_name.'».');
    }
}
