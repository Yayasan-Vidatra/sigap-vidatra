<?php

namespace Tests\Concerns;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;

/**
 * Mengambil tantangan dari /altcha/tantangan lalu memecahkannya seperti widget, dengan biaya hitung
 * kecil agar test cepat. Jalur verifikasinya sama dengan produksi.
 */
trait MemecahkanAltcha
{
    protected function tantanganAltcha(int $masaBerlakuDetik = 600): Challenge
    {
        config([
            'sigap.altcha.cost' => 1,
            'sigap.altcha.counter_min' => 1,
            'sigap.altcha.counter_maks' => 5,
            'sigap.altcha.masa_berlaku_detik' => $masaBerlakuDetik,
        ]);

        return Challenge::fromArray($this->getJson(route('altcha.tantangan'))->assertOk()->json());
    }

    protected function solusiAltcha(?Challenge $tantangan = null): string
    {
        $tantangan ??= $this->tantanganAltcha();
        $solusi = app(Altcha::class)->solveChallenge(new SolveChallengeOptions(
            algorithm: new Pbkdf2,
            challenge: $tantangan,
        ));

        return (new Payload($tantangan, $solusi))->toBase64();
    }
}
