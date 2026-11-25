<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_sends_the_browser_protections(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_through_the_local_tunnel_is_recognised(): void
    {
        // cloudflared forwards from 127.0.0.1 with X-Forwarded-Proto: https.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.trycloudflare.com'])
            ->get('/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_session_cookies_are_http_only(): void
    {
        $cookie = collect($this->get('/login')->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
