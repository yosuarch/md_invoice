<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Support\ProtectionTriggers;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Prints the SQL that installs the protection triggers (App\Database\Support\ProtectionTriggers).
 * A DBA runs it once as a user with the TRIGGER privilege, e.g.
 *   php spark db:protection-sql > protection.sql
 *   mariadb -u root -p md_invoice < protection.sql
 * Use --dev for the development variant with the @allow_test_purge bypass (never on production).
 * --check lists which triggers are installed.
 */
class ProtectionSql extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:protection-sql';
    protected $description = 'Print the SQL for the tamper-protection triggers (run it as a DBA), or --check what is installed.';
    protected $usage       = 'db:protection-sql [--dev] [--check]';
    protected $options     = [
        '--dev'   => 'Include the @allow_test_purge bypass for removing test data (development only).',
        '--check' => 'Show which protection triggers exist in the database.',
    ];

    /**
     * @param array<int|string, string|null> $params Options.
     *
     * @return int Exit code.
     */
    public function run(array $params): int
    {
        if (CLI::getOption('check') !== null || array_key_exists('check', $params)) {
            $existing = array_column(db_connect()->query('SHOW TRIGGERS')->getResultArray(), 'Trigger');
            $missing  = array_diff(array_keys(ProtectionTriggers::definitions()), $existing);

            CLI::write(count(ProtectionTriggers::definitions()) - count($missing) . ' of ' . count(ProtectionTriggers::definitions()) . ' protection triggers installed.', $missing === [] ? 'green' : 'yellow');

            foreach ($missing as $name) {
                CLI::write('  missing: ' . $name, 'yellow');
            }

            return $missing === [] ? EXIT_SUCCESS : EXIT_ERROR;
        }

        $dev = CLI::getOption('dev') !== null || array_key_exists('dev', $params);

        echo "-- de'makmur invoicing: protection triggers" . ($dev ? ' (DEVELOPMENT variant with test bypass)' : '') . "\n";
        echo "-- Run as a user with the TRIGGER privilege on the application database.\n";

        foreach (ProtectionTriggers::statements($dev) as $statement) {
            echo str_starts_with($statement, 'CREATE')
                ? "DELIMITER //\n{$statement} //\nDELIMITER ;\n"
                : "{$statement};\n";
        }

        return EXIT_SUCCESS;
    }
}
