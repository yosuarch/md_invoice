<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark backup:list — the backups in Config\Backup::$path, newest first. Permanent command.
 */
class BackupList extends BaseCommand
{
    protected $group       = 'Invoicing';
    protected $name        = 'backup:list';
    protected $description = 'List the backups (newest first).';
    protected $usage       = 'backup:list';

    /**
     * @param array<int|string, string|null> $params Unused.
     *
     * @return int Exit code.
     */
    public function run(array $params): int
    {
        $backups = (new BackupService())->list();

        if ($backups === []) {
            CLI::write('No backups in ' . config('Backup')->path);

            return EXIT_SUCCESS;
        }

        $rows = array_map(static fn (array $b): array => [
            $b['file'],
            $b['created_at'],
            empty($b['error']) ? (string) ($b['trigger'] ?? '') : 'INVALID',
            number_format((int) ($b['rows'] ?? 0)),
            (string) ($b['uploads'] ?? 0),
            number_format($b['size'] / 1024, 0) . ' KB',
            (string) ($b['app_version'] ?? ''),
            $b['restorable'] ? 'yes' : 'no',
        ], $backups);

        CLI::table($rows, ['File', 'Created', 'Type', 'Rows', 'Files', 'Size', 'Version', 'Restorable']);
        CLI::write('Folder: ' . config('Backup')->path);

        return EXIT_SUCCESS;
    }
}
