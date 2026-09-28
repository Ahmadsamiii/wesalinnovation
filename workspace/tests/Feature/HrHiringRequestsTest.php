<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\HiringRequestStatus;
use App\Enums\ProjectStatus;
use App\Models\HiringRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrHiringRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->role('hr')->create();
    }

    public function test_hr_sees_every_request_with_its_requester_and_the_approved_ones_first(): void
    {
        $pm = User::factory()->role('pm')->create(['name' => 'سعد المدير']);
        $finance = User::factory()->role('finance')->create(['name' => 'نورة المالية']);
        HiringRequest::factory()->create(['requested_by' => $pm->id, 'title' => 'طلب ينتظر القرار']);
        HiringRequest::factory()->approved()->create(['requested_by' => $finance->id, 'title' => 'طلب معتمد']);

        $this->actingAs($this->hr)->get(route('hiring-requests.index'))
            ->assertOk()
            ->assertSee('احتياجات الفرق من الوظائف')
            ->assertSeeInOrder(['طلب معتمد', 'نورة المالية', 'طلب ينتظر القرار', 'سعد المدير'])
            ->assertSee(route('hiring-requests.show', HiringRequest::firstWhere('title', 'طلب معتمد')), false)
            ->assertDontSee('طلب توظيف جديد')
            ->assertDontSee('مراجعة وقرار');
    }

    public function test_hr_opens_a_request_and_a_project_link_stays_plain_text(): void
    {
        $project = Project::factory()->status(ProjectStatus::InProgress)->create(['name' => 'منصة النور']);
        $hiring = HiringRequest::factory()->approved()->create(['project_id' => $project->id, 'title' => 'مطور واجهات']);

        $this->actingAs($this->hr)->get(route('hiring-requests.show', $hiring))
            ->assertOk()
            ->assertSee('مطور واجهات')
            ->assertSee('منصة النور')
            ->assertDontSee(route('projects.show', $project), false)
            ->assertSee('شُغلت الوظيفة؟')
            ->assertDontSee('قرارك');
    }

    public function test_hr_fills_an_approved_request(): void
    {
        $hiring = HiringRequest::factory()->approved()->create();

        $this->actingAs($this->hr)->post(route('hiring-requests.fill', $hiring), [])->assertSessionHasErrors('note');
        $this->actingAs($this->hr)->post(route('hiring-requests.fill', $hiring), ['note' => 'عُيّنت نورة وتباشر ١ أكتوبر'])->assertSessionHasNoErrors();

        $hiring->refresh();
        $this->assertSame(HiringRequestStatus::Filled, $hiring->status);
        $this->assertSame($this->hr->id, $hiring->closed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::HiringFilled->value, 'user_id' => $this->hr->id, 'subject_id' => $hiring->id]);
    }

    public function test_hr_cancels_an_approved_request_with_a_reason(): void
    {
        $hiring = HiringRequest::factory()->approved()->create();

        $this->actingAs($this->hr)->post(route('hiring-requests.cancel', $hiring), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->hr)->post(route('hiring-requests.cancel', $hiring), ['reason' => 'أُلغيت الحاجة للوظيفة'])->assertSessionHasNoErrors();

        $hiring->refresh();
        $this->assertSame(HiringRequestStatus::Cancelled, $hiring->status);
        $this->assertSame($this->hr->id, $hiring->closed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::HiringCancelled->value, 'user_id' => $this->hr->id, 'subject_id' => $hiring->id]);
    }

    public function test_hr_cannot_decide_create_edit_or_close_what_is_not_approved(): void
    {
        $pending = HiringRequest::factory()->create();

        $this->actingAs($this->hr)->post(route('hiring-requests.approve', $pending))->assertForbidden();
        $this->actingAs($this->hr)->post(route('hiring-requests.reject', $pending), ['reason' => 'لا'])->assertForbidden();
        $this->actingAs($this->hr)->post(route('hiring-requests.fill', $pending), ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->hr)->post(route('hiring-requests.cancel', $pending), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->hr)->get(route('hiring-requests.edit', $pending))->assertForbidden();
        $this->actingAs($this->hr)->put(route('hiring-requests.update', $pending), ['title' => 'عنوان آخر'])->assertForbidden();
        $this->assertSame(HiringRequestStatus::Pending, $pending->fresh()->status);

        $this->actingAs($this->hr)->get(route('hiring-requests.create'))->assertForbidden();
        $this->actingAs($this->hr)->post(route('hiring-requests.store'), [
            'title' => 'مطور', 'headcount' => 1, 'employment_type' => 'full_time', 'justification' => 'سبب',
        ])->assertForbidden();
        $this->assertSame(1, HiringRequest::count());
    }

    public function test_hr_cannot_reopen_a_closed_request(): void
    {
        $filled = HiringRequest::factory()->approved()->create();
        $filled->markFilled($this->hr, 'عُيّن فلان');
        $rejected = HiringRequest::factory()->create();
        $rejected->reject(User::factory()->role('executive')->create(), 'لا');

        foreach ([$filled, $rejected] as $closed) {
            $this->actingAs($this->hr)->post(route('hiring-requests.cancel', $closed), ['reason' => 'x'])->assertForbidden();
            $this->actingAs($this->hr)->post(route('hiring-requests.fill', $closed), ['note' => 'x'])->assertForbidden();
        }
    }

    public function test_the_executive_still_decides_and_the_requester_still_sees_the_outcome(): void
    {
        $executive = User::factory()->role('executive')->create();
        $hiring = HiringRequest::factory()->create();

        $this->actingAs($executive)->post(route('hiring-requests.approve', $hiring))->assertSessionHasNoErrors();
        $this->assertSame(HiringRequestStatus::Approved, $hiring->fresh()->status);

        $this->actingAs($this->hr)->get(route('hiring-requests.show', $hiring))->assertOk()->assertSee('اعتمده '.$executive->name);
        $this->actingAs($hiring->requester)->post(route('hiring-requests.fill', $hiring), ['note' => 'عُيّن فلان'])->assertSessionHasNoErrors();
    }
}
