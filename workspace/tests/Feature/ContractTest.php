<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $finance;

    private User $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->role('pm')->create();
        $this->finance = User::factory()->role('finance')->create();
        $this->client = User::factory()->role('client')->create();
        $this->project = Project::factory()->forClient($this->client)->status(ProjectStatus::Approved)->create(['pm_id' => $this->pm->id]);
    }

    public function test_pm_drafts_a_contract_with_the_projects_client(): void
    {
        $this->actingAs($this->pm)->post(route('contracts.store'), [
            'project_id' => $this->project->id,
            'title' => 'عقد تطوير المنصة',
            'value' => '300000',
            'start_date' => '2026-10-01',
            'end_date' => '2027-03-31',
        ])->assertSessionHasNoErrors();

        $contract = Contract::sole();
        $this->assertMatchesRegularExpression('/^CT-\d{4}-0001$/', $contract->number);
        $this->assertSame($this->client->id, $contract->client_id);
        $this->assertSame(ContractStatus::Draft, $contract->status);
    }

    public function test_client_does_not_see_drafts_but_sees_signed_contracts(): void
    {
        $contract = Contract::factory()->create(['project_id' => $this->project->id, 'title' => 'عقد العميل']);

        $this->actingAs($this->client)->get(route('contracts.index'))->assertDontSee('عقد العميل');
        $this->actingAs($this->client)->get(route('contracts.show', $contract))->assertForbidden();

        $this->actingAs($this->finance)->post(route('contracts.activate', $contract), ['signed_on' => today()->toDateString()]);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);

        $this->actingAs($this->client)->get(route('contracts.index'))->assertSee('عقد العميل');
        $this->actingAs($this->client)->get(route('contracts.show', $contract))->assertOk();
    }

    public function test_only_finance_or_executive_activates(): void
    {
        $contract = Contract::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)->post(route('contracts.activate', $contract), ['signed_on' => today()->toDateString()])->assertForbidden();
    }

    public function test_active_contract_is_locked_and_termination_needs_a_reason(): void
    {
        $contract = Contract::factory()->active()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)->get(route('contracts.edit', $contract))->assertForbidden();

        $this->actingAs($this->finance)->post(route('contracts.close', $contract), ['status' => 'terminated'])->assertSessionHasErrors('reason');
        $this->actingAs($this->finance)->post(route('contracts.close', $contract), ['status' => 'terminated', 'reason' => 'إخلال بالسداد']);

        $this->assertSame(ContractStatus::Terminated, $contract->fresh()->status);
    }

    public function test_client_downloads_the_signed_copy_of_their_contract(): void
    {
        Storage::fake(Attachment::DISK);
        $contract = Contract::factory()->active()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->finance)->post(route('contracts.files.store', $contract), [
            'file' => UploadedFile::fake()->create('signed.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('status');

        $attachment = Attachment::sole();
        $this->actingAs($this->client)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs(User::factory()->role('client')->create())->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_project_with_a_contract_cannot_be_deleted(): void
    {
        $draft = Project::factory()->create(['pm_id' => $this->pm->id]);
        Contract::factory()->create(['project_id' => $draft->id]);

        $this->actingAs($this->pm)->delete(route('projects.destroy', $draft))->assertForbidden();
    }
}
