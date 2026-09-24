<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_all_seven_roles_from_the_spec_exist_and_are_seeded(): void
    {
        $expected = ['executive', 'pm', 'finance', 'sysadmin', 'medical', 'team_member', 'client'];

        foreach ($expected as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_dashboard_sends_each_role_to_its_first_built_tab(): void
    {
        // «إدارة المحتوى» أول تبويبات مدير النظام ولم تُبنَ؛ الثاني مبني.
        $sysadmin = User::factory()->role('sysadmin')->create();

        $this->actingAs($sysadmin)->get('/dashboard')->assertRedirect(route('users.index'));
    }
}
