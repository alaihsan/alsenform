<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UnlockCode
{
    /**
     * The code is never shown to anyone (teachers unlock from their panel), so it is long and
     * random: guessing it, even at the rate limit, is not possible.
     */
    public function generate(): string
    {
        return Str::random(40);
    }

    public function hash(string $code): string
    {
        return Hash::make($code);
    }

    public function verify(string $code, string $hash): bool
    {
        return Hash::check($code, $hash);
    }
}
