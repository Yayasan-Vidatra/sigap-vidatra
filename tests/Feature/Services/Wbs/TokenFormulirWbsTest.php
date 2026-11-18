<?php

namespace Tests\Feature\Services\Wbs;

use App\Services\Wbs\TokenFormulirWbs;
use Tests\TestCase;

class TokenFormulirWbsTest extends TestCase
{
    public function test_fresh_token_is_valid(): void
    {
        $this->assertNotNull(TokenFormulirWbs::nonce(TokenFormulirWbs::terbitkan()));
    }

    public function test_tampered_or_malformed_tokens_are_rejected(): void
    {
        $token = TokenFormulirWbs::terbitkan();
        [$isi, $tanda] = explode('.', $token);

        $this->assertNull(TokenFormulirWbs::nonce($isi.'.'.strrev($tanda)));
        $this->assertNull(TokenFormulirWbs::nonce('x'.$isi.'.'.$tanda));
        $this->assertNull(TokenFormulirWbs::nonce('bukan-token'));
        $this->assertNull(TokenFormulirWbs::nonce(null));
        $this->assertFalse(TokenFormulirWbs::pakai($isi.'.'.strrev($tanda)));
    }

    public function test_token_expires_after_its_lifetime(): void
    {
        $this->freezeTime();
        $token = TokenFormulirWbs::terbitkan();

        $this->travel(TokenFormulirWbs::masaMenit())->minutes();
        $this->assertNotNull(TokenFormulirWbs::nonce($token));

        $this->travel(1)->seconds();
        $this->assertNull(TokenFormulirWbs::nonce($token));
        $this->assertFalse(TokenFormulirWbs::pakai($token));
    }

    public function test_token_can_be_used_only_once(): void
    {
        $token = TokenFormulirWbs::terbitkan();

        $this->assertTrue(TokenFormulirWbs::pakai($token));
        $this->assertFalse(TokenFormulirWbs::pakai($token));
        $this->assertNull(TokenFormulirWbs::nonce($token));
    }

    public function test_token_contains_only_a_random_nonce_and_expiry(): void
    {
        $isi = json_decode(base64_decode(strtr(explode('.', TokenFormulirWbs::terbitkan())[0], '-_', '+/')), true);

        $this->assertSame(['n', 'e'], array_keys($isi));
    }
}
