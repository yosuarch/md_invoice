<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * SQL text helpers for backups (pure, unit-tested).
 *
 * database.sql holds exactly one statement per line, ending with ";": values are escaped so they never contain a raw
 * line break, and CREATE TABLE statements are folded to one line. Lines starting with "--" are comments.
 */
final class BackupSql
{
    /**
     * Quote a value for an INSERT: NULL, 0x… for binary values, otherwise a single-quoted string with \0, \n, \r,
     * \\, ', " and Ctrl-Z escaped (same rules as mysqli::real_escape_string).
     *
     * @param mixed $value  Column value.
     * @param bool  $binary Column is binary (BLOB / BINARY / VARBINARY).
     *
     * @return string
     */
    public static function value(mixed $value, bool $binary = false): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = (string) $value;

        if ($binary) {
            return $value === '' ? "''" : '0x' . bin2hex($value);
        }

        return "'" . strtr($value, [
            '\\'   => '\\\\',
            "\0"   => '\\0',
            "\n"   => '\\n',
            "\r"   => '\\r',
            "'"    => "\\'",
            '"'    => '\\"',
            "\x1a" => '\\Z',
        ]) . "'";
    }

    /**
     * Quote an identifier: `name` (backticks doubled).
     *
     * @param string $name Table or column name.
     *
     * @return string
     */
    public static function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * One INSERT statement for several rows.
     *
     * @param string                          $table   Table name.
     * @param list<string>                    $columns Column names.
     * @param list<array<int|string, mixed>>  $rows    Rows (values in column order, or keyed by column).
     * @param array<string, bool>             $binary  Binary columns.
     *
     * @return string Statement ending with ";" (no line break).
     */
    public static function insert(string $table, array $columns, array $rows, array $binary = []): string
    {
        $values = [];

        foreach ($rows as $row) {
            $row      = array_values($row);
            $values[] = '(' . implode(',', array_map(
                static fn (int $i): string => self::value($row[$i] ?? null, $binary[$columns[$i]] ?? false),
                array_keys($columns),
            )) . ')';
        }

        return 'INSERT INTO ' . self::identifier($table) . ' (' . implode(',', array_map(self::identifier(...), $columns)) . ') VALUES ' . implode(',', $values) . ';';
    }

    /**
     * Fold a multi-line statement (SHOW CREATE TABLE) to one line ending with ";".
     *
     * @param string $statement SQL.
     *
     * @return string
     */
    public static function oneLine(string $statement): string
    {
        return rtrim(preg_replace('/\s*\R\s*/', ' ', trim($statement)) ?? '', ';') . ';';
    }

    /**
     * Statements of a database.sql file, read line by line (comments and empty lines skipped, final ";" removed).
     *
     * @param iterable<string> $lines Lines (with or without line breaks).
     *
     * @return \Generator<int, string>
     */
    public static function statements(iterable $lines): \Generator
    {
        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue; // SplFileObject yields false at the end of the file
            }

            $line = rtrim($line, "\r\n");

            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }

            yield str_ends_with($line, ';') ? substr($line, 0, -1) : $line;
        }
    }

    /**
     * Whether a column type is binary (hex-encoded in backups).
     *
     * @param string $type Column type from SHOW COLUMNS (e.g. "varbinary(16)", "longblob").
     *
     * @return bool
     */
    public static function isBinaryType(string $type): bool
    {
        return preg_match('/^(tiny|medium|long)?blob|^(var)?binary/i', $type) === 1;
    }
}
