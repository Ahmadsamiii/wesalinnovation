<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReferenceLetterStatus;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrReferenceLettersTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_lists_every_request_and_approves_with_the_frozen_facts(): void
    {
        $hr = User::factory()->role('hr')->create(['name' => 'منى الموارد']);
        $employee = User::factory()->role('team_member')->create(['name' => 'ريم الشهري', 'job_title' => 'مصممة', 'department' => 'التصميم', 'joined_at' => '2024-03-01']);
        $colleague = User::factory()->role('pm')->create(['name' => 'سعد المدير']);
        $letter = ReferenceLetter::factory()->create(['requester_id' => $employee->id, 'purpose' => 'بنك الرياض']);
        ReferenceLetter::factory()->create(['requester_id' => $colleague->id, 'purpose' => 'سفارة']);

        $this->actingAs($hr)->get(route('reference-letters.index'))
            ->assertOk()
            ->assertSee('الإفادات الوظيفية')
            ->assertSee('ريم الشهري')
            ->assertSee('سعد المدير')
            ->assertSee('بنك الرياض')
            ->assertSee('سفارة')
            ->assertSee(route('reference-letters.approve', $letter), false)
            ->assertSee('رفض بتعليل');

        $this->actingAs($hr)->post(route('reference-letters.approve', $letter), ['note' => 'مطابقة للسجل'])->assertSessionHasNoErrors();

        $letter->refresh();
        $this->assertSame(ReferenceLetterStatus::Approved, $letter->status);
        $this->assertSame($hr->id, $letter->decided_by);
        $this->assertMatchesRegularExpression('/^REF-\d{4}-0001$/', $letter->number);
        $this->assertSame('ريم الشهري', $letter->holder_name);
        $this->assertSame('مصممة', $letter->job_title);
        $this->assertSame('التصميم', $letter->department);
        $this->assertSame('2024-03-01', $letter->joined_at->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ReferenceLetterApproved->value, 'user_id' => $hr->id, 'subject_id' => $letter->id]);

        // ترقية لاحقة لا تغيّر نص إفادة صدرت، والتوقيع باسم من اعتمدها وصفته.
        $employee->update(['job_title' => 'مديرة التصميم']);
        $this->actingAs($employee)->get(route('reference-letters.show', $letter))
            ->assertOk()
            ->assertSee('مصممة')
            ->assertDontSee('مديرة التصميم')
            ->assertSee('منى الموارد')
            ->assertSee('مدير الموارد البشرية')
            ->assertDontSee('المدير التنفيذي');

        $this->actingAs($hr)->get(route('reference-letters.show', $letter))->assertOk();
    }

    public function test_hr_rejects_with_a_reason_the_employee_sees(): void
    {
        $hr = User::factory()->role('hr')->create();
        $letter = ReferenceLetter::factory()->create();

        $this->actingAs($hr)->post(route('reference-letters.reject', $letter), [])->assertSessionHasErrors('note');
        $this->assertSame(ReferenceLetterStatus::Pending, $letter->fresh()->status);

        $this->actingAs($hr)->post(route('reference-letters.reject', $letter), ['note' => 'أكمل بياناتك الوظيفية أولاً'])->assertSessionHasNoErrors();

        $this->assertSame(ReferenceLetterStatus::Rejected, $letter->fresh()->status);
        $this->assertSame($hr->id, $letter->fresh()->decided_by);
        $this->actingAs($letter->requester)->get(route('reference-letters.index'))->assertSee('أكمل بياناتك الوظيفية أولاً');
    }

    public function test_a_decided_letter_cannot_be_decided_again(): void
    {
        $hr = User::factory()->role('hr')->create();
        $letter = ReferenceLetter::factory()->create();
        $letter->approve($hr);

        $this->actingAs($hr)->post(route('reference-letters.approve', $letter))->assertForbidden();
        $this->actingAs($hr)->post(route('reference-letters.reject', $letter), ['note' => 'متأخر'])->assertForbidden();
    }

    public function test_executive_decides_only_while_there_is_no_active_hr_account(): void
    {
        $executive = User::factory()->role('executive')->create();
        $first = ReferenceLetter::factory()->create();
        $second = ReferenceLetter::factory()->create();

        // لا حساب موارد بشرية: القرار للمدير التنفيذي كما كان.
        $this->assertFalse(User::hasActiveHr());
        $this->actingAs($executive)->get(route('reference-letters.index'))
            ->assertSee('ما ينتظر اعتمادك أولاً')
            ->assertSee(route('reference-letters.approve', $first), false);
        $this->actingAs($executive)->post(route('reference-letters.approve', $first))->assertSessionHasNoErrors();
        $this->assertSame(ReferenceLetterStatus::Approved, $first->fresh()->status);
        $this->assertSame($executive->id, $first->fresh()->decided_by);

        // حساب موارد بشرية موقوف لا يُحسب.
        $suspended = User::factory()->role('hr')->deactivated()->create();
        $this->assertFalse(User::hasActiveHr());
        $this->actingAs($executive)->post(route('reference-letters.reject', $second), ['note' => 'بيانات ناقصة'])->assertSessionHasNoErrors();
        $this->assertSame(ReferenceLetterStatus::Rejected, $second->fresh()->status);

        // حساب دُعي ولم يقبل دعوته لا يستطيع الدخول، فلا يُوقف اعتماد التنفيذي.
        $suspended->forceFill(['deactivated_at' => null, 'invitation_accepted_at' => null])->save();
        $this->assertFalse(User::hasActiveHr());
        $pendingOnly = ReferenceLetter::factory()->create();
        $this->actingAs($executive)->post(route('reference-letters.approve', $pendingOnly))->assertSessionHasNoErrors();
        $this->assertSame(ReferenceLetterStatus::Approved, $pendingOnly->fresh()->status);

        // يظهر أول حساب قادر على الدخول: ينتقل القرار إلى مدير الموارد.
        $suspended->forceFill(['invitation_accepted_at' => now()])->save();
        $this->assertTrue(User::hasActiveHr());

        $third = ReferenceLetter::factory()->create();
        $fourth = ReferenceLetter::factory()->create();

        $this->actingAs($executive)->post(route('reference-letters.approve', $third))->assertForbidden();
        $this->actingAs($executive)->post(route('reference-letters.reject', $fourth), ['note' => 'لا'])->assertForbidden();
        $this->assertSame(ReferenceLetterStatus::Pending, $third->fresh()->status);
        $this->assertSame(ReferenceLetterStatus::Pending, $fourth->fresh()->status);

        $this->actingAs($executive)->get(route('reference-letters.index'))
            ->assertOk()
            ->assertSee('يعتمدها مدير الموارد البشرية')
            ->assertDontSee('ما ينتظر اعتمادك أولاً')
            ->assertDontSee(route('reference-letters.approve', $third), false)
            ->assertDontSee('رفض بتعليل');

        $this->actingAs($suspended)->post(route('reference-letters.approve', $third))->assertSessionHasNoErrors();
        $this->assertSame($suspended->id, $third->fresh()->decided_by);
    }

    public function test_the_executive_keeps_seeing_and_printing_letters_once_hr_decides(): void
    {
        $hr = User::factory()->role('hr')->create();
        $executive = User::factory()->role('executive')->create();
        $letter = ReferenceLetter::factory()->create(['purpose' => 'بنك الرياض']);
        $letter->approve($hr);

        $this->actingAs($executive)->get(route('reference-letters.index'))->assertSee('بنك الرياض')->assertSee($letter->requester->name);
        $this->actingAs($executive)->get(route('reference-letters.show', $letter))->assertOk();
    }

    public function test_the_executive_queue_counts_only_what_the_executive_can_still_decide(): void
    {
        $executive = User::factory()->role('executive')->create();
        ReferenceLetter::factory()->create();

        $count = fn (): int => collect($this->actingAs($executive)->get(route('dashboard'))->viewData('queues'))->firstWhere('label', 'طلبات إفادة')['count'];

        $this->assertSame(1, $count());

        User::factory()->role('hr')->create();
        $this->assertSame(0, $count());
    }

    public function test_nobody_decides_their_own_letter_but_the_executive_covers_the_hr_managers(): void
    {
        $hr = User::factory()->role('hr')->create();
        $executive = User::factory()->role('executive')->create();

        $own = ReferenceLetter::factory()->create(['requester_id' => $hr->id]);
        $this->actingAs($hr)->post(route('reference-letters.approve', $own))->assertForbidden();
        $this->actingAs($hr)->get(route('reference-letters.index'))->assertDontSee(route('reference-letters.approve', $own), false);

        // بلا هذا الاستثناء تبقى إفادة مدير الموارد الوحيد معلّقة إلى الأبد.
        $this->actingAs($executive)->post(route('reference-letters.approve', $own))->assertSessionHasNoErrors();
        $this->assertSame(ReferenceLetterStatus::Approved, $own->fresh()->status);
        $this->assertSame($executive->id, $own->fresh()->decided_by);
        $this->actingAs($hr)->get(route('reference-letters.show', $own))
            ->assertOk()
            ->assertSee('المدير التنفيذي');

        // مديرا موارد: يعتمد كل منهما إفادة الآخر.
        $other = User::factory()->role('hr')->create();
        $theirs = ReferenceLetter::factory()->create(['requester_id' => $other->id]);
        $this->actingAs($hr)->post(route('reference-letters.approve', $theirs))->assertSessionHasNoErrors();
        $this->assertSame($hr->id, $theirs->fresh()->decided_by);
    }

    public function test_hr_requests_their_own_letter_like_any_employee(): void
    {
        $hr = User::factory()->role('hr')->create(['job_title' => 'مديرة الموارد', 'joined_at' => '2023-01-01']);

        $this->actingAs($hr)->get(route('reference-letters.index'))->assertSee(route('reference-letters.create'), false);
        $this->actingAs($hr)->get(route('reference-letters.create'))
            ->assertOk()
            ->assertSee('ويعتمدها المدير التنفيذي');

        $this->actingAs($hr)->post(route('reference-letters.store'), ['purpose' => 'بنك'])
            ->assertRedirect(route('reference-letters.index'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'المدير التنفيذي'));
        $this->assertSame($hr->id, ReferenceLetter::sole()->requester_id);
    }

    public function test_employees_are_told_who_approves_and_who_fixes_their_data(): void
    {
        $employee = User::factory()->role('team_member')->create(['job_title' => null, 'joined_at' => null]);

        $this->actingAs($employee)->get(route('reference-letters.create'))
            ->assertSee('ويعتمدها المدير التنفيذي')
            ->assertSee('يصححها مدير النظام');
        $this->actingAs($employee)->get(route('reference-letters.index'))->assertSee('اطلب من مدير النظام استكمالهما');

        User::factory()->role('hr')->create();

        $this->actingAs($employee)->get(route('reference-letters.create'))
            ->assertSee('ويعتمدها مدير الموارد البشرية')
            ->assertSee('يصححها مدير الموارد البشرية');
        $this->actingAs($employee)->get(route('reference-letters.index'))->assertSee('اطلب من مدير الموارد البشرية استكمالهما');
        $this->actingAs($employee)->post(route('reference-letters.store'), ['purpose' => 'بنك'])
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'مدير الموارد البشرية'));
    }

    public function test_letters_stay_private_from_other_roles_and_hr_does_not_widen_that(): void
    {
        $letter = ReferenceLetter::factory()->create();
        $letter->approve(User::factory()->role('hr')->create());

        foreach (['team_member', 'pm', 'finance', 'medical', 'sysadmin', 'crm'] as $role) {
            $actor = User::factory()->role($role)->create();

            $this->actingAs($actor)->get(route('reference-letters.show', $letter))->assertForbidden();
            $this->actingAs($actor)->get(route('reference-letters.index'))->assertDontSee($letter->number);
        }

        $pending = ReferenceLetter::factory()->create();
        foreach (['team_member', 'pm', 'finance', 'medical', 'sysadmin', 'crm', 'client'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->post(route('reference-letters.approve', $pending))->assertForbidden();
        }
        $this->assertSame(ReferenceLetterStatus::Pending, $pending->fresh()->status);
    }
}
