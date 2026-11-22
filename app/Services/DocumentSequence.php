<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Documents;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Gap-safe document numbers from `document_sequence` (never MAX(id)+1).
 *
 * Formats come from `document_numbering` (Settings → Document numbering), with Config\Documents as defaults.
 * The counter row is locked with SELECT ... FOR UPDATE, so concurrent requests queue up. Call next() inside the
 * same transaction that inserts the document: if that transaction rolls back, the number is released too.
 * If a format was changed and a generated number already exists, the counter moves on to the next free one.
 */
final class DocumentSequence
{
    /**
     * Allowed tokens in a pattern.
     */
    public const TOKENS = ['{Y}', '{y}', '{m}', '{d}'];

    private const MAX_LENGTH = 32;

    private Documents $config;

    /**
     * Definitions cache per request: type => [pattern, period, table, column, label].
     *
     * @var array<string, array{label: string, pattern: string, period: string, table: string, column: string}>|null
     */
    private static ?array $definitions = null;

    public function __construct(?Documents $config = null)
    {
        $this->config = $config ?? config(Documents::class);
    }

    /**
     * Reserve and format the next number for a document type.
     *
     * @param string                 $docType Key of Config\Documents::$sequences (QUO, SPK, INV, KW, CN, DO, BAST, SKU).
     * @param DateTimeInterface|null $date    Document date (defaults to now); decides the period and date tokens.
     *
     * @return string Formatted number, e.g. "INV/2026/11/0001".
     *
     * @throws InvalidArgumentException For an unknown document type.
     */
    public function next(string $docType, ?DateTimeInterface $date = null): string
    {
        $def    = $this->definition($docType);
        $date ??= new DateTimeImmutable();
        $period = self::periodKey($def['period'], $date);
        $db     = db_connect();

        $work = function (BaseConnection $db) use ($docType, $period, $def, $date): string {
            // Skip numbers that already exist (only possible after a format change in the same period).
            for ($attempt = 0; $attempt < 1000; $attempt++) {
                $number = self::format($def['pattern'], $this->increment($db, $docType, $period), $date);

                if ($db->table($def['table'])->where($def['column'], $number)->countAllResults() === 0) {
                    return $number;
                }
            }

            throw new InvalidArgumentException("No free number for {$docType}; check its format in Settings.");
        };

        // Join the caller's transaction, or open one just for the counter.
        return $db->transDepth > 0 ? $work($db) : Transaction::run($work);
    }

    /**
     * The number the next document would get (the counter is not changed).
     *
     * @param string                 $docType Document type.
     * @param DateTimeInterface|null $date    Date (defaults to now).
     *
     * @return string
     */
    public function preview(string $docType, ?DateTimeInterface $date = null): string
    {
        $def    = $this->definition($docType);
        $date ??= new DateTimeImmutable();
        $row    = db_connect()->table('document_sequence')
            ->where(['doc_type' => $docType, 'period' => self::periodKey($def['period'], $date)])
            ->get()->getRowArray();

        return self::format($def['pattern'], (int) ($row['last_number'] ?? 0) + 1, $date);
    }

    /**
     * Definitions of all document types (database formats over config defaults).
     *
     * @return array<string, array{label: string, pattern: string, period: string, table: string, column: string}>
     */
    public function definitions(): array
    {
        if (self::$definitions === null) {
            $defs = $this->config->sequences;

            if (db_connect()->tableExists('document_numbering')) {
                foreach (db_connect()->table('document_numbering')->get()->getResultArray() as $row) {
                    if (isset($defs[$row['doc_type']])) {
                        $defs[$row['doc_type']]['pattern'] = $row['pattern'];
                        $defs[$row['doc_type']]['period']  = $row['period'];
                    }
                }
            }

            self::$definitions = $defs;
        }

        return self::$definitions;
    }

