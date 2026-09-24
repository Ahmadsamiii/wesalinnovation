<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_name_and_phone_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'phone' => '+966 500000000',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('+966 500000000', $user->phone);
    }

    public function test_email_cannot_be_changed_from_the_profile(): void
    {
        // البريد هوية الدخول وعنوان الدعوات؛ يغيّره مدير النظام وحده.
        $user = User::factory()->create(['email' => 'original@wesalinnovation.sa']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'attacker@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('original@wesalinnovation.sa', $user->refresh()->email);
    }

    public function test_users_cannot_delete_their_own_account(): void
    {
        // الحسابات مرتبطة بقرارات ومهام وعقود؛ الإيقاف بيد مدير النظام.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }
}
