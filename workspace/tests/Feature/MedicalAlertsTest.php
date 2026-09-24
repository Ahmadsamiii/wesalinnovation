<?php

namespace Tests\Feature;

use App\Enums\AlertOutcome;
use App\Models\QuestionAlertReview;
use App\Models\SensitiveTerm;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicalAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $medical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->medical = User::factory()->role('medical')->create();
    }

    /**
     * @param  list<array<string, mixed>>  $logs
     */
    private function connectPlatform(array $logs): void
    {
        config(['database.connections.platform' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        Schema::connection('platform')->create('chat_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('provider')->nullable();
            $table->dateTime('created_at');
        });

        foreach ($logs as $log) {
            DB::connection('platform')->table('chat_logs')->insert([...$log, 'created_at' => $log['created_at'] ?? now()->subHour()]);
        }
    }

    public function test_page_explains_the_link_when_the_platform_is_not_connected(): void
    {
        config(['database.connections.platform.database' => null]);

        $this->actingAs($this->medical)->get(route('medical.alerts'))
            ->assertOk()
            ->assertSee('قاعدة المنصة العامة غير مربوطة')
            ->assertSee('انتحار');
    }

    public function test_sensitive_questions_are_listed_until_reviewed(): void
    {
        $this->connectPlatform([
            ['id' => 1, 'question' => 'ما الجرعة الآمنة من دواء الصرع لطفل؟', 'answer' => 'راجع الطبيب', 'provider' => 'gemini'],
            ['id' => 2, 'question' => 'كيف أحجز موعداً في مركز التأهيل؟', 'answer' => 'من التطبيق'],
            ['id' => 3, 'question' => 'أشعر بألم في الصدر منذ ساعة', 'answer' => null],
            ['id' => 4, 'question' => 'سؤال قديم عن جرعة', 'created_at' => now()->subDays(40)],
        ]);

        $this->actingAs($this->medical)->get(route('medical.alerts'))
            ->assertOk()
            ->assertViewHas('pendingCount', 2)
            ->assertSee('ما الجرعة الآمنة')
            ->assertSee('بألم في الصدر')
            ->assertDontSee('كيف أحجز موعداً')
            ->assertDontSee('سؤال قديم');

        $this->actingAs($this->medical)->post(route('medical.alerts.review', 3), ['outcome' => 'escalated'])->assertSessionHasErrors('note');
        $this->actingAs($this->medical)->post(route('medical.alerts.review', 3), ['outcome' => 'escalated', 'note' => 'أبلغت مدير النظام لإضافة توجيه للإسعاف'])->assertSessionHasNoErrors();

        $this->assertSame(AlertOutcome::Escalated, QuestionAlertReview::sole()->outcome);
        $this->actingAs($this->medical)->get(route('medical.alerts'))->assertViewHas('pendingCount', 1)->assertDontSee('بألم في الصدر');
        $this->actingAs($this->medical)->get(route('medical.alerts', ['show' => 'all']))->assertSee('بألم في الصدر')->assertSee('صُعِّد لمتابعة عاجلة');

        $this->actingAs($this->medical)->post(route('medical.alerts.review', 999), ['outcome' => 'safe'])->assertNotFound();
    }

    public function test_the_medical_director_maintains_the_alert_terms(): void
    {
        $this->actingAs($this->medical)->post(route('medical.terms.store'), ['term' => 'صرع'])->assertSessionHasNoErrors();
        $this->actingAs($this->medical)->post(route('medical.terms.store'), ['term' => 'صرع'])->assertSessionHasErrors('term');

        $term = SensitiveTerm::where('term', 'صرع')->sole();
        $this->actingAs($this->medical)->delete(route('medical.terms.destroy', $term));

        $this->assertModelMissing($term);
        $this->actingAs(User::factory()->role('sysadmin')->create())->post(route('medical.terms.store'), ['term' => 'x1'])->assertForbidden();
    }

    public function test_medical_report_summarises_decisions(): void
    {
        $this->actingAs($this->medical)->get(route('reports.medical'))
            ->assertOk()
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['inReview'] === 0 && $kpis['published'] === 0);
    }
}
