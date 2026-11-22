<?php

/**
 * Display formatting for Indonesian business documents.
 */

if (! function_exists('format_idr')) {
    /**
     * Format an amount as Rupiah, e.g. 1234567.5 -> "Rp 1.234.567,50".
     *
     * Without $decimals the calculation setting decides: whole rupiah ("Rp 28.500.000") when amounts have 0 decimals,
     * unless the amount has cents (documents from before the setting).
     *
     * @param float|int|string|null $amount   Amount (DECIMAL columns arrive as strings).
     * @param int|null              $decimals Number of decimals to show (0 or 2; null = calculation setting).
     *
     * @return string Formatted amount.
     */
    function format_idr(float|int|string|null $amount, ?int $decimals = null): string
    {
        if ($decimals === null) {
            $decimals = \App\Services\CalcSettings::current()->amountDecimals;

            if ($decimals === 0 && round((float) ($amount ?? 0), 2) !== round((float) ($amount ?? 0))) {
                $decimals = 2;
            }
        }

        return 'Rp ' . number_format((float) ($amount ?? 0), $decimals, ',', '.');
    }
}

if (! function_exists('format_price')) {
    /**
     * Format a unit price with 2 up to 6 decimals (stored DECIMAL(19,6), trailing zeros after 2 decimals dropped),
     * e.g. "171171.171200" -> "Rp 171.171,1712". Exact string formatting (no float).
     *
     * @param float|int|string|null $price    Unit price.
     * @param bool                  $currency Prefix "Rp ".
     *
     * @return string
     */
    function format_price(float|int|string|null $price, bool $currency = true): string
    {
        $value    = bcadd(is_numeric((string) $price) ? (string) $price : '0', '0', 6);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = explode('.', ltrim($value, '-'));
        $fraction = str_pad(rtrim($fraction, '0'), 2, '0');
        $whole    = number_format((float) $whole, 0, ',', '.');

        return ($negative ? '-' : '') . ($currency ? 'Rp ' : '') . $whole . ',' . $fraction;
    }
}

