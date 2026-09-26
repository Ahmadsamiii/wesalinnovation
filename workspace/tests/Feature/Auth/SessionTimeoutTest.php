<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_in_starts_a_fresh_clock(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHas('auth.started_at', now()->getTimestamp())
            ->assertSessionHas('auth.seen_at', now()->getTimestamp());
    }

    public function test_activity_within_the_limit_keeps_the_session(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        for ($i = 0; $i < 6; $i++) {
            $this->travel(14)->minutes();
            $this->post(route('session.heartbeat'))->assertNoContent();
        }

        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_idle_session_ends_with_the_reason_on_the_login_page_and_in_the_audit_log(): void
    {
        $user = User::factory()->role('finance')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $this->travel(18)->minutes();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('login'))->assertSee('سجّلنا خروجك تلقائياً بعد 15 دقيقة دون نشاط');
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AuthTimedOut->value,
            'user_id' => $user->id,
            'properties->reason' => 'idle',
        ]);
    }

    public function test_requests_inside_the_grace_period_still_pass(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $this->travel(16)->minutes();

        $this->get('/profile')->assertOk();
    }

    public function test_page_scripts_get_401_json_after_the_idle_limit(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->travel(18)->minutes();

        $this->postJson(route('session.heartbeat'))
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'idle');
        $this->assertGuest();
    }

    public function test_the_absolute_limit_ends_even_an_active_session(): void
    {
        $user = User::factory()->role('pm')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        for ($i = 0; $i < 60; $i++) {
            $this->travel(12)->minutes();
            $this->post(route('session.heartbeat'))->assertNoContent();
        }

        $this->travel(12)->minutes();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::AuthTimedOut->value, 'properties->reason' => 'expired']);
    }

    public function test_the_countdown_in_the_page_signs_out_and_is_recorded_as_automatic(): void
    {
        $user = User::factory()->role('executive')->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $this->postJson(route('session.timeout'))->assertNoContent();

        $this->assertGuest();
        $this->get(route('login', ['timeout' => 1]))->assertSee('سجّلنا خروجك تلقائياً بعد 15 دقيقة دون نشاط');
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::AuthTimedOut->value, 'user_id' => $user->id]);
    }

    public function test_login_page_explains_a_timeout_even_when_the_stored_session_is_gone(): void
    {
        $this->get(route('login', ['timeout' => 1]))
            ->assertOk()
            ->assertSee('سجّلنا خروجك تلقائياً بعد 15 دقيقة دون نشاط');
    }

    public function test_signed_in_pages_carry_the_warning_and_guest_pages_do_not(): void
    {
        $user = User::factory()->role('pm')->create();

        $this->actingAs($user)->get('/profile')
            ->assertSee('هل ما زلت هنا؟')
            ->assertSee('idleTimeout(', false);

        auth()->logout();

        $this->get(route('login'))->assertDontSee('هل ما زلت هنا؟');
    }

    public function test_print_pages_carry_the_warning_too(): void
    {
        $finance = User::factory()->role('finance')->create();
        $invoice = Invoice::factory()->withAmount('100')->issued()->create(['created_by' => $finance->id]);

        $this->actingAs($finance)->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('هل ما زلت هنا؟');
    }

    public function test_remember_me_is_no_longer_offered_or_honoured(): void
    {
        $user = User::factory()->role('pm')->create();

        $this->get(route('login'))->assertDontSee('name="remember"', false);

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertCookieMissing(auth()->guard('web')->getRecallerName());
    }

    public function test_existing_remember_me_tokens_are_forgotten(): void
    {
        $user = User::factory()->create(['remember_token' => 'still-valid-cookie-token']);

        $migration = require database_path('migrations/2026_09_26_111602_forget_remember_me_tokens.php');
        $migration->up();

        $this->assertNull($user->fresh()->remember_token);
    }
}
