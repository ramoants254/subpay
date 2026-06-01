<?php

use App\Services\Daraja\PhoneNormaliser;

test('it normalizes numbers starting with 07 to 2547', function () {
    $normalized = PhoneNormaliser::normalize('0712345678');
    expect($normalized)->toBe('254712345678');
});

test('it normalizes numbers starting with 01 to 2541', function () {
    $normalized = PhoneNormaliser::normalize('0112345678');
    expect($normalized)->toBe('254112345678');
});

test('it normalizes numbers starting with +254', function () {
    $normalized = PhoneNormaliser::normalize('+254712345678');
    expect($normalized)->toBe('254712345678');
});

test('it leaves already normalized numbers intact', function () {
    $normalized = PhoneNormaliser::normalize('254712345678');
    expect($normalized)->toBe('254712345678');
});

test('it strips spaces, hyphens, and other symbols gracefully', function () {
    $normalized = PhoneNormaliser::normalize(' +254 712-345 678 ');
    expect($normalized)->toBe('254712345678');
});

test('it throws an exception for invalid phone lengths or country codes', function ($invalidPhone) {
    expect(fn () => PhoneNormaliser::normalize($invalidPhone))
        ->toThrow(InvalidArgumentException::class);
})->with([
    '071234567',      // Too short
    '07123456789',     // Too long
    '256712345678',    // Incorrect country prefix (Uganda)
    'not-a-phone-no',  // Alphabetical
    '123456789012',    // Incorrect starting numbers
]);
