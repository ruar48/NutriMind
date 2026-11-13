<?php

use Database\Seeders\Support\DemoPassword;
use Illuminate\Support\Facades\Hash;

it('hashes the demo password once and reuses the hash', function () {
    $hash = DemoPassword::hash();

    expect(DemoPassword::hash())->toBe($hash);
    expect(Hash::check('password', $hash))->toBeTrue();
});
