<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * php spark backup:restore <file> [--force]
 *
 * Replaces ALL data (database + writable/uploads) with a backup from Config\Backup::$path. Asks to type the database
 * name (unless --force); a safety backup of the current data is made first. Afterwards a DBA must install the
 * protection triggers again (php spark db:protection-sql). Permanent command.
 */
class BackupRestore extends BaseCommand
{
    protected $group       = 'Invoicing';
    protected $name        = 'backup:restore';
    protected $description = 'Restore a backup (replaces ALL data; a safety backup is made first).';
    protected $usage       = 'backup:restore <file> [--force]';
    protected $arguments   = ['file' => 'Backup file name in the backup folder (see backup:list)'];
    protected $options     = ['--force' => 'Do not ask for confirmation (scripts)'];

    /**
     * @param array<int|string, string|null> $params Arguments and options.
     *
     * @return int Exit code.
     */
    public function run(array $params): int
    {
        $file = (string) ($params[0] ?? '');

        if ($file === '') {
            CLI::error('Usage: php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        $service = new BackupService();

        try {
            $manifest = $service->manifest(basename($file));
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_USER_INPUT;
        }

        $database = db_connect()->getDatabase();
        CLI::write('Backup:   ' . basename($file));
        CLI::write('Created:  ' . ($manifest['created_at'] ?? '?') . ' from database ' . ($manifest['database'] ?? '?') . ', version ' . ($manifest['app_version'] ?? '?'));
        CLI::write('Contents: ' . count($manifest['tables'] ?? []) . ' tables, ' . number_format((int) ($manifest['rows'] ?? 0)) . ' rows, ' . (int) ($manifest['uploads'] ?? 0) . ' files');
        CLI::write('ALL current data in database "' . $database . '" and writable/uploads will be replaced.', 'yellow');

        if (CLI::getOption('force') === null && ! array_key_exists('force', $params)) {
            $answer = CLI::prompt('Type the database name (' . $database . ') to continue');

            if ($answer !== $database) {
                CLI::write('Cancelled.');

                return EXIT_USER_INPUT;
            }
        }

        try {
            $result = $service->restore(basename($file));
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Restored. Safety backup of the previous data: ' . $result['safety'], 'green');

        if ($result['migrated']) {
            CLI::write('The backup was older than the code: migrations were run.');
        }

        CLI::write('Next: a DBA installs the protection triggers again (php spark db:protection-sql), then run php spark app:check.', 'yellow');

        return EXIT_SUCCESS;
    }
}
