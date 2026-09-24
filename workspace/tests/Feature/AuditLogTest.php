<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_with_the_time_of_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::AuthLogin->value, 'user_id' => $user->id]);
    }

    public function test_failed_login_records_the_email_but_never_the_password(): void
    {
        $this->post('/login', ['email' => 'someone@wesalinnovation.sa', 'password' => 'secret-guess']);

        $log = AuditLog::where('action', AuditAction::AuthFailed->value)->sole();

        $this->assertSame('someone@wesalinnovation.sa', $log->properties['email']);
        $this->assertStringNotContainsString('secret-guess', json_encode($log->properties));
    }

    public function test_sysadmin_can_read_and_filter_the_audit_log(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        $target = User::factory()->role('pm')->create(['name' => 'حساب مستهدف']);
        AuditLog::record(AuditAction::UserDeactivated, $target, actor: $sysadmin);
        AuditLog::record(AuditAction::AuthLogin, $sysadmin, actor: $sysadmin);

        $response = $this->actingAs($sysadmin)->get(route('audit.index', ['group' => 'user']));

        $response->assertOk();
        $response->assertSee('إيقاف حساب');
        $response->assertSee('حساب مستهدف');
        $response->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1
            && $logs->first()->action === AuditAction::UserDeactivated);
    }

    public function test_audit_log_is_for_sysadmin_only(): void
    {
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($executive)->get(route('audit.index'))->assertForbidden();
    }

    public function test_resetting_a_forgotten_password_completes_a_pending_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->pendingInvitation()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertNotNull($user->fresh()->invitation_accepted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PasswordReset->value, 'subject_id' => $user->id]);
    }
}