if (! function_exists('format_qty')) {
    /**
     * Format a DECIMAL(12,3) quantity without trailing zeros, e.g. "2.500" -> "2,5".
     *
     * @param float|int|string|null $qty Quantity.
     *
     * @return string Formatted quantity.
     */
    function format_qty(float|int|string|null $qty): string
    {
        $formatted = number_format((float) ($qty ?? 0), 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (! function_exists('format_date_id')) {
    /**
     * Indonesian long date for printed documents, e.g. "22 November 2026" (optionally with the weekday: "Minggu, 22 November 2026").
     *
     * @param string|null $value       Y-m-d or Y-m-d H:i:s.
     * @param bool        $withWeekday Prefix the Indonesian weekday.
     *
     * @return string Empty for an empty value.
     */
    function format_date_id(?string $value, bool $withWeekday = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $months   = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $weekdays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $time     = strtotime($value);
        $date     = date('j', $time) . ' ' . $months[(int) date('n', $time)] . ' ' . date('Y', $time);

        return $withWeekday ? $weekdays[(int) date('w', $time)] . ', ' . $date : $date;
    }
}

if (! function_exists('number_words_id')) {
    /**
     * Whole number in Indonesian words, e.g. 2800000 -> "dua juta delapan ratus ribu".
     *
     * @param int|string $number Non-negative whole number (up to 999 trillion).
     *
     * @return string
     */
    function number_words_id(int|string $number): string
    {
        $n     = (int) $number;
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        return trim(match (true) {
            $n < 12                  => $words[$n],
            $n < 20                  => number_words_id($n - 10) . ' belas',
            $n < 100                 => number_words_id(intdiv($n, 10)) . ' puluh ' . number_words_id($n % 10),
            $n < 200                 => 'seratus ' . number_words_id($n - 100),
            $n < 1000                => number_words_id(intdiv($n, 100)) . ' ratus ' . number_words_id($n % 100),
            $n < 2000                => 'seribu ' . number_words_id($n - 1000),
            $n < 1000000             => number_words_id(intdiv($n, 1000)) . ' ribu ' . number_words_id($n % 1000),
            $n < 1000000000          => number_words_id(intdiv($n, 1000000)) . ' juta ' . number_words_id($n % 1000000),
            $n < 1000000000000       => number_words_id(intdiv($n, 1000000000)) . ' miliar ' . number_words_id($n % 1000000000),
            default                  => number_words_id(intdiv($n, 1000000000000)) . ' triliun ' . number_words_id($n % 1000000000000),
        });
    }
}

if (! function_exists('terbilang')) {
    /**
     * Amount in Indonesian words for kwitansi / agreements, e.g. "2800000.50" -> "Dua juta delapan ratus ribu rupiah lima puluh sen".
     *
     * @param float|int|string|null $amount Amount (DECIMAL string).
     *
     * @return string Sentence-case, ending with "rupiah" (and "sen" when there are cents).
     */
    function terbilang(float|int|string|null $amount): string
    {
        $value = number_format((float) ($amount ?? 0), 2, '.', '');
        [$whole, $cents] = explode('.', ltrim($value, '-'));
        $text  = (int) $whole === 0 ? 'nol' : number_words_id($whole);
        $text .= ' rupiah';

        if ((int) $cents > 0) {
            $text .= ' ' . number_words_id($cents) . ' sen';
        }

        return ucfirst(preg_replace('/\s+/', ' ', $text));
    }
}

if (! function_exists('date_words_id')) {
    /**
     * Date spelled out as in a Berita Acara: "Minggu tanggal dua puluh dua bulan November tahun dua ribu dua puluh enam".
     *
     * @param string $value Y-m-d (or datetime).
     *
     * @return string
     */
    function date_words_id(string $value): string
    {
        $time   = strtotime($value);
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $days   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return $days[(int) date('w', $time)] . ' tanggal ' . number_words_id((int) date('j', $time))
            . ' bulan ' . $months[(int) date('n', $time)] . ' tahun ' . number_words_id((int) date('Y', $time));
    }
}

if (! function_exists('format_amount')) {
    /**
     * Amount without currency for printed tables: "1.400.000", or "2.252.252,25" when there are cents.
     *
     * @param float|int|string|null $amount Amount (DECIMAL string).
     *
     * @return string
     */
    function format_amount(float|int|string|null $amount): string
    {
        $value = format_price($amount, false);

        return str_ends_with($value, ',00') ? substr($value, 0, -3) : $value;
    }
}

if (! function_exists('normalize_name')) {
    /**
     * Normalize a name/title/address for storage: lower case (multibyte safe), spaces collapsed and trimmed per
     * line, empty lines removed (multi-line addresses keep their line breaks). Shown in upper case with up().
     *
     * @param string|null $value Raw input.
     *
     * @return string|null Normalized value (null stays null).
     */
    function normalize_name(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $lines = preg_split('/\R/u', $value) ?: [];
        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/[^\S\n]+/u', ' ', $line)), $lines);

        return mb_strtolower(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== '')), 'UTF-8');
    }
}

if (! function_exists('up')) {
    /**
     * Display form of a stored name/title: upper case (multibyte safe). Escape the result with esc() in HTML.
     * Use only for names, titles and addresses, never for paragraphs, emails or codes.
     *
     * @param string|null $value Stored value.
     *
     * @return string
     */
    function up(?string $value): string
    {
        return mb_strtoupper((string) $value, 'UTF-8');
    }
}

if (! function_exists('month_label')) {
    /**
     * Month and year in the screen language, e.g. "November 2026" (id: "November 2026", "Agustus 2026").
     *
     * @param string $ym Y-m.
     *
     * @return string
     */
    function month_label(string $ym): string
    {
        $time = strtotime($ym . '-01');

        if (service('request')->getLocale() === 'id' && function_exists('format_date_id')) {
            return (string) preg_replace('/^\d+\s+/', '', format_date_id(date('Y-m-d', $time)));
        }

        return date('F Y', $time);
    }
}

if (! function_exists('decimal_input')) {
    /**
     * Stored decimal → value for a number input: dot decimal, at least 2 decimals, trailing zeros beyond dropped
     * ("171171.171200" -> "171171.1712", "190000.000000" -> "190000.00").
     *
     * @param float|int|string|null $value Stored value.
     *
     * @return string Empty for null / ''.
     */
    function decimal_input(float|int|string|null $value): string
    {
        if ($value === null || $value === '' || ! is_numeric((string) $value)) {
            return '';
        }

        [$whole, $fraction] = explode('.', bcadd((string) $value, '0', 6));

        return $whole . '.' . str_pad(rtrim($fraction, '0'), 2, '0');
    }
}
