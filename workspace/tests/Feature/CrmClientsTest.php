<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmClientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_lists_clients_with_their_projects_and_status(): void
    {
        $crm = User::factory()->role('crm')->create();
        $noor = User::factory()->role('client')->create(['name' => 'شركة النور', 'email' => 'noor@example.test', 'phone' => '0501112222']);
        $amal = User::factory()->role('client')->deactivated()->create(['name' => 'جمعية الأمل']);
        User::factory()->role('client')->pendingInvitation()->create(['name' => 'مؤسسة الغد']);
        Project::factory()->forClient($noor)->status(ProjectStatus::InProgress)->create(['name' => 'منصة النور']);
        Project::factory()->forClient($noor)->create(['name' => 'بوابة النور']);
        Project::factory()->forClient($amal)->status(ProjectStatus::Completed)->create(['name' => 'موقع الأمل']);
        Project::factory()->create(['name' => 'مشروع داخلي بلا عميل']);
        User::factory()->role('pm')->create(['name' => 'مدير ليس عميلاً']);

        $this->actingAs($crm)->get(route('crm.clients'))
            ->assertOk()
            ->assertSee('العملاء')
            ->assertSeeInOrder(['جمعية الأمل', 'موقوف', 'موقع الأمل', 'منجَز'])
            ->assertSeeInOrder(['شركة النور', 'noor@example.test', '0501112222', 'نشط', 'بوابة النور', 'مسودة', 'منصة النور', 'قيد التنفيذ'])
            ->assertSee('مؤسسة الغد')
            ->assertSee('بانتظار قبول الدعوة')
            ->assertSee('لا مشاريع')
            ->assertSee(route('projects.show', Project::firstWhere('name', 'منصة النور')), false)
            ->assertDontSee('مشروع داخلي بلا عميل')
            ->assertDontSee('مدير ليس عميلاً');
    }

    public function test_the_clients_page_is_read_only(): void
    {
        $crm = User::factory()->role('crm')->create();
        $client = User::factory()->role('client')->create();

        $response = $this->actingAs($crm)->get(route('crm.clients'))->assertOk();

        $response->assertDontSee(route('users.edit', $client), false);
        $response->assertDontSee('name="_method"', false);
        $this->actingAs($crm)->get(route('users.index'))->assertForbidden();
        $this->actingAs($crm)->put(route('users.update', $client), ['name' => 'اسم آخر'])->assertForbidden();
        $this->actingAs($crm)->post(route('users.deactivate', $client))->assertForbidden();
    }

    public function test_crm_searches_clients_by_name_or_email(): void
    {
        $crm = User::factory()->role('crm')->create();
        User::factory()->role('client')->create(['name' => 'شركة النور', 'email' => 'first@example.test']);
        User::factory()->role('client')->create(['name' => 'جمعية الأمل', 'email' => 'second@example.test']);

        $this->actingAs($crm)->get(route('crm.clients', ['q' => 'النور']))
            ->assertSee('شركة النور')
            ->assertDontSee('جمعية الأمل');

        $this->actingAs($crm)->get(route('crm.clients', ['q' => 'second@']))
            ->assertSee('جمعية الأمل')
            ->assertDontSee('شركة النور');

        $this->actingAs($crm)->get(route('crm.clients', ['q' => 'لا أحد']))->assertSee('لا عملاء مطابقين.');
    }

    public function test_only_crm_and_sysadmin_open_the_clients_page(): void
    {
        $this->get(route('crm.clients'))->assertRedirect(route('login'));

        foreach (['executive', 'pm', 'finance', 'medical', 'team_member', 'client', 'hr'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('crm.clients'))->assertForbidden();
        }

        foreach (['crm', 'sysadmin'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('crm.clients'))->assertOk();
        }
    }

    public function test_sysadmin_reads_client_projects_as_plain_names(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        $client = User::factory()->role('client')->create();
        $project = Project::factory()->forClient($client)->create(['name' => 'منصة النور']);

        $this->actingAs($sysadmin)->get(route('crm.clients'))
            ->assertOk()
            ->assertSee('منصة النور')
            ->assertDontSee(route('projects.show', $project), false);
    }
}
