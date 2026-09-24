<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AuditAction;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $sysadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysadmin = User::factory()->role('sysadmin')->create();
    }

    public function test_only_sysadmin_can_manage_accounts(): void
    {
        $pm = User::factory()->role('pm')->create();

        $this->actingAs($pm)->get(route('users.index'))->assertForbidden();
        $this->actingAs($pm)->post(route('users.store'), [])->assertForbidden();
        $this->actingAs($pm)->post(route('users.deactivate', $this->sysadmin))->assertForbidden();
    }

    public function test_sysadmin_sees_accounts_with_role_and_status(): void
    {
        User::factory()->role('finance')->create(['name' => 'منى المالية']);
        User::factory()->role('client')->pendingInvitation()->create(['name' => 'عميل جديد']);

        $response = $this->actingAs($this->sysadmin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('منى المالية');
        $response->assertSee('المدير المالي');
        $response->assertSee('عميل جديد');
        $response->assertSee('بانتظار قبول الدعوة');
    }

    public function test_accounts_can_be_filtered_by_role_and_status(): void
    {
        User::factory()->role('finance')->create(['name' => 'منى المالية']);
        User::factory()->role('client')->pendingInvitation()->create(['name' => 'عميل جديد']);

        $this->actingAs($this->sysadmin)
            ->get(route('users.index', ['role' => 'finance']))
            ->assertSee('منى المالية')
            ->assertDontSee('عميل جديد');

        $this->actingAs($this->sysadmin)
            ->get(route('users.index', ['status' => AccountStatus::Pending->value]))
            ->assertSee('عميل جديد')
            ->assertDontSee('منى المالية');
    }

    public function test_creating_an_account_assigns_the_role_and_sends_an_invitation(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->sysadmin)->post(route('users.store'), [
            'name' => 'سارة مديرة المشاريع',
            'email' => 'sara@wesalinnovation.sa',
            'role' => 'pm',
            'department' => 'المشاريع',
            'job_title' => 'مديرة مشاريع',
        ]);

        $user = User::where('email', 'sara@wesalinnovation.sa')->firstOrFail();

        $response->assertRedirect(route('users.edit', $user));
        $this->assertTrue($user->hasRole('pm'));
        $this->assertSame(AccountStatus::Pending, $user->status());
        $this->assertNotNull($user->invited_at);
        Notification::assertSentTo($user, AccountInvitation::class);

        // لا أحد يعرف كلمة المرور العشوائية حتى يعيّنها صاحب الحساب.
        $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => 'password']));

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UserCreated->value, 'subject_id' => $user->id, 'user_id' => $this->sysadmin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::InvitationSent->value, 'subject_id' => $user->id]);
    }

    public function test_account_creation_is_validated(): void
    {
        User::factory()->create(['email' => 'taken@wesalinnovation.sa']);

        $this->actingAs($this->sysadmin)
            ->post(route('users.store'), [
                'name' => '',
                'email' => 'taken@wesalinnovation.sa',
                'role' => 'superuser',
                'phone' => 'not-a-phone',
            ])
            ->assertSessionHasErrors(['name', 'email', 'role', 'phone']);
    }

    public function test_role_change_is_saved_and_audited(): void
    {
        $user = User::factory()->role('team_member')->create();

        $this->actingAs($this->sysadmin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'pm',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole('pm'));
        $this->assertFalse($user->fresh()->hasRole('team_member'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserRoleChanged->value,
            'subject_id' => $user->id,
        ]);
    }

    public function test_sysadmin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->sysadmin)->put(route('users.update', $this->sysadmin), [
            'name' => $this->sysadmin->name,
            'email' => $this->sysadmin->email,
            'role' => 'client',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($this->sysadmin->fresh()->hasRole('sysadmin'));
    }

    public function test_last_active_sysadmin_is_detected(): void
    {
        $this->assertTrue($this->sysadmin->isLastActiveSysadmin());

        // مدير معلّق الدعوة أو موقوف لا يستطيع الدخول، فلا يُحسب بديلاً.
        User::factory()->role('sysadmin')->pendingInvitation()->create();
        User::factory()->role('sysadmin')->deactivated()->create();
        $this->assertTrue($this->sysadmin->isLastActiveSysadmin());

        User::factory()->role('sysadmin')->create();
        $this->assertFalse($this->sysadmin->isLastActiveSysadmin());
    }

    public function test_correcting_a_pending_accounts_email_resends_the_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->role('client')->pendingInvitation()->create(['email' => 'typo@exmaple.com']);

        $this->actingAs($this->sysadmin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => 'client@example.com',
            'role' => 'client',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user->fresh(), AccountInvitation::class);
    }

    public function test_resending_an_invitation_only_works_while_pending(): void
    {
        Notification::fake();
        $active = User::factory()->role('pm')->create();

        $this->actingAs($this->sysadmin)
            ->post(route('users.invitation', $active))
            ->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_edit_page_shows_the_invitation_link_for_pending_accounts(): void
    {
        $user = User::factory()->role('client')->pendingInvitation()->create();

        $this->actingAs($this->sysadmin)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('رابط الدعوة الحالي')
            ->assertSee(e($user->invitationUrl()), false);
    }
}