    /**
     * Forget cached formats (after they were changed in Settings).
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$definitions = null;
    }

    /**
     * Problems with a pattern / period combination (empty = valid).
     * The counter must be present; a monthly counter needs year and month in the number, a yearly one the year,
     * otherwise numbers would repeat when the counter restarts.
     *
     * @param string $pattern Pattern.
     * @param string $period  monthly | yearly | never.
     *
     * @return list<string>
     */
    public static function validate(string $pattern, string $period): array
    {
        $errors  = [];
        $hasYear = str_contains($pattern, '{Y}') || str_contains($pattern, '{y}');

        if (preg_match_all('/\{seq:([1-8])\}/', $pattern) !== 1) {
            $errors[] = lang('Messages.seqNeedsCounter');
        }

        $rest = preg_replace(['/\{seq:[1-8]\}/', '/\{[Yymd]\}/'], '', $pattern);

        if (preg_match('/^[A-Za-z0-9\/._-]*$/', (string) $rest) !== 1) {
            $errors[] = lang('Messages.seqChars');
        }

        if (! in_array($period, ['monthly', 'yearly', 'never'], true)) {
            $errors[] = lang('Messages.seqPeriod');
        } elseif ($period === 'monthly' && (! $hasYear || ! str_contains($pattern, '{m}'))) {
            $errors[] = lang('Messages.seqMonthly');
        } elseif ($period === 'yearly' && ! $hasYear) {
            $errors[] = lang('Messages.seqYearly');
        }

        if ($errors === [] && strlen(self::format($pattern, 99999999, new DateTimeImmutable('2026-12-31'))) > self::MAX_LENGTH) {
            $errors[] = lang('Messages.seqTooLong', [self::MAX_LENGTH]);
        }

        return $errors;
    }

    /**
     * Apply a pattern such as "INV/{Y}/{m}/{seq:4}".
     *
     * @param string            $pattern Pattern.
     * @param int               $number  Counter value.
     * @param DateTimeInterface $date    Date for the date tokens.
     *
     * @return string
     */
    public static function format(string $pattern, int $number, DateTimeInterface $date): string
    {
        $result = strtr($pattern, [
            '{Y}' => $date->format('Y'),
            '{y}' => $date->format('y'),
            '{m}' => $date->format('m'),
            '{d}' => $date->format('d'),
        ]);

        return (string) preg_replace_callback(
            '/\{seq:(\d+)\}/',
            static fn (array $m): string => str_pad((string) $number, (int) $m[1], '0', STR_PAD_LEFT),
            $result,
        );
    }

    /**
     * Counter period key.
     *
     * @param string            $period monthly | yearly | never.
     * @param DateTimeInterface $date   Date.
     *
     * @return string e.g. "2026-11", "2026" or "ALL".
     */
    private static function periodKey(string $period, DateTimeInterface $date): string
    {
        return match ($period) {
            'monthly' => $date->format('Y-m'),
            'yearly'  => $date->format('Y'),
            default   => 'ALL',
        };
    }

    /**
     * @param string $docType Document type.
     *
     * @return array{label: string, pattern: string, period: string, table: string, column: string}
     *
     * @throws InvalidArgumentException
     */
    private function definition(string $docType): array
    {
        return $this->definitions()[$docType] ?? throw new InvalidArgumentException("Unknown document type: {$docType}");
    }

    /**
     * Lock the counter row (creating it if needed) and increment it.
     *
     * @param BaseConnection $db      Connection in a transaction.
     * @param string         $docType Document type.
     * @param string         $period  Period key.
     *
     * @return int The reserved number.
     */
    private function increment(BaseConnection $db, string $docType, string $period): int
    {
        $now = date('Y-m-d H:i:s');

        $db->query(
            'INSERT IGNORE INTO document_sequence (doc_type, period, last_number, created_at, updated_at) VALUES (?, ?, 0, ?, ?)',
            [$docType, $period, $now, $now],
        );

        $row = $db->query(
            'SELECT id, last_number FROM document_sequence WHERE doc_type = ? AND period = ? FOR UPDATE',
            [$docType, $period],
        )->getRowArray();

        $next = (int) $row['last_number'] + 1;

        $db->query('UPDATE document_sequence SET last_number = ?, updated_at = ? WHERE id = ?', [$next, $now, $row['id']]);

        return $next;
    }
}
