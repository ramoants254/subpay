<?php

namespace App\Services\Daraja;

use InvalidArgumentException;

class PhoneNormaliser
{
    /**
     * Normalize a Kenyan phone number into Safaricom's required format: 2547XXXXXXXX or 2541XXXXXXXX.
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = '254'.substr($cleaned, 1);
        }

        if (! preg_match('/^254[17]\d{8}$/', $cleaned)) {
            throw new InvalidArgumentException("Invalid Kenyan phone number format: {$phone}");
        }

        return $cleaned;
    }
}
