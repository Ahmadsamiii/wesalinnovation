<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_verifies_a_certificate_without_signing_in(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get(route('verify.show', $certificate->verification_code))
            ->assertOk()
            ->assertSee('مستند صحيح وساري')
            ->assertSee($certificate->recipient->name)
            ->assertSee($certificate->number);
    }

    public function test_code_is_accepted_with_dashes_spaces_and_lowercase(): void
    {
        $certificate = Certificate::factory()->create();
        $typed = strtolower(Certificate::formatVerificationCode($certificate->verification_code)).' ';

        $this->get(route('verify.show', ['code' => $typed]))->assertSee('مستند صحيح وساري');
    }

    public function test_revoked_certificate_is_reported_as_no_longer_valid(): void
    {
        $certificate = Certificate::factory()->create();
        $certificate->revoke(User::factory()->role('executive')->create(), 'خطأ');

        $this->get(route('verify.show', $certificate->verification_code))->assertSee('لم يعد سارياً');
    }

    public function test_employee_card_is_valid_only_while_the_account_is_active(): void
    {
        $employee = User::factory()->role('team_member')->create();
        $code = $employee->cardCode();

        $this->get(route('verify.show', $code))->assertSee('بطاقة موظف')->assertSee('مستند صحيح وساري');

        $employee->forceFill(['deactivated_at' => now()])->save();

        $this->get(route('verify.show', $code))->assertSee('لم يعد سارياً');
    }

    public function test_pending_letters_and_unknown_codes_verify_nothing(): void
    {
        ReferenceLetter::factory()->create(['verification_code' => 'ABCDEFGHJKLM']);

        $this->get(route('verify.show', 'ABCDEFGHJKLM'))->assertSee('لا يوجد مستند بهذا الرمز');
        $this->get(route('verify.show', 'ZZZZZZZZZZZZ'))->assertSee('لا يوجد مستند بهذا الرمز');
    }

    public function test_the_public_page_reveals_no_contact_details(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get(route('verify.show', $certificate->verification_code))
            ->assertDontSee($certificate->recipient->email);
    }

    public function test_team_member_sees_their_card_with_a_stable_code(): void
    {
        $employee = User::factory()->role('team_member')->create(['job_title' => 'مطور']);

        $first = $this->actingAs($employee)->get(route('card.show'))->assertOk()->assertSee($employee->employeeNumber());
        $code = $employee->fresh()->card_code;

        $this->assertNotNull($code);
        $this->actingAs($employee)->get(route('card.show'))->assertSee(Certificate::formatVerificationCode($code));
        $this->actingAs(User::factory()->role('client')->create())->get(route('card.show'))->assertForbidden();
    }
}
