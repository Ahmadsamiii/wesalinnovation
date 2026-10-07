<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * الدخول الموحد مع المنصة: الصف في auth_sessions هو المرجع، ومساحة العمل تُدخل
 * صاحبه وتزامن دوره وتُخرجه حين ينتهي. جدولا المنصة هنا بنيتهما كما في
 * schema.sql في جذر المستودع (وعقدهما في App\Support\PlatformSession).
 */
class UnifiedSessionTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'wesal_auth';

    private function connectPlatform(): void
    {
        config([
            'workspace.unified_auth' => true,
            'database.connections.platform' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('platform');

        Schema::connection('platform')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role')->default('user');
            $table->string('status')->default('active');
            $table->string('org_role')->nullable();
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
    }

    /** @param array<string, mixed> $attrs */
    private function platformUser(array $attrs = []): int
    {
        return DB::connection('platform')->table('users')->insertGetId([
            'name' => 'أحمد المنصة', 'email' => 'ahmad@example.test', 'phone' => '0500000001',
            'role' => 'user', 'status' => 'active', 'org_role' => 'finance', ...$attrs,
        ]);
    }

    /**
     * يفتح جلسة في المنصة ويعيد رمزها (ما يضعه الكوكي).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function platformSession(int $userId, array $attrs = []): string
    {
        $token = bin2hex(random_bytes(32));
        DB::connection('platform')->table('auth_sessions')->insert([
            'user_id' => $userId, 'token_hash' => hash('sha256', $token),
            'auth_at' => now()->getTimestamp(), 'seen_at' => now()->getTimestamp(),
            'idle_sec' => 900, 'max_sec' => 43200, ...$attrs,
        ]);

        return $token;
    }

    /** @return object{token: string, uid: int} */
    private function signedIn(array $user = [], array $session = []): object
    {
        $this->connectPlatform();
        $uid = $this->platformUser($user);

        return (object) ['uid' => $uid, 'token' => $this->platformSession($uid, $session)];
    }

    private function sessionRow(string $token): object
    {
        return DB::connection('platform')->table('auth_sessions')->where('token_hash', hash('sha256', $token))->first();
    }

    // -------------------------------------------------------------- مطفأ

    public function test_the_cookie_is_ignored_and_local_sign_in_works_when_the_switch_is_off(): void
    {
        config(['workspace.unified_auth' => false]);

        $this->withUnencryptedCookie(self::COOKIE, str_repeat('a', 64))->get('/profile')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();

        $user = User::factory()->role('pm')->create();
        // الوجهة التي طلبها الزائر قبل الدخول (الملف الشخصي) هي التي يعود إليها
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(url('/profile'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_switch_needs_the_platform_database_to_be_configured(): void
    {
        config(['workspace.unified_auth' => true, 'database.connections.platform.database' => null]);

        $this->get(route('login'))->assertOk();
    }

    // -------------------------------------------------------------- الدخول

    public function test_a_live_platform_session_signs_the_workspace_user_in(): void
    {
        $s = $this->signedIn();

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $user = User::where('platform_user_id', $s->uid)->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('finance', $user->roleName());
        $this->assertSame('أحمد المنصة', $user->name);
        $this->assertNotNull($user->invitation_accepted_at);
    }

    public function test_platform_admin_is_sysadmin_here_whatever_the_org_role(): void
    {
        $s = $this->signedIn(['role' => 'admin', 'org_role' => null]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $this->assertSame('sysadmin', User::where('platform_user_id', $s->uid)->first()->roleName());
    }

    public function test_sign_in_is_recorded_once_not_on_every_request(): void
    {
        $s = $this->signedIn();

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();
        $this->get('/profile')->assertOk();
        $this->get('/profile')->assertOk();

        $this->assertSame(1, DB::table('audit_logs')->where('action', AuditAction::AuthLogin->value)->count());
        $this->assertNotNull(User::where('platform_user_id', $s->uid)->first()->last_login_at);
    }

    public function test_a_linked_account_keeps_its_local_id_and_follows_the_platform(): void
    {
        $s = $this->signedIn(['name' => 'اسم جديد', 'phone' => '0555555555', 'org_role' => 'executive']);
        $local = User::factory()->role('pm')->create(['email' => 'ahmad@example.test', 'name' => 'اسم قديم', 'platform_user_id' => $s->uid]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $local->refresh();
        $this->assertAuthenticatedAs($local);
        $this->assertSame(1, User::count());
        $this->assertSame('اسم جديد', $local->name);
        $this->assertSame('0555555555', $local->phone);
        $this->assertSame('executive', $local->roleName());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserRoleChanged->value,
            'subject_id' => $local->id,
            'properties->from' => 'pm',
            'properties->to' => 'executive',
        ]);
    }

    public function test_a_local_account_with_the_same_email_is_never_linked_by_email_alone(): void
    {
        $s = $this->signedIn();
        $local = User::factory()->role('pm')->create(['email' => 'ahmad@example.test']);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')
            ->assertForbidden()->assertSee('لم يُربط بحسابك في وصال بعد');

        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertNull($local->fresh()->platform_user_id);
    }

    public function test_a_platform_account_without_a_workspace_role_is_told_so(): void
    {
        $s = $this->signedIn(['org_role' => null]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')
            ->assertForbidden()->assertSee('ليس له دور في مساحة العمل');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_unknown_role_counts_as_no_role(): void
    {
        $s = $this->signedIn(['org_role' => 'astronaut']);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertForbidden();
        $this->assertSame(0, User::count());
    }

    public function test_public_pages_stay_open_for_a_denied_visitor(): void
    {
        $s = $this->signedIn(['org_role' => null]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/verify')->assertOk();
    }

    public function test_a_suspended_platform_account_is_denied_and_the_session_is_ended(): void
    {
        $s = $this->signedIn(['status' => 'suspended']);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertForbidden()->assertSee('حسابك موقوف');

        $this->assertGuest();
        $this->assertSame('suspended', $this->sessionRow($s->token)->ended_why);
    }

    public function test_a_deactivated_local_account_is_denied(): void
    {
        $s = $this->signedIn();
        User::factory()->role('finance')->create(['email' => 'ahmad@example.test', 'platform_user_id' => $s->uid, 'deactivated_at' => now()]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertForbidden();
        $this->assertGuest();
    }

    // -------------------------------------------------------------- الانتهاء

    public function test_a_session_ended_elsewhere_ends_the_workspace_session(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        DB::connection('platform')->table('auth_sessions')->update(['ended_at' => now()->getTimestamp(), 'ended_why' => 'logout']);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_local_session_ends_when_the_cookie_is_gone(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->connectPlatform();

        $this->actingAs($user)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_idle_ends_the_shared_session_and_is_audited(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $this->travel(18)->minutes();

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('idle', $this->sessionRow($s->token)->ended_why);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::AuthTimedOut->value, 'properties->reason' => 'idle']);
    }

    public function test_the_maximum_session_length_ends_it_whatever_the_activity(): void
    {
        $s = $this->signedIn([], ['auth_at' => now()->subHours(13)->getTimestamp(), 'max_sec' => 43200]);

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertRedirect(route('login'));
        $this->assertSame('max', $this->sessionRow($s->token)->ended_why);
    }

    public function test_activity_here_renews_the_shared_session(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $this->travel(10)->minutes();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->post(route('session.heartbeat'))->assertNoContent();

        $this->assertSame(now()->getTimestamp(), (int) $this->sessionRow($s->token)->seen_at);

        $this->travel(10)->minutes();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();
    }

    public function test_the_shared_clock_replaces_the_local_one(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        // النشاط في المنصة يبقي الجلسة هنا وإن لم يتحرك المستخدم في مساحة العمل
        $this->travel(10)->minutes();
        DB::connection('platform')->table('auth_sessions')->update(['seen_at' => now()->getTimestamp()]);
        $this->travel(10)->minutes();

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();
    }

    public function test_the_countdown_expiry_from_the_page_ends_the_shared_session(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->post(route('session.timeout'))->assertNoContent();

        $this->assertSame('idle', $this->sessionRow($s->token)->ended_why);
        $this->assertGuest();
    }

    // -------------------------------------------------------------- الخروج

    public function test_logging_out_here_ends_the_shared_session_and_clears_the_cookie(): void
    {
        $s = $this->signedIn();
        config(['workspace.home_url' => 'https://wesalinnovation.sa']);
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();

        $response = $this->withUnencryptedCookie(self::COOKIE, $s->token)->post('/logout');

        $response->assertRedirect('https://wesalinnovation.sa');
        $this->assertSame('logout', $this->sessionRow($s->token)->ended_why);
        $this->assertGuest();
        $this->assertTrue(collect($response->headers->getCookies())->contains(fn ($cookie) => $cookie->getName() === self::COOKIE && $cookie->getExpiresTime() < time()));
    }

    // -------------------------------------------------------------- مسارات الدخول المحلية

    public function test_local_credential_pages_go_to_the_platform_login(): void
    {
        $this->connectPlatform();
        config(['workspace.login_url' => 'https://wesalinnovation.sa/login']);

        $this->get('/login')->assertRedirectContains('https://wesalinnovation.sa/login?next=');
        $this->post('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertRedirectContains('https://wesalinnovation.sa/login');
        $this->get('/forgot-password')->assertRedirectContains('https://wesalinnovation.sa/login');
        $this->get('/reset-password/token')->assertRedirectContains('https://wesalinnovation.sa/login');
    }

    public function test_a_protected_page_sends_guests_to_the_platform_login_with_the_way_back(): void
    {
        $this->connectPlatform();

        $first = $this->get('/projects')->assertRedirect(route('login'));
        $this->get($first->headers->get('Location'))
            ->assertRedirectContains('https://wesalinnovation.sa/login?next='.rawurlencode(url('/projects')));
    }

    public function test_the_password_is_changed_on_the_platform_not_here(): void
    {
        $s = $this->signedIn();
        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk();
        $user = User::where('platform_user_id', $s->uid)->first();
        $before = $user->password;

        $this->withUnencryptedCookie(self::COOKIE, $s->token)
            ->from('/profile')
            ->put('/password', ['current_password' => 'x', 'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!'])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', __('auth.managed_on_platform'));

        $this->assertSame($before, $user->fresh()->password);
    }

    // -------------------------------------------------------------- رابط مساعد وصال

    public function test_the_layout_links_back_to_the_assistant_when_sign_in_is_unified(): void
    {
        config(['workspace.chat_url' => 'https://wesalinnovation.sa/chat']);
        $s = $this->signedIn();

        $html = $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'href="https://wesalinnovation.sa/chat"'), 'الرابط في القائمة الجانبية وقائمة المستخدم');
        $this->assertStringContainsString('مساعد وصال', $html);
    }

    public function test_the_layout_has_no_assistant_link_while_sign_in_is_local(): void
    {
        config(['workspace.unified_auth' => false]);

        $html = $this->actingAs(User::factory()->role('pm')->create())->get('/profile')->assertOk()->getContent();

        $this->assertStringNotContainsString('wesalinnovation.sa/chat', $html);
        $this->assertStringNotContainsString('مساعد وصال', $html);
    }

    // -------------------------------------------------------------- قائمة واحدة لأقسام اللوحتين

    /** @return list<string> روابط أقسام لوحة المنصة في القائمة الجانبية كما رسمها الخادم */
    private function platformLinksIn(string $html): array
    {
        preg_match('/data-platform-links.*?<\/div>/s', $html, $block);
        preg_match_all('/href="([^"]+)"/', $block[0] ?? '', $m);

        return $m[1];
    }

    public function test_the_sysadmin_sidebar_lists_every_platform_section_with_deep_links(): void
    {
        config(['workspace.chat_url' => 'https://wesalinnovation.sa/chat']);
        $s = $this->signedIn(['role' => 'admin', 'org_role' => null]);

        $html = $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->assertOk()->getContent();

        $this->assertSame(
            array_map(fn (string $tab): string => 'https://wesalinnovation.sa/chat#dashboard/'.$tab, ['users', 'messages', 'tickets', 'content', 'experience', 'audit']),
            $this->platformLinksIn($html),
        );
        $this->assertStringContainsString('المستخدمون والأدوار', $html);
        $this->assertStringContainsString('تذاكر الدعم الفني', $html);
    }

    public function test_the_local_roles_tab_gives_way_to_the_platform_users_page_when_unified(): void
    {
        $s = $this->signedIn(['role' => 'admin', 'org_role' => null]);
        $html = $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/profile')->getContent();
        $this->assertStringNotContainsString('data-tab="roles_permissions"', $html);

        config(['workspace.unified_auth' => false]);
        $admin = User::factory()->role('sysadmin')->create();
        $html = $this->actingAs($admin)->get('/profile')->getContent();
        $this->assertStringContainsString('data-tab="roles_permissions"', $html);
        $this->assertSame([], $this->platformLinksIn($html));
    }

    public function test_hr_sees_only_the_users_section_and_other_roles_none(): void
    {
        $hr = $this->signedIn(['org_role' => 'hr']);
        $html = $this->withUnencryptedCookie(self::COOKIE, $hr->token)->get('/profile')->assertOk()->getContent();
        $this->assertCount(1, $this->platformLinksIn($html));
        $this->assertStringEndsWith('#dashboard/users', $this->platformLinksIn($html)[0]);

        $pm = $this->platformUser(['email' => 'pm@example.test', 'phone' => '0500000002', 'org_role' => 'pm']);
        $token = $this->platformSession($pm);
        $html = $this->flushSession()->withUnencryptedCookie(self::COOKIE, $token)->get('/profile')->assertOk()->getContent();
        $this->assertSame([], $this->platformLinksIn($html));
    }

    public function test_local_account_management_moves_to_the_platform_when_unified(): void
    {
        config(['workspace.chat_url' => 'https://wesalinnovation.sa/chat']);
        $s = $this->signedIn(['role' => 'admin', 'org_role' => null]);
        $to = 'https://wesalinnovation.sa/chat#dashboard/users';

        $this->withUnencryptedCookie(self::COOKIE, $s->token)->get('/users')->assertRedirect($to);
        $this->get('/users/create')->assertRedirect($to);
        $this->post('/users', ['name' => 'x', 'email' => 'x@example.test', 'role' => 'pm'])->assertRedirect($to);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);
    }

    public function test_local_account_management_stays_when_sign_in_is_local(): void
    {
        config(['workspace.unified_auth' => false]);

        $this->actingAs(User::factory()->role('sysadmin')->create())->get('/users')->assertOk();
    }

    // -------------------------------------------------------------- عطل المنصة

    public function test_an_unreachable_platform_leaves_the_local_rules_in_charge(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->connectPlatform();
        Schema::connection('platform')->drop('auth_sessions');

        $this->actingAs($user)->withUnencryptedCookie(self::COOKIE, str_repeat('b', 64))->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->travel(18)->minutes();
        $this->withUnencryptedCookie(self::COOKIE, str_repeat('b', 64))->get('/profile')->assertRedirect(route('login'));
    }

    public function test_a_malformed_cookie_counts_as_no_session(): void
    {
        $this->connectPlatform();

        $this->withUnencryptedCookie(self::COOKIE, 'not-a-token')->get('/profile')->assertRedirect(route('login'));
    }
}
