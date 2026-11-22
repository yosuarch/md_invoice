<?php

declare(strict_types=1);

namespace App\Models;

use RuntimeException;

/**
 * Base for lookup tables with a UNIQUE `code`. Application logic refers to rows by code.
 */
abstract class LookupModel extends BaseModel
{
    /**
     * Cache of code => id per model class.
     *
     * @var array<class-string, array<string, int>>
     */
    private static array $codeCache = [];

    /**
     * Id of the row with this code.
     *
     * @param string $code Lookup code, e.g. 'TRANSFER'.
     *
     * @return int
     *
     * @throws RuntimeException When the code does not exist (missing seed data).
     */
    public function idForCode(string $code): int
    {
        $cache = &self::$codeCache[static::class];

        if (! isset($cache[$code])) {
            $row = $this->where('code', $code)->first();

            if ($row === null) {
                throw new RuntimeException(sprintf('Lookup code "%s" not found in %s.', $code, $this->table));
            }

            $cache[$code] = (int) $row['id'];
        }

        return $cache[$code];
    }

    /**
     * Additional checks that CI validation rules cannot express (e.g. composite uniqueness).
     *
     * @param array<string, mixed> $data Submitted values.
     * @param int|null             $id   Row being edited, null on create.
     *
     * @return array<string, string> field => error message.
     */
    public function extraErrors(array $data, ?int $id): array
    {
        return [];
    }

    /**
     * Checks for lookups versioned by validity period: UNIQUE (code, valid_from) and valid_to >= valid_from.
     *
     * @param array<string, mixed> $data Submitted values.
     * @param int|null             $id   Row being edited.
     *
     * @return array<string, string>
     */
    protected function periodErrors(array $data, ?int $id): array
    {
        $errors = [];
        $code   = (string) ($data['code'] ?? '');
        $from   = (string) ($data['valid_from'] ?? '');
        $to     = (string) ($data['valid_to'] ?? '');

        if ($code !== '' && $from !== '') {
            $builder = $this->db->table($this->table)->where('code', $code)->where('valid_from', $from);

            if ($id !== null) {
                $builder->where('id !=', $id);
            }

            if ($builder->countAllResults() > 0) {
                $errors['valid_from'] = 'An entry with this code already starts on this date.';
            }
        }

        if ($to !== '' && $from !== '' && $to < $from) {
            $errors['valid_to'] = 'Valid to must be on or after valid from.';
        }

        return $errors;
    }
}
