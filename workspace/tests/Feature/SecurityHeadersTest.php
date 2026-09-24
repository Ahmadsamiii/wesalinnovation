<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_every_response_carries_the_protective_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_only_over_https_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('https://localhost/up')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
    }
}
