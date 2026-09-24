<?php

namespace Tests\Feature;

use App\Enums\ReferenceLetterStatus;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceLetterTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_requests_and_executive_approves_with_a_snapshot(): void
    {
        $employee = User::factory()->role('team_member')->create(['job_title' => 'مصممة', 'department' => 'التصميم', 'joined_at' => '2024-03-01']);
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($employee)->post(route('reference-letters.store'), ['purpose' => 'بنك الرياض'])->assertRedirect(route('reference-letters.index'));
        $letter = ReferenceLetter::sole();
        $this->assertSame(ReferenceLetterStatus::Pending, $letter->status);
        $this->assertNull($letter->number);

        $this->actingAs($executive)->get(route('reference-letters.index'))->assertSee($employee->name);
        $this->actingAs($executive)->post(route('reference-letters.approve', $letter));

        $letter->refresh();
        $this->assertSame(ReferenceLetterStatus::Approved, $letter->status);
        $this->assertMatchesRegularExpression('/^REF-\d{4}-0001$/', $letter->number);
        $this->assertSame('مصممة', $letter->job_title);

        // ترقية لاحقة لا تغيّر نص إفادة صدرت.
        $employee->update(['job_title' => 'مديرة التصميم']);
        $this->actingAs($employee)->get(route('reference-letters.show', $letter))
            ->assertOk()
            ->assertSee('مصممة')
            ->assertDontSee('مديرة التصميم')
            ->assertSee('بنك الرياض');
    }

    public function test_rejection_needs_a_reason_the_employee_sees(): void
    {
        $letter = ReferenceLetter::factory()->create();
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($executive)->post(route('reference-letters.reject', $letter), [])->assertSessionHasErrors('note');
        $this->actingAs($executive)->post(route('reference-letters.reject', $letter), ['note' => 'أكمل بياناتك الوظيفية أولاً']);

        $this->actingAs($letter->requester)->get(route('reference-letters.index'))->assertSee('أكمل بياناتك الوظيفية أولاً');
        $this->actingAs($letter->requester)->get(route('reference-letters.show', $letter))->assertForbidden();
    }

    public function test_letters_are_private_to_their_requester(): void
    {
        $letter = ReferenceLetter::factory()->create();
        $letter->approve(User::factory()->role('executive')->create());
        $colleague = User::factory()->role('team_member')->create();

        $this->actingAs($colleague)->get(route('reference-letters.show', $letter))->assertForbidden();
        $this->actingAs($colleague)->get(route('reference-letters.index'))->assertDontSee($letter->number);
    }

    public function test_clients_cannot_request_letters_and_nobody_approves_their_own(): void
    {
        $this->actingAs(User::factory()->role('client')->create())->get(route('reference-letters.create'))->assertForbidden();

        $executive = User::factory()->role('executive')->create();
        $own = ReferenceLetter::factory()->create(['requester_id' => $executive->id]);

        $this->actingAs($executive)->post(route('reference-letters.approve', $own))->assertForbidden();
    }
}
