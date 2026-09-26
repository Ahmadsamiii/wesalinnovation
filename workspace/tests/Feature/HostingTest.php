<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * ما يفرضه النشر على Hostinger: مجلد النطاق الفرعي داخل جذر الموقع العام،
 * فيصل التطبيق من مضيف الموقع العام أيضاً، ويرث ترويسات ملف .htaccess هناك.
 */
class HostingTest extends TestCase
{
    protected function tearDown(): void
    {
        // TrustHosts يضبط المضيفات الموثوقة للعملية كلها، فتبقى لبقية الاختبارات.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_production_serves_the_app_only_on_the_app_url_host(): void
    {
        config(['app.url' => 'https://workspace.wesalinnovation.sa']);
        $this->app['env'] = 'production';

        $this->get('https://workspace.wesalinnovation.sa/login')->assertOk();
        $this->get('https://wesalinnovation.sa/login')->assertBadRequest();
    }

    public function test_htaccess_replaces_the_inherited_policies_with_ones_the_app_works_under(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        // Alpine تقيّم تعابير القوالب بـ new Function، فبلا 'unsafe-eval' لا يعمل أي زر أو قائمة.
        $this->assertMatchesRegularExpression('/Header always set Content-Security-Policy "[^"]*script-src [^;"]*\'unsafe-eval\'/', $htaccess);

        $permissionsPolicy = $this->get('/login')->headers->get('Permissions-Policy');
        $this->assertStringContainsString('Header always set Permissions-Policy "'.$permissionsPolicy.'"', $htaccess);
    }
}
