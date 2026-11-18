<?php

namespace Tests\Feature\Http\Controllers;

use AltchaOrg\Altcha\Altcha;
use Tests\TestCase;

class AltchaControllerTest extends TestCase
{
    public function test_returns_a_signed_challenge_that_expires_after_ten_minutes(): void
    {
        $this->freezeTime();

        $response = $this->getJson('/altcha/tantangan');

        $response->assertOk();
        $response->assertJsonPath('parameters.algorithm', 'PBKDF2/SHA-256');
        $this->assertNotEmpty($response->json('signature'));
        $this->assertEqualsWithDelta(now()->getTimestamp() + 600, $response->json('parameters.expiresAt'), 2);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_challenge_endpoint_sets_no_cookies(): void
    {
        $response = $this->getJson('/altcha/tantangan');

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_challenge_endpoint_is_rate_limited(): void
    {
        config(['sigap.altcha.batas_tantangan_per_menit' => 3]);

        foreach (range(1, 3) as $permintaan) {
            $this->getJson('/altcha/tantangan')->assertOk();
        }

        $this->getJson('/altcha/tantangan')->assertTooManyRequests();
    }

    public function test_challenge_limit_is_global_and_not_keyed_by_ip_address(): void
    {
        config(['sigap.altcha.batas_tantangan_per_menit' => 2]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->getJson('/altcha/tantangan')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])->getJson('/altcha/tantangan')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.3'])->getJson('/altcha/tantangan')->assertTooManyRequests();
    }

    public function test_missing_hmac_key_refuses_to_issue_challenges(): void
    {
        config(['sigap.altcha.hmac_key' => '']);
        $this->app->forgetInstance(Altcha::class);

        $response = $this->getJson('/altcha/tantangan');

        $response->assertServerError();
    }
}
