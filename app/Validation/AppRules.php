<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Project-specific validation rules (registered in Config\Validation::$ruleSets).
 */
class AppRules
{
    /**
     * Indonesian tax ID (NPWP): 15 digits (old format, e.g. 01.234.567.8-901.000) or 16 digits (NIK-based).
     * Dots, dashes and spaces are allowed as separators.
     *
     * @param string|null $value Submitted value.
     * @param string|null $error Error message (set on failure).
     *
     * @return bool
     */
    public function npwp(?string $value, ?string &$error = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (preg_match('/^[0-9 .\-]+$/', $value) !== 1) {
            $error = lang('Validation.npwpChars');

            return false;
        }

        $digits = strlen(preg_replace('/\D/', '', $value));

        if ($digits !== 15 && $digits !== 16) {
            $error = lang('Validation.npwpDigits');

            return false;
        }

        return true;
    }

    /**
     * Phone number: digits with optional +, spaces, dashes, dots and parentheses; 6-20 digits.
     *
     * @param string|null $value Submitted value.
     * @param string|null $error Error message (set on failure).
     *
     * @return bool
     */
    public function phone(?string $value, ?string &$error = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $digits = strlen(preg_replace('/\D/', '', $value));

        if (preg_match('/^\+?[0-9 ().\-]+$/', $value) !== 1 || $digits < 6 || $digits > 20) {
            $error = lang('Validation.phoneInvalid');

            return false;
        }

        return true;
    }
}
