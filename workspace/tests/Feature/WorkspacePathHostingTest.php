<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * مساحة العمل على wesalinnovation.sa/workspace بجانب نطاقها الفرعي القديم، وتحويل
 * القديم إلى الجديد (RedirectToCanonicalHost) دون كسر ما طُبع ووُزّع من روابط.
 */
class WorkspacePathHostingTest extends TestCase
{
    private const OLD = 'https://workspace.wesalinnovation.sa';

    private const NEW = 'https://wesalinnovation.sa/workspace';

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    private function production(array $hosts = ['wesalinnovation.sa', 'workspace.wesalinnovation.sa']): void
    {
        config(['app.url' => 'https://workspace.wesalinnovation.sa', 'workspace.hosts' => $hosts]);
        $this->app['env'] = 'production';
    }

    private function canonical(int $status = 302): void
    {
        $this->production();
        config(['workspace.canonical_url' => self::NEW, 'workspace.canonical_status' => $status]);
    }

    // -------------------------------------------------------------- المضيفات

    public function test_the_site_root_is_served_next_to_the_old_subdomain_when_listed(): void
    {
        $this->production();

        $this->get(self::OLD.'/login')->assertOk();
        $this->get('https://wesalinnovation.sa/login')->assertOk();
    }

    public function test_only_the_listed_hosts_are_served(): void
    {
        $this->production();

        $this->get('https://chat.wesalinnovation.sa/login')->assertBadRequest();
        $this->get('https://evil.example/login')->assertBadRequest();
    }

    public function test_the_app_url_host_alone_is_served_when_nothing_is_listed(): void
    {
        $this->production([]);

        $this->get(self::OLD.'/login')->assertOk();
        $this->get('https://wesalinnovation.sa/login')->assertBadRequest();
    }

    public function test_links_carry_the_workspace_path_when_served_from_it(): void
    {
        $this->production();

        $response = $this->withServerVariables([
            'SCRIPT_NAME' => '/workspace/index.php',
            'PHP_SELF' => '/workspace/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ])->get('https://wesalinnovation.sa/workspace/login')->assertOk();

        $this->assertStringContainsString('action="https://wesalinnovation.sa/workspace/login"', $response->getContent());
    }

    // -------------------------------------------------------------- التحويل

    public function test_the_old_host_redirects_reads_to_the_new_address_keeping_path_and_query(): void
    {
        $this->canonical();

        $this->get(self::OLD.'/verify/ABCDEF234567?ref=card')->assertRedirect(self::NEW.'/verify/ABCDEF234567?ref=card')->assertStatus(302);
        $this->get(self::OLD.'/kb/7')->assertRedirect(self::NEW.'/kb/7');
        $this->get(self::OLD.'/')->assertRedirect(self::NEW.'/');
        $this->get(self::OLD.'/login')->assertRedirect(self::NEW.'/login');
    }

    public function test_the_redirect_becomes_permanent_by_configuration(): void
    {
        $this->canonical(301);

        $this->get(self::OLD.'/kb/7')->assertStatus(301);
    }

    public function test_the_health_check_is_never_redirected(): void
    {
        $this->canonical();

        $this->get(self::OLD.'/up')->assertOk();
    }

    public function test_the_new_address_is_not_redirected(): void
    {
        $this->canonical();

        $this->get('https://wesalinnovation.sa/verify')->assertOk();
    }

    public function test_writes_from_the_old_host_are_not_redirected_so_their_body_is_not_lost(): void
    {
        $this->canonical();

        $response = $this->post(self::OLD.'/login', ['email' => 'a@example.test', 'password' => 'x']);

        $this->assertFalse(str_starts_with((string) $response->headers->get('Location'), self::NEW));
    }

    public function test_nothing_is_redirected_without_a_canonical_address(): void
    {
        $this->production();
        config(['workspace.canonical_url' => null]);

        $this->get(self::OLD.'/verify')->assertOk();
    }
}
