<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * UUID version 7 (RFC 9562): 48-bit Unix timestamp in ms + random bits.
 * Time-ordered, so new rows append to the end of the index (better for MariaDB than random v4).
 */
final class Uuid
{
    /**
     * Generate a UUIDv7 string, e.g. "0193f3a2-7c1e-7b4a-9d2f-3a6b8c1d2e4f".
     *
     * @return string Lower-case canonical form (36 characters).
     */
    public static function v7(): string
    {
        $ms    = (int) floor(microtime(true) * 1000);
        $bytes = random_bytes(16);

        // 48-bit big-endian timestamp in bytes 0-5
        for ($i = 5; $i >= 0; $i--) {
            $bytes[$i] = chr($ms & 0xFF);
            $ms >>= 8;
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70); // version 7
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // variant 10xx

        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    /**
     * Whether a string is a canonical UUID (any version).
     *
     * @param string $value Candidate.
     *
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
