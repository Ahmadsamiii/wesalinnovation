<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class SystemPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysadmin = User::factory()->role('sysadmin')->create();
    }

    /**
     * قاعدة منصة وهمية في الذاكرة بجدول chat_logs نفسه.
     */
    private function connectPlatform(): void
    {
        config(['database.connections.platform' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        Schema::connection('platform')->create('chat_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('mode')->default('simple');
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->boolean('stream')->default(false);
            $table->unsignedInteger('ttfb_ms')->nullable();
            $table->unsignedInteger('total_ms')->nullable();
            $table->boolean('aborted')->default(false);
            $table->dateTime('created_at');
        });
    }

    public function test_system_pages_are_for_the_sysadmin_only(): void
    {
        $executive = User::factory()->role('executive')->create();

        foreach (['system.health', 'system.mail', 'system.deployment', 'system.ai', 'reports.technical'] as $route) {
            $this->actingAs($this->sysadmin)->get(route($route))->assertOk();
            $this->actingAs($executive)->get(route($route))->assertForbidden();
        }
    }

    public function test_health_page_flags_mail_that_is_never_delivered(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->sysadmin)->get(route('system.health'))
            ->assertOk()
            ->assertViewHas('checks', function (array $checks): bool {
                $byLabel = collect($checks)->keyBy('label');

                return $byLabel['قاعدة البيانات']['status'] === 'ok'
                    && $byLabel['ترحيلات قاعدة البيانات']['status'] === 'ok'
                    && $byLabel['البريد']['status'] === 'warn'
                    && $byLabel['قاعدة المنصة العامة']['status'] === 'off';
            });
    }

    public function test_test_email_is_sent_and_logged_and_failures_are_explained(): void
    {
        $this->actingAs($this->sysadmin)->post(route('system.mail.test'), ['to' => 'ops@example.org'])->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::MailTestSent->value, 'user_id' => $this->sysadmin->id]);
        $this->assertTrue(AuditLog::sole()->properties['delivered']);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->actingAs($this->sysadmin)->post(route('system.mail.test'), ['to' => 'ops@example.org'])->assertSessionHas('error');
        $this->assertFalse(AuditLog::latest('id')->first()->properties['delivered']);
    }

    public function test_ai_page_explains_how_to_connect_when_the_platform_is_not_linked(): void
    {
        config(['database.connections.platform.database' => null]);

        $this->actingAs($this->sysadmin)->get(route('system.ai'))
            ->assertOk()
            ->assertSee('قاعدة المنصة العامة غير مربوطة')
            ->assertSee('PLATFORM_DB_DATABASE');
    }

    public function test_ai_page_summarises_provider_usage_from_the_platform_chat_log(): void
    {
        $this->connectPlatform();
        $rows = [
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'total_ms' => 1200, 'answer' => 'جواب'],
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'total_ms' => 3000, 'answer' => 'جواب'],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'total_ms' => 2000, 'answer' => null],
        ];
        foreach ($rows as $row) {
            DB::connection('platform')->table('chat_logs')->insert([...$row, 'question' => 'سؤال', 'created_at' => now()->subDay()]);
        }

        $this->actingAs($this->sysadmin)->get(route('system.ai'))
            ->assertOk()
            ->assertViewHas('total', 3)
            ->assertViewHas('unanswered', 1)
            ->assertViewHas('providers', fn (array $providers): bool => $providers[0]['provider'] === 'gemini'
                && $providers[0]['share'] === 67
                && $providers[0]['median'] === 1200
                && $providers[0]['p90'] === 3000)
            ->assertSee('gpt-4o-mini');
    }

    public function test_platform_chat_log_is_read_only(): void
    {
        $this->connectPlatform();

        $this->expectException(LogicException::class);

        (new ChatLog)->forceFill(['question' => 'x', 'created_at' => now()])->save();
    }

    public function test_technical_report_counts_logins_and_lists_dormant_accounts(): void
    {
        $regular = User::factory()->role('pm')->create();
        $idle = User::factory()->role('finance')->create(['name' => 'حساب خامل']);

        AuditLog::record(AuditAction::AuthLogin, $regular, actor: $regular);
        AuditLog::record(AuditAction::AuthFailed, properties: ['email' => 'x@example.org']);
        AuditLog::record(AuditAction::AuthFailed, properties: ['email' => 'x@example.org']);

        $this->actingAs($this->sysadmin)->get(route('reports.technical'))
            ->assertOk()
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['activeLast30'] === 1 && $kpis['failedLogins'] === 2)
            ->assertViewHas('dormant', fn ($dormant): bool => $dormant->pluck('user.id')->contains($idle->id)
                && ! $dormant->pluck('user.id')->contains($regular->id))
            ->assertSee('حساب خامل');
    }

    public function test_deployment_page_reads_the_release_written_by_the_deploy_script(): void
    {
        $file = storage_path('app/release.json');
        file_put_contents($file, json_encode(['commit' => 'abc1234def5678', 'branch' => 'main', 'deployed_at' => '2026-09-20T10:00:00+03:00']));

        try {
            $this->actingAs($this->sysadmin)->get(route('system.deployment'))
                ->assertOk()
                ->assertSee('abc1234def56')
                ->assertSee('قائمة جاهزية الإنتاج');
        } finally {
            @unlink($file);
        }
    }
}
