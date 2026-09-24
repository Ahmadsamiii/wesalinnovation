<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Attachment::DISK);
        $this->pm = User::factory()->role('pm')->create();
        $this->project = Project::factory()->forClient()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
    }

    public function test_upload_is_stored_privately_under_a_server_generated_name(): void
    {
        $this->actingAs($this->pm)->post(route('projects.files.store', $this->project), [
            'file' => UploadedFile::fake()->create('../../عرض السعر.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('status');

        $attachment = Attachment::sole();
        Storage::disk(Attachment::DISK)->assertExists($attachment->path);
        $this->assertMatchesRegularExpression('#^attachments/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#', $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
        $this->assertSame($this->project->id, $attachment->attachable_id);
    }

    public function test_executable_or_page_files_are_refused(): void
    {
        foreach (['shell.php', 'page.html', 'image.svg'] as $name) {
            $this->actingAs($this->pm)
                ->post(route('projects.files.store', $this->project), ['file' => UploadedFile::fake()->create($name, 5)])
                ->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_team_can_download_but_client_and_outsiders_cannot(): void
    {
        $member = User::factory()->role('team_member')->create();
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $member->id]);
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($member)->post(route('tasks.files.store', $task), [
            'file' => UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHas('status');
        $attachment = Attachment::sole();

        $this->actingAs($this->pm)->get(route('attachments.show', $attachment))->assertOk()->assertDownload('notes.docx');
        $this->actingAs($this->project->client)->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs(User::factory()->role('pm')->create())->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_uploader_deletes_their_file_and_it_leaves_the_disk(): void
    {
        $this->actingAs($this->pm)->post(route('projects.files.store', $this->project), [
            'file' => UploadedFile::fake()->create('plan.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $attachment = Attachment::sole();

        $this->actingAs($this->pm)->delete(route('attachments.destroy', $attachment))->assertSessionHas('status');

        $this->assertModelMissing($attachment);
        Storage::disk(Attachment::DISK)->assertMissing($attachment->path);
    }

    public function test_files_page_lists_project_and_task_files_together(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'مهمة التصميم']);
        Attachment::factory()->create(['attachable_type' => 'project', 'attachable_id' => $this->project->id, 'original_name' => 'العقد.pdf', 'uploaded_by' => $this->pm->id]);
        Attachment::factory()->create(['attachable_type' => 'task', 'attachable_id' => $task->id, 'original_name' => 'المسودة.pdf', 'uploaded_by' => $this->pm->id]);

        $this->actingAs($this->pm)->get(route('projects.files.index', $this->project))
            ->assertOk()
            ->assertSee('العقد.pdf')
            ->assertSee('المسودة.pdf')
            ->assertSee('مهمة التصميم');
    }
}
