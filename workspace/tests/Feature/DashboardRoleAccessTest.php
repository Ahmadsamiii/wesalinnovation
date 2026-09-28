<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_self_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_root_redirects_guest_to_login_and_user_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');

        $user = User::factory()->role('client')->create();
        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_executive_sees_all_seven_of_their_own_tabs(): void
    {
        $user = User::factory()->role('executive')->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('المدير التنفيذي');
        foreach (config('roles.executive.tabs') as $tab) {
            $response->assertSee($tab['label']);
        }
    }

    public function test_each_role_only_sees_its_own_tabs_not_another_roles(): void
    {
        $client = User::factory()->role('client')->create();

        $response = $this->actingAs($client)->followingRedirects()->get('/dashboard');

        $response->assertOk();
        foreach (config('roles.client.tabs') as $tab) {
            $response->assertSee($tab['label']);
        }
        // تبويبات مالية/تنفيذية حصرية لأدوار أخرى يجب ألا تظهر إطلاقاً لعميل
        $response->assertDontSee('الاعتمادات المالية');
        $response->assertDontSee('اعتماد المشاريع');
        $response->assertDontSee('طلبات التوظيف');
    }

    public function test_user_with_no_role_sees_safe_message_not_a_default_roles_tabs(): void
    {
        $user = User::factory()->create(); // بلا أي دور مُسند

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('لا يوجد دور مُسنَد لحسابك بعد');
        // يجب ألا يُمنح أي تبويب من أي دور بالخطأ
        $response->assertDontSee('اعتماد المشاريع');
        $response->assertDontSee('فواتيري');
    }

    public function test_all_nine_roles_exist_and_are_seeded(): void
    {
        $expected = ['executive', 'pm', 'finance', 'sysadmin', 'medical', 'hr', 'crm', 'team_member', 'client'];

        $this->assertSame($expected, array_keys(config('roles')));

        foreach ($expected as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_demo_seed_creates_a_signable_account_for_every_role_including_hr_and_crm(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (array_keys(config('roles')) as $role) {
            $user = User::firstWhere('email', $role.'@wesalinnovation.sa');

            $this->assertNotNull($user, $role);
            $this->assertTrue($user->hasRole($role), $role);
            $this->assertTrue(Hash::check('password', $user->password), $role);
            $this->assertTrue($user->status() === AccountStatus::Active, $role);
        }

        $this->assertSame('مدير الموارد البشرية', User::firstWhere('email', 'hr@wesalinnovation.sa')->roleLabel());
        $this->assertSame('مدير علاقات العملاء', User::firstWhere('email', 'crm@wesalinnovation.sa')->roleLabel());
        $this->assertTrue(User::hasActiveHr());
    }

    public function test_hr_and_crm_land_on_their_first_tab(): void
    {
        $this->actingAs(User::factory()->role('hr')->create())->get('/dashboard')->assertRedirect(route('hr.employees'));
        $this->actingAs(User::factory()->role('crm')->create())->get('/dashboard')->assertRedirect(route('crm.clients'));
    }

    public function test_hr_and_crm_see_their_own_tabs_and_not_each_others(): void
    {
        $hr = $this->actingAs(User::factory()->role('hr')->create())->followingRedirects()->get('/dashboard');

        $hr->assertOk()->assertSee('مدير الموارد البشرية');
        foreach (config('roles.hr.tabs') as $tab) {
            $hr->assertSee($tab['label']);
        }
        foreach (array_keys(config('roles.crm.tabs')) as $key) {
            $hr->assertDontSee('data-tab="'.$key.'"', false);
        }
        $hr->assertDontSee('الاعتمادات المالية')->assertDontSee('فواتيري');

        $crm = $this->actingAs(User::factory()->role('crm')->create())->followingRedirects()->get('/dashboard');

        $crm->assertOk()->assertSee('مدير علاقات العملاء');
        foreach (config('roles.crm.tabs') as $tab) {
            $crm->assertSee($tab['label']);
        }
        foreach (['الموظفون', 'طلبات التوظيف', 'الإفادات الوظيفية', 'الاعتمادات المالية'] as $label) {
            $crm->assertDontSee($label);
        }
    }

    public function test_dashboard_sends_each_role_to_its_first_built_tab(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();

        $this->actingAs($sysadmin)->get('/dashboard')->assertRedirect(route('content.index'));

        // تبويب أول لم يُبنَ مساره بعد يُتخطّى إلى أول تبويب مبني.
        config(['roles.sysadmin.tabs' => ['future' => ['label' => 'قريباً', 'route' => 'system.future'], ...config('roles.sysadmin.tabs')]]);

        $this->actingAs($sysadmin)->get('/dashboard')->assertRedirect(route('content.index'));
    }
}
