<?php

namespace App\Http\Controllers;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use Illuminate\Http\JsonResponse;

/**
 * Membuat tantangan CAPTCHA ALTCHA untuk widget di formulir publik. Tanpa sesi dan cookie, sehingga
 * aman dipakai juga oleh formulir WBS.
 */
class AltchaController extends Controller
{
    public function __invoke(Altcha $altcha): JsonResponse
    {
        $tantangan = $altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: (int) config('sigap.altcha.cost'),
            counter: random_int((int) config('sigap.altcha.counter_min'), (int) config('sigap.altcha.counter_maks')),
            expiresAt: time() + (int) config('sigap.altcha.masa_berlaku_detik'),
        ));

        return response()
            ->json($tantangan->toArray())
            ->header('Cache-Control', 'no-store');
    }
}
