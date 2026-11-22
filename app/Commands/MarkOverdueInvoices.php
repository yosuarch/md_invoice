<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\InvoiceService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Scheduled job (database.md §11.8): set OVERDUE on issued / partially paid invoices past their due date.
 * Run once a day, e.g. Windows Task Scheduler or cron:  php spark invoices:mark-overdue
 * This is a permanent command (not a temporary test command).
 */
class MarkOverdueInvoices extends BaseCommand
{
    protected $group       = 'Invoicing';
    protected $name        = 'invoices:mark-overdue';
    protected $description = 'Set OVERDUE on unpaid invoices whose due date has passed (run daily).';
    protected $usage       = 'invoices:mark-overdue';

    /**
     * @param array<int|string, string|null> $params Unused.
     *
     * @return int Exit code (0 = success).
     */
    public function run(array $params): int
    {
        try {
            $changed = (new InvoiceService())->markOverdue();
        } catch (Throwable $e) {
            log_message('error', 'invoices:mark-overdue failed: {message}', ['message' => $e->getMessage()]);
            CLI::error('Failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        if ($changed === []) {
            CLI::write('No invoices became overdue.');
        } else {
            CLI::write(count($changed) . ' invoice(s) marked overdue: ' . implode(', ', $changed), 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
