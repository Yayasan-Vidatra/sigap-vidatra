<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LindungiJalurWbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wbs_responses_carry_privacy_headers(): void
    {
        $this->assertHeaderPrivasi($this->get('/wbs')->assertOk());
    }

    public function test_wbs_error_responses_also_carry_privacy_headers_and_no_cookies(): void
    {
        $response = $this->postJson('/wbs/lampiran/unggah', ['nama' => 'a.pdf', 'ukuran' => 10, 'kanal' => 'wbs']);

        $response->assertStatus(419);
        $this->assertHeaderPrivasi($response);
    }

    public function test_content_security_policy_allows_no_third_party_origin(): void
    {
        $kebijakan = (string) $this->get('/wbs')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $kebijakan);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'(;|$)/", $kebijakan);
        $this->assertStringContainsString("object-src 'none'", $kebijakan);
        $this->assertDoesNotMatchRegularExpression('#https?://|\*#', $kebijakan);
    }

    public function test_page_scripts_carry_the_policy_nonce(): void
    {
        $response = $this->get('/wbs');
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $cocok);

        $this->assertNotEmpty($cocok[1] ?? null);
        $this->assertStringContainsString('nonce="'.$cocok[1].'"', $response->getContent());
    }

    public function test_cookies_sent_by_the_browser_are_not_answered_with_cookies(): void
    {
        $response = $this->withCookie('sigap-vidatra-session', 'sesi-lain')->get('/wbs');

        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    private function assertHeaderPrivasi(TestResponse $response): void
    {
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertSame([], $response->headers->getCookies());
    }
}
