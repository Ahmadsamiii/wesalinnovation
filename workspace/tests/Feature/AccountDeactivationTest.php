<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sysadmin_can_deactivate_and_reactivate_an_account(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        $user = User::factory()->role('finance')->create();

        $this->actingAs($sysadmin)->post(route('users.deactivate', $user))->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isDeactivated());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UserDeactivated->value, 'subject_id' => $user->id]);

        $this->actingAs($sysadmin)->post(route('users.reactivate', $user))->assertSessionHas('status');
        $this->assertFalse($user->fresh()->isDeactivated());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UserReactivated->value, 'subject_id' => $user->id]);
    }

    public function test_sysadmin_cannot_deactivate_themselves(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        User::factory()->role('sysadmin')->create();

        $this->actingAs($sysadmin)->post(route('users.deactivate', $sysadmin))->assertSessionHas('error');

        $this->assertFalse($sysadmin->fresh()->isDeactivated());
    }

    public function test_deactivated_user_cannot_sign_in_and_is_told_why(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.deactivated')]);

        $this->assertGuest();
    }

    public function test_deactivation_reason_is_hidden_from_someone_without_the_password(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_open_session_ends_on_the_next_request_after_deactivation(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $user->forceFill(['deactivated_at' => now()])->save();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
