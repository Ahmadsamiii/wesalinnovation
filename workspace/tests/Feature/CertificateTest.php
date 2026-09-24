<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CertificateType;
use App\Enums\ProjectStatus;
use App\Models\Certificate;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->role('pm')->create();
        $this->client = User::factory()->role('client')->create();
        $this->project = Project::factory()->forClient($this->client)->status(ProjectStatus::Completed)->create(['pm_id' => $this->pm->id]);
    }

    public function test_pm_issues_the_completion_certificate_to_the_client(): void
    {
        $this->actingAs($this->pm)->post(route('certificates.store'), [
            'project_id' => $this->project->id,
            'type' => 'completion',
        ])->assertSessionHasNoErrors();

        $certificate = Certificate::sole();
        $this->assertSame($this->client->id, $certificate->recipient_id);
        $this->assertMatchesRegularExpression('/^CERT-\d{4}-0001$/', $certificate->number);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{12}$/', $certificate->verification_code);
        $this->assertSame(Certificate::defaultTitle(CertificateType::Completion, $this->project), $certificate->title);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CertificateIssued->value, 'subject_id' => $certificate->id]);
    }

    public function test_participation_certificates_go_to_team_members_only(): void
    {
        $member = User::factory()->role('team_member')->create();
        $outsider = User::factory()->role('team_member')->create();
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $member->id]);

        $this->actingAs($this->pm)->post(route('certificates.store'), [
            'project_id' => $this->project->id,
            'type' => 'participation',
            'recipients' => [$member->id, $outsider->id],
        ]);

        $this->assertSame([$member->id], Certificate::pluck('recipient_id')->all());
    }

    public function test_certificates_are_only_for_completed_projects_the_pm_manages(): void
    {
        $running = Project::factory()->forClient()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
        $foreign = Project::factory()->forClient()->status(ProjectStatus::Completed)->create();

        foreach ([$running, $foreign] as $project) {
            $this->actingAs($this->pm)
                ->post(route('certificates.store'), ['project_id' => $project->id, 'type' => 'completion'])
                ->assertSessionHasErrors('project_id');
        }
    }

    public function test_no_duplicate_valid_certificate(): void
    {
        Certificate::factory()->create(['project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'issued_by' => $this->pm->id]);

        $this->actingAs($this->pm)
            ->post(route('certificates.store'), ['project_id' => $this->project->id, 'type' => 'completion'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_recipient_sees_and_prints_their_certificate_others_do_not(): void
    {
        $certificate = Certificate::factory()->create(['project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'issued_by' => $this->pm->id]);

        $this->actingAs($this->client)->get(route('certificates.index'))->assertOk()->assertSee($certificate->number);
        $this->actingAs($this->client)->get(route('certificates.show', $certificate))
            ->assertOk()
            ->assertSee($this->client->name)
            ->assertSee(Certificate::formatVerificationCode($certificate->verification_code));

        $this->actingAs(User::factory()->role('client')->create())->get(route('certificates.show', $certificate))->assertForbidden();
    }

    public function test_pm_is_reminded_of_completed_projects_without_a_certificate(): void
    {
        $this->actingAs($this->pm)->get(route('certificates.index'))
            ->assertOk()
            ->assertSee('مشاريع منجزة بلا شهادة إنجاز')
            ->assertSee($this->project->name);
    }

    public function test_only_the_executive_revokes_and_the_reason_is_kept(): void
    {
        $certificate = Certificate::factory()->create(['project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'issued_by' => $this->pm->id]);
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($this->pm)->post(route('certificates.revoke', $certificate), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($executive)->post(route('certificates.revoke', $certificate), ['reason' => 'صدرت باسم خاطئ']);

        $this->assertTrue($certificate->fresh()->isRevoked());
        $this->assertSame('صدرت باسم خاطئ', $certificate->fresh()->revocation_reason);
    }
}
