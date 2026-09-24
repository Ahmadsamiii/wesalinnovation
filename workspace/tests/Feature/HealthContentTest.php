<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\HealthContentStatus;
use App\Models\HealthContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HealthContentTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    private User $medical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysadmin = User::factory()->role('sysadmin')->create();
        $this->medical = User::factory()->role('medical')->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'category' => 'health',
            'title' => 'الوقاية من قرح الفراش',
            'summary' => 'علامات الإنذار المبكر.',
            'body' => 'غيّر وضعية الجلوس كل ٣٠ دقيقة.',
            ...$overrides,
        ];
    }

    public function test_sysadmin_drafts_the_medical_director_approves_and_it_is_published(): void
    {
        $this->actingAs($this->sysadmin)->post(route('content.store'), $this->payload())->assertSessionHasNoErrors();
        $content = HealthContent::sole();
        $this->assertSame(HealthContentStatus::Draft, $content->status);
        $this->get(route('kb.show', $content))->assertNotFound();

        $this->actingAs($this->sysadmin)->post(route('content.submit', $content));
        $this->assertSame(HealthContentStatus::InReview, $content->fresh()->status);
        $this->actingAs($this->medical)->get(route('medical.review'))->assertSee('الوقاية من قرح الفراش');

        $this->actingAs($this->medical)->post(route('medical.review.approve', $content), ['note' => 'دقيق'])->assertRedirect(route('medical.review'));

        $content->refresh();
        $this->assertSame(HealthContentStatus::Approved, $content->status);
        $this->assertSame(1, $content->published_version);
        $this->assertTrue($content->review_due_on->isSameDay(today()->addMonths(HealthContent::REVIEW_VALID_MONTHS)));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ContentApproved->value, 'user_id' => $this->medical->id]);

        auth()->logout();
        $this->get(route('kb.show', $content))->assertOk()->assertSee('الوقاية من قرح الفراش')->assertSee('غيّر وضعية الجلوس');
    }

    public function test_an_edit_stays_unpublished_until_approved_again(): void
    {
        $content = HealthContent::factory()->inReview()->create(['body' => 'النص المعتمد الأول']);
        $content->approve($this->medical);

        $this->actingAs($this->sysadmin)->put(route('content.update', $content), $this->payload(['body' => 'نص معدّل لم يُراجَع']))->assertSessionHasNoErrors();

        $content->refresh();
        $this->assertSame(HealthContentStatus::Draft, $content->status);
        $this->assertTrue($content->hasUnpublishedChanges());
        $this->get(route('kb.show', $content))->assertSee('النص المعتمد الأول')->assertDontSee('نص معدّل لم يُراجَع');

        $this->actingAs($this->sysadmin)->post(route('content.submit', $content));
        $this->actingAs($this->medical)->post(route('medical.review.reject', $content), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->medical)->post(route('medical.review.reject', $content), ['reason' => 'الرقم يحتاج مصدراً']);

        $content->refresh();
        $this->assertSame(HealthContentStatus::Rejected, $content->status);
        $this->assertSame(2, $content->version);
        $this->assertSame(1, $content->published_version);
        $this->assertSame('النص المعتمد الأول', $content->published_body);
        $this->actingAs($this->sysadmin)->get(route('content.show', $content))->assertSee('الرقم يحتاج مصدراً');
    }

    public function test_editing_and_deciding_follow_separation_of_duties(): void
    {
        $content = HealthContent::factory()->inReview()->create();

        $this->actingAs($this->medical)->post(route('content.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->medical)->get(route('content.edit', $content))->assertForbidden();
        $this->actingAs($this->sysadmin)->get(route('content.edit', $content))->assertForbidden();
        $this->actingAs($this->sysadmin)->post(route('medical.review.approve', $content))->assertForbidden();
        $this->actingAs(User::factory()->role('team_member')->create())->get(route('content.index'))->assertForbidden();

        $this->assertSame(HealthContentStatus::InReview, $content->fresh()->status);
    }

    public function test_withdrawal_unpublishes_and_the_export_says_how_to_clean_the_knowledge_base(): void
    {
        $kept = HealthContent::factory()->inReview()->create(['title' => 'محتوى باقٍ', 'summary' => 'ملخص', 'body' => 'نص باقٍ']);
        $kept->approve($this->medical);
        $pulled = HealthContent::factory()->inReview()->create(['title' => 'محتوى مسحوب']);
        $pulled->approve($this->medical);

        $this->actingAs($this->medical)->post(route('content.withdraw', $pulled), ['reason' => 'توصية طبية تغيّرت'])->assertSessionHasNoErrors();
        $this->get(route('kb.show', $pulled))->assertNotFound();

        $directory = storage_path('framework/testing/kb-'.uniqid());

        try {
            $this->artisan('content:export-kb', ['directory' => $directory])
                ->expectsOutputToContain('صُدّر 1 محتوى')
                ->expectsOutputToContain("DELETE FROM kb_chunks WHERE source_url = '".route('kb.show', $pulled)."';")
                ->assertSuccessful();

            $files = File::files($directory);
            $this->assertCount(1, $files);
            $exported = json_decode(File::get($files[0]->getPathname()), true);
            $this->assertSame(['url' => route('kb.show', $kept), 'title' => 'محتوى باقٍ', 'text' => "ملخص\n\nنص باقٍ", 'ok' => true], $exported);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_periodic_review_renews_published_content(): void
    {
        $content = HealthContent::factory()->inReview()->create();
        $content->approve($this->medical);
        $content->forceFill(['review_due_on' => today()->addDays(5)])->save();

        $this->actingAs($this->medical)->get(route('medical.review'))->assertSee('مراجعة دورية مستحقة')->assertSee($content->title);
        $this->actingAs($this->medical)->post(route('medical.review.renew', $content))->assertSessionHasNoErrors();

        $this->assertTrue($content->fresh()->review_due_on->isSameDay(today()->addMonths(HealthContent::REVIEW_VALID_MONTHS)));
        $this->assertSame(2, $content->reviews()->count());
    }

    public function test_only_never_reviewed_drafts_can_be_deleted(): void
    {
        $draft = HealthContent::factory()->create();
        $reviewed = HealthContent::factory()->inReview()->create();
        $reviewed->reject($this->medical, 'يحتاج مراجعة');
        $reviewed->forceFill(['status' => HealthContentStatus::Draft])->save();

        $this->actingAs($this->sysadmin)->delete(route('content.destroy', $reviewed))->assertForbidden();
        $this->actingAs($this->sysadmin)->delete(route('content.destroy', $draft))->assertRedirect(route('content.index'));

        $this->assertModelMissing($draft);
        $this->assertModelExists($reviewed);
    }
}
