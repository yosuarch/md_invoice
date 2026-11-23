<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Support\ProtectionTriggers;
use App\Models\CompanyProfileModel;
use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Documents;
use Throwable;

/**
 * Permanent command: checks whether this installation is ready for production use (see DEPLOYMENT.md).
 * Prints OK / WARN / FAIL per check and exits with 1 when something FAILs. Never prints secrets.
 */
class AppCheck extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'app:check';
    protected $description = 'Check PHP, configuration, folders, database, triggers and company data before going live.';
    protected $usage       = 'app:check';

    private int $failed = 0;
    private int $warned = 0;

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int Exit code.
     */
    public function run(array $params): int
    {
        CLI::write("de'makmur invoicing — installation check (" . ENVIRONMENT . ')', 'light_cyan');

        $this->section('PHP');
        $this->check(PHP_VERSION_ID >= 80200, 'PHP ' . PHP_VERSION, 'PHP 8.2 or newer is required');

        foreach (['intl', 'mbstring', 'bcmath', 'mysqli', 'gd', 'fileinfo', 'dom', 'json', 'phar', 'zlib'] as $ext) {
            $this->check(extension_loaded($ext), "extension {$ext}", "enable extension={$ext} in php.ini (gd: PDFs and QR codes; fileinfo: uploads; phar, zlib: backups)");
        }

        $maxUpload = min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size'));
        $wanted    = config(Documents::class)->attachmentMaxKb * 1024;
        $this->check($maxUpload >= $wanted, 'upload limit ' . round($maxUpload / 1048576, 1) . ' MB', 'raise upload_max_filesize / post_max_size to at least '
            . round($wanted / 1048576) . 'M (+ some margin for post_max_size)', true);
        $this->check(date_default_timezone_get() === config('App')->appTimezone, 'timezone ' . date_default_timezone_get(), 'app timezone is ' . config('App')->appTimezone, true);

        $this->section('Configuration');
        $this->check(ENVIRONMENT === 'production', 'CI_ENVIRONMENT = ' . ENVIRONMENT, 'set CI_ENVIRONMENT = production in .env (hides errors, disables the debug toolbar)', true);
        $baseUrl = (string) config('App')->baseURL;
        $this->check(str_starts_with($baseUrl, 'https://'), 'app.baseURL uses https', 'serve the app over HTTPS and set app.baseURL = https://...', true);
        $this->check(! str_contains($baseUrl, 'localhost') || ENVIRONMENT !== 'production', 'app.baseURL is not localhost', 'set app.baseURL to the real address');
        $this->check(config('Encryption')->key !== '', 'encryption key set', 'run: php spark key:generate', true);
        $before = config('Filters')->globals['before'];
        $this->check(in_array('csrf', $before, true) || array_key_exists('csrf', $before), 'CSRF filter enabled globally', 'keep csrf in Config\Filters::$globals[before]');
        $this->check(ENVIRONMENT !== 'production' || config('App')->forceGlobalSecureRequests, 'HTTPS enforced (forceGlobalSecureRequests)', 'set app.forceGlobalSecureRequests = true in .env when the site runs on HTTPS', true);

        $this->section('Folders');

        foreach (['cache', 'logs', 'session', 'uploads', 'debugbar'] as $dir) {
            $path = WRITEPATH . $dir;

            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            $this->check(is_dir($path) && is_writable($path), "writable/{$dir} writable", "give the web server user write access to {$path}", $dir === 'debugbar');
        }

        $this->check(! is_dir(FCPATH . 'uploads'), 'no uploads inside public/', 'uploaded files must stay in writable/uploads/');

        $this->section('Backups');
        $backupPath = config('Backup')->path;

        if (! is_dir($backupPath)) {
            @mkdir($backupPath, 0775, true);
        }

        $this->check(is_dir($backupPath) && is_writable($backupPath), 'backup folder writable (' . $backupPath . ')', 'give the web server user and the scheduled job write access to it');
        $realBackup = realpath($backupPath) ?: $backupPath;
        $realPublic = realpath(FCPATH) ?: FCPATH;
        $this->check(! str_starts_with(str_replace('\\', '/', $realBackup), str_replace('\\', '/', $realPublic)), 'backup folder outside public/', 'set backup.path to a folder that the web server does not serve');
        $latest = (new BackupService())->list()[0] ?? null;
        $this->check($latest !== null && strtotime((string) $latest['created_at']) > time() - 2 * 86400,
            $latest === null ? 'no backup yet' : 'latest backup ' . $latest['created_at'], 'schedule php spark backup:create --prune daily and copy the backup folder off the server', true);

        $this->section('Database');

        try {
            $db = db_connect();
            $db->query('SELECT 1');
            $this->ok('connected to ' . $db->getDatabase());

            $runner  = service('migrations');
            $runner->setNamespace(null);
            $done    = array_column($db->table('migrations')->select('version')->get()->getResultArray(), 'version');
            $pending = array_filter($runner->findMigrations(), static fn ($m): bool => ! in_array($m->version, $done, true));
            $this->check($pending === [], 'migrations up to date', count($pending) . ' pending: run php spark migrate');

            $triggers = array_column($db->query('SHOW TRIGGERS')->getResultArray(), 'Trigger');
            $missing  = array_diff(array_keys(ProtectionTriggers::definitions()), $triggers);
            $this->check($missing === [], 'protection triggers installed', count($missing) . ' missing: a DBA runs the SQL from php spark db:protection-sql', true);

            $devTrigger = $db->query("SHOW TRIGGERS WHERE `Trigger` = 'trg_payment_no_delete'")->getRowArray();
            $this->check($devTrigger === null || ! str_contains((string) ($devTrigger['Statement'] ?? ''), 'allow_test_purge') || ENVIRONMENT !== 'production',
                'no development trigger variant', 'production must use php spark db:protection-sql without --dev');

            $this->check($db->table('client_proposal_status')->countAllResults() > 0 && $db->table('document_numbering')->countAllResults() > 0,
                'lookups and numbering seeded', 'run php spark db:seed DatabaseSeeder');
            $this->check($db->table('region')->countAllResults() > 500, 'regions loaded', 'run php spark db:seed DatabaseSeeder', true);

            $admins = $db->table('auth_groups_users')->where('group', 'app_admin')->countAllResults();
            $this->check($admins > 0, "{$admins} app_admin user(s)", 'create one: php spark shield:user create, then shield:user addgroup -g app_admin');

            $profile = (new CompanyProfileModel())->getProfile();
            $this->check($profile !== null && trim((string) $profile['company_name']) !== '', 'company profile filled', 'fill Settings → Company (printed on every document)');
            $this->check($profile !== null && ! empty($profile['tax_id']) && ! empty($profile['address']), 'company NPWP and address', 'fill NPWP and address in Settings → Company', true);
            $this->check($profile !== null && ! empty($profile['default_bank_account_id']), 'default bank account', 'choose a default bank account in Settings → Company (printed on invoices)', true);
        } catch (Throwable $e) {
            $this->fail('database: ' . $e->getMessage());
        }

        CLI::newLine();
        CLI::write(sprintf('%d failed, %d warnings.', $this->failed, $this->warned), $this->failed > 0 ? 'red' : ($this->warned > 0 ? 'yellow' : 'green'));
        CLI::write('Also schedule (daily): php spark invoices:mark-overdue and php spark backup:create --prune; copy the backup folder off the server.');

        return $this->failed > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write($title, 'white');
    }

    /**
     * @param bool   $ok      Result.
     * @param string $label   What was checked.
     * @param string $fix     How to fix it.
     * @param bool   $warning Only a warning (not blocking).
     */
    private function check(bool $ok, string $label, string $fix, bool $warning = false): void
    {
        if ($ok) {
            $this->ok($label);
        } elseif ($warning) {
            $this->warned++;
            CLI::write('  WARN  ' . $label . ' — ' . $fix, 'yellow');
        } else {
            $this->fail($label . ' — ' . $fix);
        }
    }

    private function ok(string $label): void
    {
        CLI::write('  OK    ' . $label, 'green');
    }

    private function fail(string $message): void
    {
        $this->failed++;
        CLI::write('  FAIL  ' . $message, 'red');
    }

    /**
     * php.ini size in bytes.
     */
    private function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $num   = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G'     => $num * 1073741824,
            'M'     => $num * 1048576,
            'K'     => $num * 1024,
            default => $num,
        };
    }
}
