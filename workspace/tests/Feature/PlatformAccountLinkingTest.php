<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountLinker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * أداة ربط الحسابات (platform:link-accounts): تقرير جاف لا يغيّر شيئاً، وتنفيذ على
 * الخطة التي روجعت وحدها، وتراجع. جداول المنصة هنا بنيتها كما في schema.sql في الجذر.
 */
class PlatformAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    private string $planDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planDir = sys_get_temp_dir().'/wsp-link-'.uniqid();
        $this->connectPlatform();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->planDir);

        parent::tearDown();
    }

    private function connectPlatform(bool $withLinkTable = true): void
    {
        config([
            'workspace.unified_auth' => true,
            'database.connections.platform' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('platform');

        Schema::connection('platform')->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('pref')->default('simple');
            $table->string('pass_hash');
            $table->string('role')->default('user');
            $table->string('status')->default('active');
            $table->string('org_role', 30)->nullable();
            $table->boolean('must_change_pw')->default(false);
            $table->boolean('improve')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->integer('tokens')->default(30);
            $table->dateTime('tokens_at')->nullable();
            $table->integer('questions')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('last_login')->nullable();
        });
        Schema::connection('platform')->create('auth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('auth_at');
            $table->unsignedInteger('seen_at');
            $table->unsignedInteger('idle_sec');
            $table->unsignedInteger('max_sec');
            $table->unsignedInteger('ended_at')->nullable();
            $table->string('ended_why', 16)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('ua', 160)->nullable();
        });

        if ($withLinkTable) {
            Schema::connection('platform')->create('account_links', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('batch', 12);
                $table->unsignedInteger('workspace_user_id')->unique();
                $table->integer('platform_user_id')->unique();
                $table->string('action');
                $table->string('prev_role')->nullable();
                $table->string('prev_org_role')->nullable();
                $table->string('prev_status')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('reverted_at')->nullable();
            });
        }
    }

    /** @param array<string, mixed> $attrs */
    private function platformUser(array $attrs = []): int
    {
        return DB::connection('platform')->table('users')->insertGetId([
            'name' => 'من المنصة', 'email' => 'someone@example.test', 'pass_hash' => 'platform-hash',
            'created_at' => now(), 'tokens_at' => now(), ...$attrs,
        ]);
    }

    private function platformRow(int $id): object
    {
        return DB::connection('platform')->table('users')->where('id', $id)->first();
    }

    /** يشغّل التقرير الجاف ويعيد ملف الخطة. */
    private function dryRun(array $options = []): string
    {
        $this->artisan('platform:link-accounts', ['--plan-dir' => $this->planDir, ...$options])->assertExitCode(0);

        return collect(File::files($this->planDir))->sortBy(fn ($f) => $f->getMTime())->last()->getPathname();
    }

    private function apply(string $plan, array $options = []): int
    {
        return $this->artisan('platform:link-accounts', ['--apply' => true, '--plan' => $plan, '--skip-backup' => true, ...$options])->run();
    }

    /** مدير نظام محلي مربوط أصلاً، ليبقى في الخطة مدير مربوط. */
    private function linkedSysadmin(): User
    {
        $pid = $this->platformUser(['email' => 'root@example.test', 'role' => 'admin', 'org_role' => 'sysadmin']);

        return User::factory()->role('sysadmin')->create(['email' => 'root@example.test', 'platform_user_id' => $pid]);
    }

    // -------------------------------------------------------------- التقرير الجاف

    public function test_the_dry_run_changes_nothing_and_saves_the_plan(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('pm')->create(['email' => 'pm@example.test']);
        $this->platformUser(['email' => 'pm@example.test']);

        $file = $this->dryRun();

        $this->assertNull($local->fresh()->platform_user_id);
        $this->assertNull(DB::connection('platform')->table('users')->where('email', 'pm@example.test')->value('org_role'));
        $this->assertSame(0, DB::connection('platform')->table('account_links')->count());
        $this->assertSame('link', collect(json_decode(file_get_contents($file), true)['items'])->firstWhere('email', 'pm@example.test')['action']);
    }

    public function test_the_report_states_the_counts_and_how_to_apply(): void
    {
        $this->linkedSysadmin();
        User::factory()->role('pm')->create(['email' => 'pm@example.test']);

        $this->artisan('platform:link-accounts', ['--plan-dir' => $this->planDir])
            ->expectsOutputToContain('تقرير الربط (لم يتغير شيء)')
            ->expectsOutputToContain('يُنشأ له حساب في المنصة بكلمة مروره الحالية')
            ->expectsOutputToContain('php artisan platform:link-accounts --apply --plan=')
            ->assertExitCode(0);
    }

    public function test_the_plan_is_deterministic(): void
    {
        $this->linkedSysadmin();
        User::factory()->role('pm')->create(['email' => 'pm@example.test']);

        $this->assertSame(app(AccountLinker::class)->plan()['id'], app(AccountLinker::class)->plan()['id']);
    }

    // -------------------------------------------------------------- الربط والإنشاء

    public function test_an_account_in_both_systems_is_linked_by_email_and_keeps_the_platform_password(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('finance')->create(['email' => 'Fin@Example.test']);
        $pid = $this->platformUser(['email' => 'fin@example.test', 'pass_hash' => 'platform-hash']);

        $this->assertSame(0, $this->apply($this->dryRun()));

        $this->assertSame($pid, $local->fresh()->platform_user_id);
        $row = $this->platformRow($pid);
        $this->assertSame('finance', $row->org_role);
        $this->assertSame('platform-hash', $row->pass_hash);
        $this->assertSame('user', $row->role);
    }

    public function test_an_account_only_here_gets_a_platform_account_with_its_current_password(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('pm')->create(['email' => 'pm@example.test', 'phone' => '+966 50 123 4567']);

        $this->assertSame(0, $this->apply($this->dryRun()));

        $local->refresh();
        $row = $this->platformRow($local->platform_user_id);
        $this->assertSame('pm@example.test', $row->email);
        $this->assertSame($local->getRawOriginal('password'), $row->pass_hash);
        $this->assertSame('pm', $row->org_role);
        $this->assertSame('user', $row->role);
        $this->assertSame('active', $row->status);
        $this->assertSame('0501234567', $row->phone);
    }

    public function test_an_invalid_or_taken_phone_is_dropped_not_guessed(): void
    {
        $this->linkedSysadmin();
        $this->platformUser(['email' => 'other@example.test', 'phone' => '0501234567']);
        $taken = User::factory()->role('pm')->create(['email' => 'a@example.test', 'phone' => '0501234567']);
        $bad = User::factory()->role('pm')->create(['email' => 'b@example.test', 'phone' => '12345']);
        $none = User::factory()->role('pm')->create(['email' => 'c@example.test', 'phone' => null]);

        $this->assertSame(0, $this->apply($this->dryRun()));

        foreach ([$taken, $bad, $none] as $user) {
            $this->assertNull($this->platformRow($user->fresh()->platform_user_id)->phone);
        }
    }

    public function test_two_local_accounts_with_one_phone_do_not_collide(): void
    {
        $this->linkedSysadmin();
        User::factory()->role('pm')->create(['email' => 'a@example.test', 'phone' => '0555555555']);
        User::factory()->role('pm')->create(['email' => 'b@example.test', 'phone' => '0555555555']);

        $this->assertSame(0, $this->apply($this->dryRun()));

        $this->assertSame(1, DB::connection('platform')->table('users')->where('phone', '0555555555')->count());
    }

    public function test_pending_invitations_and_accounts_without_a_role_are_skipped(): void
    {
        $this->linkedSysadmin();
        $pending = User::factory()->role('pm')->create(['email' => 'pending@example.test', 'invitation_accepted_at' => null]);
        $noRole = User::factory()->create(['email' => 'norole@example.test']);

        $this->assertSame(0, $this->apply($this->dryRun()));

        $this->assertNull($pending->fresh()->platform_user_id);
        $this->assertNull($noRole->fresh()->platform_user_id);
        $this->assertFalse(DB::connection('platform')->table('users')->where('email', 'pending@example.test')->exists());
    }

    public function test_a_deactivated_account_gets_no_access_it_never_had(): void
    {
        $this->linkedSysadmin();
        $onlyHere = User::factory()->role('pm')->create(['email' => 'left@example.test', 'deactivated_at' => now()]);
        $both = User::factory()->role('pm')->create(['email' => 'left2@example.test', 'deactivated_at' => now()]);
        $pid = $this->platformUser(['email' => 'left2@example.test']);

        $this->assertSame(0, $this->apply($this->dryRun()));

        $this->assertSame('suspended', $this->platformRow($onlyHere->fresh()->platform_user_id)->status);
        $existing = $this->platformRow($pid);
        $this->assertNull($existing->org_role);
        $this->assertSame('active', $existing->status);
        $this->assertSame($pid, $both->fresh()->platform_user_id);
    }

    // -------------------------------------------------------------- مدير النظام والتعارضات

    public function test_a_sysadmin_is_not_made_a_platform_admin_without_explicit_approval(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create(['email' => 'root@example.test']);
        $pid = $this->platformUser(['email' => 'root@example.test']);

        $file = $this->dryRun();
        $plan = json_decode(file_get_contents($file), true);
        $item = collect($plan['items'])->firstWhere('email', 'root@example.test');

        $this->assertSame('conflict', $item['action']);
        $this->assertSame('needs_admin_promotion', $item['reason']);
        $this->assertSame(['no_linked_sysadmin'], $plan['blockers']);
        $this->assertSame(1, $this->apply($file));
        $this->assertNull($sysadmin->fresh()->platform_user_id);
        $this->assertSame('user', $this->platformRow($pid)->role);
    }

    public function test_approving_the_promotion_makes_the_sysadmin_a_platform_admin(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create(['email' => 'root@example.test']);
        $pid = $this->platformUser(['email' => 'root@example.test']);

        $file = $this->dryRun(['--allow-admin-promotion' => true]);

        $this->assertSame(0, $this->apply($file, ['--allow-admin-promotion' => true]));
        $this->assertSame($pid, $sysadmin->fresh()->platform_user_id);
        $row = $this->platformRow($pid);
        $this->assertSame('admin', $row->role);
        $this->assertSame('sysadmin', $row->org_role);
    }

    public function test_a_new_sysadmin_account_also_needs_the_approval(): void
    {
        User::factory()->role('sysadmin')->create(['email' => 'root@example.test']);

        $plan = app(AccountLinker::class)->plan();
        $this->assertSame('conflict', $plan['items'][0]['action']);

        $plan = app(AccountLinker::class)->plan(allowAdminPromotion: true);
        $this->assertSame('create', $plan['items'][0]['action']);
        $this->assertSame('admin', $plan['items'][0]['changes']['role']);
    }

    public function test_conflicts_are_reported_and_left_alone(): void
    {
        $this->linkedSysadmin();
        $adminPm = User::factory()->role('pm')->create(['email' => 'adminpm@example.test']);
        $adminId = $this->platformUser(['email' => 'adminpm@example.test', 'role' => 'admin']);
        $sus = User::factory()->role('pm')->create(['email' => 'sus@example.test']);
        $susId = $this->platformUser(['email' => 'sus@example.test', 'status' => 'suspended']);

        $file = $this->dryRun();
        $items = collect(json_decode(file_get_contents($file), true)['items']);

        $this->assertSame('platform_admin_with_other_local_role', $items->firstWhere('email', 'adminpm@example.test')['reason']);
        $this->assertSame('platform_account_suspended', $items->firstWhere('email', 'sus@example.test')['reason']);
        $this->assertSame(0, $this->apply($file));
        $this->assertNull($adminPm->fresh()->platform_user_id);
        $this->assertNull($sus->fresh()->platform_user_id);
        $this->assertSame('admin', $this->platformRow($adminId)->role);
        $this->assertSame('suspended', $this->platformRow($susId)->status);
    }

    // -------------------------------------------------------------- سلامة التنفيذ

    public function test_applying_needs_the_reviewed_plan_file(): void
    {
        $this->linkedSysadmin();

        $this->artisan('platform:link-accounts', ['--apply' => true, '--skip-backup' => true])
            ->expectsOutputToContain('ملف الخطة التي روجعت')
            ->assertExitCode(1);
        $this->artisan('platform:link-accounts', ['--apply' => true, '--plan' => '/nope.json', '--skip-backup' => true])->assertExitCode(1);
    }

    public function test_applying_refuses_a_plan_the_data_has_moved_past(): void
    {
        $this->linkedSysadmin();
        $file = $this->dryRun();
        $late = User::factory()->role('pm')->create(['email' => 'late@example.test']);

        $this->artisan('platform:link-accounts', ['--apply' => true, '--plan' => $file, '--skip-backup' => true])
            ->expectsOutputToContain('البيانات تغيّرت منذ التقرير')
            ->assertExitCode(1);

        $this->assertNull($late->fresh()->platform_user_id);
        $this->assertSame(0, DB::connection('platform')->table('account_links')->count());
    }

    public function test_the_approval_flag_at_apply_time_must_match_the_reviewed_plan(): void
    {
        User::factory()->role('sysadmin')->create(['email' => 'root@example.test']);
        $file = $this->dryRun(['--allow-admin-promotion' => true]);

        // موافقة لم تكن في التقرير الذي روجع
        $this->artisan('platform:link-accounts', ['--apply' => true, '--plan' => $file, '--skip-backup' => true])->assertExitCode(1);
    }

    public function test_running_the_apply_twice_does_not_duplicate_or_disturb(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('pm')->create(['email' => 'pm@example.test']);
        $linker = app(AccountLinker::class);
        $plan = $linker->plan();

        $linker->apply($plan);
        $pid = $local->fresh()->platform_user_id;
        $linker->apply($plan);

        $this->assertSame($pid, $local->fresh()->platform_user_id);
        $this->assertSame(1, DB::connection('platform')->table('users')->where('email', 'pm@example.test')->count());
        $this->assertSame(1, DB::connection('platform')->table('account_links')->where('workspace_user_id', $local->id)->count());
    }

    public function test_an_interrupted_run_is_completed_on_the_next_one(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('pm')->create(['email' => 'pm@example.test']);
        $linker = app(AccountLinker::class);
        $plan = $linker->plan();
        $linker->apply($plan);
        $pid = $local->fresh()->platform_user_id;

        // انقطع التنفيذ بعد المنصة وقبل المحلي
        DB::table('users')->where('id', $local->id)->update(['platform_user_id' => null]);
        $linker->apply($plan);

        $this->assertSame($pid, $local->fresh()->platform_user_id);
    }

    public function test_linking_needs_the_platform_database_to_be_prepared(): void
    {
        Schema::connection('platform')->drop('account_links');

        $this->artisan('platform:link-accounts', ['--plan-dir' => $this->planDir])
            ->expectsOutputToContain('لم تُرقَّ بعد')
            ->assertExitCode(1);

        config(['database.connections.platform.database' => null]);
        $this->artisan('platform:link-accounts')->expectsOutputToContain('PLATFORM_DB_*')->assertExitCode(1);
    }

    // -------------------------------------------------------------- التراجع

    public function test_undo_restores_linked_accounts_and_removes_unused_created_ones(): void
    {
        $this->linkedSysadmin();
        $linked = User::factory()->role('pm')->create(['email' => 'both@example.test']);
        $linkedPid = $this->platformUser(['email' => 'both@example.test', 'role' => 'mod', 'status' => 'active']);
        $created = User::factory()->role('pm')->create(['email' => 'new@example.test']);
        $used = User::factory()->role('pm')->create(['email' => 'used@example.test']);

        $linker = app(AccountLinker::class);
        $batch = $linker->apply($linker->plan());
        $usedPid = $used->fresh()->platform_user_id;
        DB::connection('platform')->table('users')->where('id', $usedPid)->update(['last_login' => now()]);

        $result = $linker->undo($batch);

        $this->assertSame(['restored' => 1, 'deleted' => 1, 'suspended' => 1], $result);
        $row = $this->platformRow($linkedPid);
        $this->assertNull($row->org_role);
        $this->assertSame('mod', $row->role);
        $this->assertNull($linked->fresh()->platform_user_id);
        $this->assertNull($created->fresh()->platform_user_id);
        $this->assertFalse(DB::connection('platform')->table('users')->where('email', 'new@example.test')->exists());
        $usedRow = $this->platformRow($usedPid);
        $this->assertSame('suspended', $usedRow->status);
        $this->assertNull($usedRow->org_role);
        $this->assertNotNull(DB::connection('platform')->table('account_links')->where('batch', $batch)->value('reverted_at'));
    }

    public function test_the_undo_command_reports_what_it_did(): void
    {
        $this->linkedSysadmin();
        User::factory()->role('pm')->create(['email' => 'new@example.test']);
        $linker = app(AccountLinker::class);
        $batch = $linker->apply($linker->plan());

        $this->artisan('platform:link-accounts', ['--undo' => $batch])
            ->expectsOutputToContain('أُعيد 0، وحُذف 1، وأُوقف 0')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------- مع الدخول الموحد

    public function test_after_linking_the_platform_session_signs_in_the_same_local_account(): void
    {
        $this->linkedSysadmin();
        $local = User::factory()->role('pm')->create(['email' => 'pm@example.test', 'name' => 'اسمه هنا']);
        $pid = $this->platformUser(['email' => 'pm@example.test', 'name' => 'اسمه في المنصة']);

        $linker = app(AccountLinker::class);
        $linker->apply($linker->plan());

        $token = bin2hex(random_bytes(32));
        DB::connection('platform')->table('auth_sessions')->insert([
            'user_id' => $pid, 'token_hash' => hash('sha256', $token), 'auth_at' => now()->getTimestamp(),
            'seen_at' => now()->getTimestamp(), 'idle_sec' => 900, 'max_sec' => 43200,
        ]);

        $this->withUnencryptedCookie('wesal_auth', $token)->get('/profile')->assertOk();

        $this->assertAuthenticatedAs($local->fresh());
        $this->assertSame(User::where('email', 'pm@example.test')->count(), 1);
        $this->assertSame('pm', $local->fresh()->roleName());
        $this->assertSame('اسمه في المنصة', $local->fresh()->name);
    }
}
