<?php

namespace Database\Seeders;

use App\Enums\ApprovalDecision;
use App\Enums\CertificateType;
use App\Enums\EmploymentType;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\ProjectDecisionType;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Models\Certificate;
use App\Models\Contract;
use App\Models\HiringRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\ReferenceLetter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * مشاريع تجريبية بكل حالات دورة الحياة لتجربة الواجهة محلياً. تعتمد على
 * حسابات DatabaseSeeder التجريبية — لتطوير محلي فقط.
 */
class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $pm = User::where('email', 'pm@wesalinnovation.sa')->firstOrFail();
        $executive = User::where('email', 'executive@wesalinnovation.sa')->firstOrFail();
        $client = User::where('email', 'client@wesalinnovation.sa')->firstOrFail();
        $member = User::where('email', 'team_member@wesalinnovation.sa')->firstOrFail();
        $designer = User::factory()->role('team_member')->create(['name' => 'ريم الشهري', 'email' => 'reem@wesalinnovation.sa', 'job_title' => 'مصممة تجربة مستخدم', 'department' => 'التصميم']);
        $developer = User::factory()->role('team_member')->create(['name' => 'خالد العمري', 'email' => 'khaled@wesalinnovation.sa', 'job_title' => 'مطور واجهات', 'department' => 'الهندسة']);

        $platform = Project::factory()->create([
            'name' => 'منصة التأهيل الرقمي',
            'description' => "منصة تدريب إلكتروني مهيأة لذوي الإعاقة البصرية والسمعية.\nتشمل: تجربة استخدام متوافقة مع قارئات الشاشة، ولغة إشارة مرئية، ولوحة متابعة للمدربين.",
            'pm_id' => $pm->id,
            'created_by' => $pm->id,
            'client_id' => $client->id,
            'priority' => Priority::High,
            'budget' => 480000,
            'start_date' => now()->subMonths(2)->startOfMonth(),
            'end_date' => now()->addMonths(2)->endOfMonth(),
        ]);
        $platform->members()->createMany([
            ['user_id' => $member->id, 'role' => ProjectMemberRole::Lead, 'joined_at' => now()->subMonths(2)],
            ['user_id' => $designer->id, 'role' => ProjectMemberRole::Member, 'joined_at' => now()->subMonths(2)],
            ['user_id' => $developer->id, 'role' => ProjectMemberRole::Member, 'joined_at' => now()->subMonth()],
        ]);
        $analysis = $platform->milestones()->create(['title' => 'التحليل والتصميم', 'due_date' => now()->subMonth(), 'position' => 0]);
        $analysis->forceFill(['completed_at' => now()->subMonth()->addDays(2)])->save();
        $build = $platform->milestones()->create(['title' => 'التطوير', 'due_date' => now()->addMonth(), 'position' => 1]);
        $launch = $platform->milestones()->create(['title' => 'الإطلاق والتدريب', 'due_date' => now()->addMonths(2), 'position' => 2]);

        $platform->submitForApproval($pm);
        $platform->decide(ProjectDecisionType::Approved, $executive, 'متوافق مع خطة ٢٠٢٦.');
        $platform->start($pm);

        $tasks = [
            ['رحلات المستخدم لذوي الإعاقة البصرية', $analysis, $designer, TaskStatus::Done, -40],
            ['اختبار النماذج الأولية مع ٨ مستفيدين', $analysis, $member, TaskStatus::Done, -35],
            ['واجهة تسجيل الدخول المتوافقة مع قارئ الشاشة', $build, $developer, TaskStatus::Review, 3],
            ['مشغّل الفيديو مع ترجمة لغة الإشارة', $build, $developer, TaskStatus::InProgress, 10],
            ['لوحة متابعة المدربين', $build, $member, TaskStatus::InProgress, -2],
            ['تكامل بوابة الدفع', $build, null, TaskStatus::Blocked, 5],
            ['دليل المستخدم الصوتي', $launch, $designer, TaskStatus::Todo, 40],
            ['خطة تدريب المدربين', $launch, $member, TaskStatus::Todo, 50],
        ];
        foreach ($tasks as $index => [$title, $milestone, $assignee, $status, $dueInDays]) {
            $task = Task::factory()->create([
                'project_id' => $platform->id,
                'milestone_id' => $milestone->id,
                'title' => $title,
                'assignee_id' => $assignee?->id,
                'created_by' => $pm->id,
                'due_date' => now()->addDays($dueInDays),
                'priority' => $index === 4 ? Priority::Critical : Priority::Normal,
            ]);
            if ($status !== TaskStatus::Todo) {
                $task->moveTo($status);
            }
        }
        Task::where('title', 'تكامل بوابة الدفع')->first()->comments()->create([
            'user_id' => $developer->id,
            'body' => 'بانتظار مفاتيح بيئة الاختبار من مزوّد الدفع.',
        ]);

        $pending = Project::factory()->create([
            'name' => 'تطبيق الإرشاد الصوتي للمتاحف',
            'description' => 'تطبيق جوال يقدّم وصفاً صوتياً للمعروضات لذوي الإعاقة البصرية، بالشراكة مع هيئة المتاحف.',
            'pm_id' => $pm->id,
            'created_by' => $pm->id,
            'budget' => 260000,
            'start_date' => now()->addMonth()->startOfMonth(),
            'end_date' => now()->addMonths(6)->endOfMonth(),
        ]);
        $pending->milestones()->createMany([
            ['title' => 'جمع المحتوى الوصفي', 'due_date' => now()->addMonths(2), 'position' => 0],
            ['title' => 'بناء التطبيق', 'due_date' => now()->addMonths(5), 'position' => 1],
        ]);
        $pending->submitForApproval($pm);

        Project::factory()->create([
            'name' => 'بوابة التوظيف الدامج',
            'pm_id' => $pm->id,
            'created_by' => $pm->id,
            'client_id' => $client->id,
            'budget' => null,
        ]);

        $done = Project::factory()->create([
            'name' => 'تدقيق الوصولية لموقع الجمعية',
            'pm_id' => $pm->id,
            'created_by' => $pm->id,
            'client_id' => $client->id,
            'budget' => 45000,
            'start_date' => now()->subMonths(5),
            'end_date' => now()->subMonths(3),
        ]);
        $done->members()->create(['user_id' => $member->id, 'role' => ProjectMemberRole::Member, 'joined_at' => now()->subMonths(5)]);
        $done->submitForApproval($pm);
        $done->decide(ProjectDecisionType::Approved, $executive);
        $done->start($pm);
        Task::factory()->create(['project_id' => $done->id, 'assignee_id' => $member->id, 'created_by' => $pm->id, 'title' => 'تقرير التدقيق وفق WCAG 2.2'])->moveTo(TaskStatus::Done);
        $done->complete($pm);

        $this->seedFinance($platform, $done, $pm, $executive);
        $this->seedCertificates($done, $pm, $executive, $member, $designer);
        $this->seedHiring($platform, $pm, $executive);
    }

    /**
     * طلب في كل مرحلة: بانتظار القرار، ومعتمد قيد التوظيف، ومشغول، ومرفوض.
     */
    private function seedHiring(Project $platform, User $pm, User $executive): void
    {
        $finance = User::where('email', 'finance@wesalinnovation.sa')->firstOrFail();
        $medical = User::where('email', 'medical@wesalinnovation.sa')->firstOrFail();
        $sysadmin = User::where('email', 'sysadmin@wesalinnovation.sa')->firstOrFail();

        $this->hiringRequest($pm, [
            'title' => 'مطور واجهات أمامية',
            'department' => 'الهندسة',
            'headcount' => 2,
            'employment_type' => EmploymentType::FullTime,
            'justification' => "مرحلة التطوير في منصة التأهيل الرقمي متأخرة أسبوعين، والحمل الحالي على مطور واحد.\nبلا تعزيز ينزاح الإطلاق إلى ما بعد موعد العقد.",
            'requirements' => "خبرة ثلاث سنوات في واجهات الويب.\nمعرفة عملية بمعايير الوصولية WCAG واختبارها بقارئات الشاشة.",
            'monthly_budget' => 14000,
            'target_start_date' => today()->addMonth()->startOfMonth(),
            'project_id' => $platform->id,
        ]);

        $this->hiringRequest($medical, [
            'title' => 'مراجِعة محتوى صحي',
            'department' => 'المراجعة الطبية',
            'employment_type' => EmploymentType::PartTime,
            'justification' => 'قائمة المحتوى الصحي المنتظر للمراجعة تتجاوز طاقة مراجِعة واحدة، والمحتوى لا يُنشر قبل اعتماده.',
            'monthly_budget' => 9000,
        ])->approve($executive, 'ضروري قبل توسيع قاعدة المعرفة.');

        $accountant = $this->hiringRequest($finance, [
            'title' => 'محاسب',
            'department' => 'المالية',
            'justification' => 'نمو عدد الفواتير وأوامر الشراء يحتاج محاسباً متفرغاً للذمم والتحصيل.',
            'monthly_budget' => 11000,
        ]);
        $accountant->approve($executive);
        $accountant->markFilled($finance, 'عُيّن سعد الحربي، وباشر أول الشهر.');

        $this->hiringRequest($sysadmin, [
            'title' => 'مهندس عمليات سحابية',
            'department' => 'التقنية',
            'employment_type' => EmploymentType::Contract,
            'justification' => 'نقل الاستضافة إلى بيئة سحابية مُدارة وأتمتة النشر.',
        ])->reject($executive, 'تغطيه الاستضافة المُدارة حالياً؛ يُعاد النظر بعد الإطلاق.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hiringRequest(User $requester, array $attributes): HiringRequest
    {
        $request = new HiringRequest($attributes);
        $request->requested_by = $requester->id;
        $request->save();

        return $request;
    }

    private function seedCertificates(Project $done, User $pm, User $executive, User $member, User $designer): void
    {
        foreach ([[CertificateType::Completion, $done->client_id, 'سُلّم تقرير تدقيق وفق WCAG 2.2 مع خطة معالجة لكل ملاحظة.'], [CertificateType::Participation, $member->id, null]] as [$type, $recipient, $body]) {
            $certificate = new Certificate(['type' => $type, 'project_id' => $done->id, 'recipient_id' => $recipient, 'title' => Certificate::defaultTitle($type, $done), 'body' => $body]);
            $certificate->issued_by = $pm->id;
            $certificate->save();
        }

        $approved = new ReferenceLetter(['purpose' => 'بنك الرياض', 'addressee' => 'بنك الرياض — فرع العليا']);
        $approved->requester_id = $member->id;
        $approved->save();
        $approved->approve($executive);

        $pending = new ReferenceLetter(['purpose' => 'سفارة', 'notes' => 'مطلوبة قبل نهاية الشهر.']);
        $pending->requester_id = $designer->id;
        $pending->save();
    }

    /**
     * عقود وأوامر شراء وفواتير بكل حالاتها عبر نفس مسارات النظام الفعلية.
     */
    private function seedFinance(Project $platform, Project $done, User $pm, User $executive): void
    {
        $finance = User::where('email', 'finance@wesalinnovation.sa')->firstOrFail();

        $contract = new Contract(['project_id' => $platform->id, 'title' => 'عقد تطوير منصة التأهيل الرقمي', 'value' => 420000, 'start_date' => $platform->start_date, 'end_date' => $platform->end_date]);
        $contract->created_by = $pm->id;
        $contract->save();
        $contract->activate($finance, now()->subMonths(2));

        $audit = new Contract(['project_id' => $done->id, 'title' => 'عقد تدقيق الوصولية', 'value' => 45000]);
        $audit->created_by = $finance->id;
        $audit->save();
        $audit->activate($finance, now()->subMonths(5));

        $licenses = $this->purchaseOrder($platform, $pm, 'شركة التقنيات المساعدة', [['تراخيص قارئ الشاشة للمختبر', 10, 1250], ['سماعات عزل', 10, 300]]);
        $licenses->submit($pm);
        $licenses->review(ApprovalDecision::Approved, $finance, 'ضمن بند الأجهزة في الميزانية.');
        $licenses->markReceived($pm);

        $studio = $this->purchaseOrder($platform, $pm, 'استوديو الإشارة المرئية', [['تصوير ٤٠ مقطعاً بلغة الإشارة', 40, 1400]]);
        $studio->submit($pm);
        $studio->review(ApprovalDecision::Approved, $finance, 'يتجاوز الحد؛ للاعتماد التنفيذي.');

        $hosting = $this->purchaseOrder($platform, $pm, 'مزوّد الاستضافة السحابية', [['استضافة سنوية', 1, 18000]]);
        $hosting->submit($pm);

        $this->purchaseOrder($platform, $pm, 'متجر المستلزمات', [['لوحات مفاتيح برايل', 3, 2100]]);

        $paid = $this->invoice($platform, $finance, $contract, [['الدفعة الأولى — التحليل والتصميم', 1, 126000]]);
        $paid->issue($finance);
        $this->backdate($paid, daysAgo: 49);
        $paid->recordPayment($paid->total, today()->subWeeks(3), PaymentMethod::BankTransfer, 'TRX-88231', $finance);

        $overdue = $this->invoice($platform, $finance, $contract, [['الدفعة الثانية — التطوير (٥٠٪)', 1, 84000]]);
        $overdue->issue($finance);
        $this->backdate($overdue, daysAgo: 40);
        $overdue->recordPayment('30000', today()->subDays(4), PaymentMethod::BankTransfer, 'TRX-90112', $finance);

        $this->invoice($platform, $finance, $contract, [['الدفعة الثالثة — الإطلاق', 1, 126000]]);

        $closing = $this->invoice($done, $finance, $audit, [['تقرير تدقيق الوصولية', 1, 45000]]);
        $closing->issue($finance);
        $this->backdate($closing, daysAgo: 100);
        $closing->recordPayment($closing->total, today()->subMonths(2), PaymentMethod::BankTransfer, 'TRX-70045', $finance);
    }

    /**
     * الإصدار يؤرَّخ بيومه؛ البيانات التجريبية تحتاج فواتير صدرت قبل أسابيع كي
     * تظهر أعمار الذمم والرسوم الشهرية كما في الاستخدام الفعلي.
     */
    private function backdate(Invoice $invoice, int $daysAgo): void
    {
        $issued = today()->subDays($daysAgo);

        $invoice->forceFill([
            'issue_date' => $issued,
            'issued_at' => $issued->copy()->setTime(10, 0),
            'due_date' => $issued->copy()->addDays(config('workspace.invoice_payment_terms_days')),
        ])->save();
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $items
     */
    private function purchaseOrder(Project $project, User $by, string $vendor, array $items): PurchaseOrder
    {
        $order = new PurchaseOrder(['project_id' => $project->id, 'vendor_name' => $vendor, 'needed_by' => now()->addWeeks(2)]);
        $order->requested_by = $by->id;
        $order->save();
        $order->syncItems(array_map(fn (array $item): array => ['description' => $item[0], 'quantity' => (string) $item[1], 'unit_price' => (string) $item[2]], $items));

        return $order;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $items
     */
    private function invoice(Project $project, User $by, Contract $contract, array $items): Invoice
    {
        $invoice = new Invoice(['project_id' => $project->id, 'contract_id' => $contract->id]);
        $invoice->created_by = $by->id;
        $invoice->save();
        $invoice->syncItems(array_map(fn (array $item): array => ['description' => $item[0], 'quantity' => (string) $item[1], 'unit_price' => (string) $item[2]], $items));

        return $invoice->refresh();
    }
}
