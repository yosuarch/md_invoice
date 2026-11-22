<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * php spark backup:create [--label=x] [--prune]
 *
 * Creates a backup (database + writable/uploads) in Config\Backup::$path. The daily scheduled job runs it with
 * --prune: the backup is marked "scheduled" and scheduled backups older than keepDays are removed (the newest keepMin
 * always stay). Permanent command.
 */
class BackupCreate extends BaseCommand
{
    protected $group       = 'Invoicing';
    protected $name        = 'backup:create';
    protected $description = 'Back up the database and uploaded files (daily job: --prune).';
    protected $usage       = 'backup:create [--label=before-update] [--prune]';
    protected $options     = [
        '--label' => 'Label added to the file name, e.g. before-update',
        '--prune' => 'Scheduled backup: also remove old scheduled backups (Config\Backup keepDays / keepMin)',
    ];

    /**
     * @param array<int|string, string|null> $params Arguments and options.
     *
     * @return int Exit code.
     */
    public function run(array $params): int
    {
        $prune = CLI::getOption('prune') !== null || array_key_exists('prune', $params);
        $label = CLI::getOption('label');

        // "--label value" is parsed by CodeIgniter; also accept "--label=value"
        foreach (array_keys(CLI::getOptions() + $params) as $key) {
            if (is_string($key) && str_starts_with($key, 'label=')) {
                $label = substr($key, 6);
            }
        }

        $label = is_string($label) && $label !== '' ? $label : null;

        try {
            $service = new BackupService();
            $file    = $service->create($prune ? 'scheduled' : 'cli', $label);
            CLI::write('Backup created: ' . config('Backup')->path . $file, 'green');

            if ($prune) {
                foreach ($service->prune() as $removed) {
                    CLI::write('Removed old backup: ' . $removed);
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'backup:create failed: {message}', ['message' => $e->getMessage()]);
            CLI::error('Backup failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
