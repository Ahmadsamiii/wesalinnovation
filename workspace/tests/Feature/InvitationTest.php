<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_sets_a_password_and_is_signed_in(): void
    {
        $user = User::factory()->role('pm')->pendingInvitation()->create();

        $this->get($user->invitationUrl())
            ->assertOk()
            ->assertSee($user->email);

        $response = $this->post($user->invitationUrl(), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame(AccountStatus::Active, $user->status());
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::InvitationAccepted->value, 'subject_id' => $user->id]);
    }

    public function test_invitation_link_works_only_once(): void
    {
        $user = User::factory()->pendingInvitation()->create();
        $url = $user->invitationUrl();

        $this->post($url, ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password']);
        $this->post(route('logout'));

        $this->get($url)->assertStatus(410);
        $this->post($url, ['password' => 'another-one', 'password_confirmation' => 'another-one'])->assertStatus(410);
    }

    public function test_invitation_link_expires(): void
    {
        $user = User::factory()->pendingInvitation()->create();
        $url = $user->invitationUrl();

        $this->travel(User::INVITATION_VALID_DAYS + 1)->days();

        $this->get($url)->assertStatus(410);
        // توليد الرابط من جديد لا يمدّ عمره: الصلاحية محسوبة من وقت الإرسال.
        $this->get($user->invitationUrl())->assertStatus(410);
    }

    public function test_resending_invalidates_the_previous_link(): void
    {
        Notification::fake();
        $sysadmin = User::factory()->role('sysadmin')->create();
        $user = User::factory()->pendingInvitation()->create(['invited_at' => now()->subHour()]);
        $oldUrl = $user->invitationUrl();

        $this->actingAs($sysadmin)->post(route('users.invitation', $user))->assertSessionHas('status');
        $this->post(route('logout'));

        $this->get($oldUrl)->assertStatus(410);
        $this->get($user->fresh()->invitationUrl())->assertOk();
    }

    public function test_tampered_link_is_rejected(): void
    {
        $victim = User::factory()->pendingInvitation()->create();
        $other = User::factory()->pendingInvitation()->create();

        // توقيع صالح لحساب آخر لا يفتح هذا الحساب.
        $forged = str_replace("/invitation/{$other->id}?", "/invitation/{$victim->id}?", $other->invitationUrl());

        $this->get($forged)->assertStatus(410);
    }

    public function test_deactivated_account_cannot_accept_its_invitation(): void
    {
        $user = User::factory()->pendingInvitation()->deactivated()->create();

        $this->get($user->invitationUrl())->assertStatus(410);
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = User::factory()->pendingInvitation()->create();

        $this->from($user->invitationUrl())
            ->post($user->invitationUrl(), ['password' => 'a-new-password', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');

        $this->assertSame(AccountStatus::Pending, $user->fresh()->status());
        $this->assertGuest();
    }
}
