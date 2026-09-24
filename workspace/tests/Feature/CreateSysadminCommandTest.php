<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateSysadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_sysadmin_is_created_with_an_invitation(): void
    {
        Notification::fake();

        $this->artisan('workspace:create-sysadmin', ['email' => 'Ops@Wesalinnovation.sa', '--name' => 'سارة'])
            ->expectsOutputToContain('أُنشئ حساب مدير النظام ops@wesalinnovation.sa')
            ->expectsOutputToContain('/invitation/')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertTrue($user->hasRole('sysadmin'));
        $this->assertSame('سارة', $user->name);
        $this->assertNull($user->invitation_accepted_at);
        Notification::assertSentTo($user, AccountInvitation::class);
    }

    public function test_it_refuses_duplicates_and_invalid_addresses(): void
    {
        User::factory()->create(['email' => 'taken@wesalinnovation.sa']);

        $this->artisan('workspace:create-sysadmin', ['email' => 'taken@wesalinnovation.sa'])->assertFailed();
        $this->artisan('workspace:create-sysadmin', ['email' => 'not-an-email'])->assertFailed();

        $this->assertSame(1, User::count());
    }
}
