<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * العربية واتجاه RTL كانا مضبوطين في ملف .env محلي فقط، فعملت كل نسخة
 * جديدة من المستودع بالإنجليزية ومن اليسار لليمين دون أن يلاحظ أي اختبار.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render_in_arabic_right_to_left(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<html lang="ar" dir="rtl">', false);
        $response->assertSee('البريد الإلكتروني');
        $response->assertSee('تسجيل الدخول');
        $response->assertDontSee('Forgot your password?');
    }

    public function test_validation_errors_are_in_arabic_with_readable_field_names(): void
    {
        $response = $this->from('/login')->post('/login', []);

        $response->assertSessionHasErrors([
            'email' => 'حقل البريد الإلكتروني مطلوب.',
            'password' => 'حقل كلمة المرور مطلوب.',
        ]);
    }

    public function test_failed_login_message_is_in_arabic(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@wesalinnovation.sa',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.']);
    }

    public function test_dates_use_riyadh_time(): void
    {
        $this->assertSame('Asia/Riyadh', now()->getTimezone()->getName());
    }
}
