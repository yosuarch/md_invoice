<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\BackupSql;
use CodeIgniter\Database\BaseConnection;
use Config\Backup as BackupConfig;
use FilesystemIterator;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileObject;
use Throwable;

/**
 * Backups of the whole application data: every database table (app, Shield, migrations) plus writable/uploads,
 * as one backup-YYYYMMDD-HHMMSS[-label].tar.gz in Config\Backup::$path (with a .json manifest next to it for fast
 * listing). The dump is pure PHP over the app's own connection, read in a consistent snapshot (no locks).
 * Protection triggers are not part of a backup: a DBA installs them (php spark db:protection-sql).
 *
 * restore() replaces all data: a safety backup is made first, all tables are dropped (with their triggers) and
 * rebuilt from the backup, the uploads replaced, sessions deleted (everybody logs in again) and newer migrations run.
 * Backups and restores never run at the same time (lock file).
 */
final class BackupService
{
    public const FORMAT   = 1;
    public const TRIGGERS = ['manual', 'scheduled', 'pre-restore', 'cli'];
    private const NAME    = '/^backup-\d{8}-\d{6}(-[a-z0-9-]{1,48})?\.tar\.gz$/';

    private BackupConfig $config;
    private BaseConnection $db;

    /** @var resource|null */
    private $lock;

    /**
     * @param BackupConfig|null   $config Settings (null = config('Backup')).
     * @param BaseConnection|null $db     Connection (null = default).
     */
    public function __construct(?BackupConfig $config = null, ?BaseConnection $db = null)
    {
        $this->config = $config ?? config('Backup');
        $this->db     = $db ?? db_connect();
    }

    /**
     * Create a backup.
     *
     * @param string      $trigger manual, scheduled, pre-restore or cli.
     * @param string|null $label   Optional label (letters, digits, dashes), e.g. "before-update".
     * @param int|null    $userId  User who started it.
     *
     * @return string File name.
     *
     * @throws RuntimeException When another backup / restore runs or writing fails.
     */
    public function create(string $trigger = 'manual', ?string $label = null, ?int $userId = null): string
    {
        $this->acquireLock();

        try {
            $file = $this->createUnlocked($trigger, $label, $userId);
        } finally {
            $this->releaseLock();
        }

        AuditLogger::log('backup', 0, 'CREATE', null, ['file' => $file, 'trigger' => $trigger]);
        log_message('notice', 'Backup created: {file} ({trigger})', ['file' => $file, 'trigger' => $trigger]);

        return $file;
    }

    /**
     * Backups, newest first, each with its manifest plus file, size and restorable flag.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $files = is_dir($this->config->path) ? (glob($this->config->path . 'backup-*.tar.gz') ?: []) : [];
        $code  = $this->codeVersion();
        $rows  = [];

        foreach ($files as $path) {
            $file = basename($path);

            if (preg_match(self::NAME, $file) !== 1) {
                continue;
            }

            try {
                $manifest = $this->manifest($file);
            } catch (Throwable) {
                $manifest = ['error' => true];
            }

            $rows[] = $manifest + [
                'file'       => $file,
                'size'       => filesize($path) ?: 0,
                'restorable' => empty($manifest['error']) && (string) ($manifest['app_version'] ?? '') <= $code,
                'created_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($b['file'], $a['file']));

        return $rows;
    }

    /**
     * Manifest of a backup (from the .json next to it; read from the archive and cached when missing, e.g. for a
     * backup copied from another server).
     *
     * @param string $file File name.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException Unknown file or unreadable archive.
     */
    public function manifest(string $file): array
    {
        $path    = $this->path($file);
        $sidecar = substr($path, 0, -strlen('.tar.gz')) . '.json';

        if (is_file($sidecar)) {
            $data = json_decode((string) file_get_contents($sidecar), true);

            if (is_array($data)) {
                return $data;
            }
        }

        $archive = new PharData($path);
        $data    = json_decode($archive['manifest.json']->getContent(), true);
        unset($archive);

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException(lang('Backups.invalidBackup'));
        }

        @file_put_contents($sidecar, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    /**
     * Full path of a backup file (name checked: no folders, only backup-*.tar.gz).
     *
     * @param string $file File name.
     *
     * @return string
     *
     * @throws RuntimeException Invalid name or missing file.
     */
    public function path(string $file): string
    {
        if (basename($file) !== $file || preg_match(self::NAME, $file) !== 1 || ! is_file($this->config->path . $file)) {
            throw new RuntimeException(lang('Backups.notFound'));
        }

        return $this->config->path . $file;
    }

    /**
     * Delete a backup (and its manifest).
     *
     * @param string $file File name.
     *
     * @return void
     */
    public function delete(string $file): void
    {
        $path = $this->path($file);
        @unlink(substr($path, 0, -strlen('.tar.gz')) . '.json');

        if (! unlink($path)) {
            throw new RuntimeException(lang('Backups.deleteFailed'));
        }

        AuditLogger::log('backup', 0, 'DELETE', ['file' => $file], null);
    }

    /**
     * Remove scheduled backups older than keepDays, keeping at least the newest keepMin scheduled ones.
     * Manual, CLI and safety (pre-restore) backups are never pruned.
     *
     * @return list<string> Removed files.
     */
    public function prune(): array
    {
        $scheduled = array_values(array_filter($this->list(), static fn (array $b): bool => ($b['trigger'] ?? '') === 'scheduled'));
        $limit     = date('Y-m-d H:i:s', strtotime('-' . $this->config->keepDays . ' days'));
        $removed   = [];

        foreach (array_slice($scheduled, $this->config->keepMin) as $backup) {
            if (($backup['created_at'] ?? '') < $limit) {
                $this->delete($backup['file']);
                $removed[] = $backup['file'];
            }
        }

        return $removed;
    }

    /**
     * Replace all data (database + uploads) with a backup.
     *
     * @param string   $file   Backup file name.
     * @param int|null $userId User who restores.
     *
     * @return array{safety: string, migrated: bool}
     *
     * @throws RuntimeException When the backup is invalid, newer than the code, or the restore fails (the message
     *                          names the safety backup).
     */
    public function restore(string $file, ?int $userId = null): array
    {
        $path     = $this->path($file);
        $manifest = $this->manifest($file);

        if (($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException(lang('Backups.invalidBackup'));
        }

        if ((string) ($manifest['app_version'] ?? '') > $this->codeVersion()) {
            throw new RuntimeException(lang('Backups.newerThanCode', [(string) $manifest['app_version'], $this->codeVersion()]));
        }

        @set_time_limit(0);
        ignore_user_abort(true);
        $this->acquireLock();
        $tmp      = $this->tempDir();
        $safety   = null;
        $migrated = false;

        try {
            $archive = new PharData($path);
            $archive->extractTo($tmp, null, true);
            unset($archive);

            $sql = $tmp . 'database.sql';

            if (! is_file($sql) || hash_file('sha256', $sql) !== ($manifest['sha256'] ?? '')) {
                throw new RuntimeException(lang('Backups.checksumFailed'));
            }

            $safety = $this->createUnlocked('pre-restore', 'pre-restore', $userId);

            try {
                $this->loadDatabase($sql);
                $this->replaceUploads($tmp . 'uploads/');
            } catch (Throwable $e) {
                log_message('error', 'Restore of {file} failed: {message}', ['file' => $file, 'message' => $e->getMessage()]);

                throw new RuntimeException(lang('Backups.restoreFailed', [$e->getMessage(), $safety]), 0, $e);
            }

            $migrated = (string) ($manifest['app_version'] ?? '') < $this->codeVersion();

            if ($migrated) {
                service('migrations', null, $this->db, false)->setNamespace(null)->latest();
            }

            $this->clearSessions();
            cache()->clean();
            CalcSettings::clearCache();
            DocumentSequence::clearCache();
        } finally {
            $this->removeDir($tmp);
            $this->releaseLock();
        }

        AuditLogger::log('backup', 0, 'UPDATE', null, ['restored' => $file, 'safety_backup' => $safety, 'restored_by' => $userId]);
        log_message('notice', 'Backup {file} restored (safety backup {safety})', ['file' => $file, 'safety' => $safety]);

        return ['safety' => $safety, 'migrated' => $migrated];
    }

    /**
     * Latest app migration of the code (e.g. "2026-11-22-900004").
     *
     * @return string
     */
    public function codeVersion(): string
    {
        $versions = array_map(
            static fn (string $f): string => (string) preg_replace('/_.*$/', '', basename($f)),
            glob(APPPATH . 'Database/Migrations/*.php') ?: [],
        );
        sort($versions);

        return (string) end($versions);
    }

    /**
     * Latest app migration in the database.
     *
     * @return string
     */
    public function databaseVersion(): string
    {
        $row = $this->db->query("SELECT MAX(version) AS v FROM migrations WHERE namespace = 'App'")->getRowArray();

        return (string) ($row['v'] ?? '');
    }

    /**
     * Create a backup while the lock is held.
     *
     * @param string      $trigger Trigger.
     * @param string|null $label   Label.
     * @param int|null    $userId  User.
     *
     * @return string File name.
     */
    private function createUnlocked(string $trigger, ?string $label, ?int $userId): string
    {
        @set_time_limit(0);

        if (! in_array($trigger, self::TRIGGERS, true)) {
            throw new RuntimeException('Unknown backup trigger ' . $trigger);
        }

        $file = $this->newName($label);
        $tmp  = $this->tempDir();

        try {
            $sql    = $tmp . 'database.sql';
            $tables = $this->dumpDatabase($sql);
            $files  = $this->uploadFiles();

            $manifest = [
                'format'      => self::FORMAT,
                'created_at'  => date('Y-m-d H:i:s'),
                'database'    => $this->db->getDatabase(),
                'app_version' => $this->databaseVersion(),
                'trigger'     => $trigger,
                'label'       => $label !== null && $label !== '' ? $this->cleanLabel($label) : null,
                'user_id'     => $userId,
                'tables'      => $tables,
                'rows'        => array_sum($tables),
                'uploads'     => count($files),
                'sha256'      => hash_file('sha256', $sql),
            ];

            $tar = new PharData($tmp . 'backup.tar');
            $tar->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $tar->addFile($sql, 'database.sql');

            foreach ($files as $relative => $absolute) {
                $tar->addFile($absolute, 'uploads/' . $relative);
            }

            $tar->compress(Phar::GZ);
            unset($tar);

            if (! rename($tmp . 'backup.tar.gz', $this->config->path . $file)) {
                throw new RuntimeException(lang('Backups.writeFailed', [$this->config->path]));
            }

            file_put_contents(substr($this->config->path . $file, 0, -strlen('.tar.gz')) . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            $this->removeDir($tmp);
        }

        return $file;
    }

    /**
     * Write all tables to a database.sql file (consistent snapshot, one statement per line).
     *
     * @param string $target File to write.
     *
     * @return array<string, int> Rows per table.
     */
    private function dumpDatabase(string $target): array
    {
        $out    = new SplFileObject($target, 'w');
        $counts = [];

        $out->fwrite('-- de\'makmur invoicing backup of ' . $this->db->getDatabase() . ' at ' . date('Y-m-d H:i:s') . "\n");
        $out->fwrite("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");

        $this->db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        try {
            foreach ($this->tables() as $table) {
                $name   = BackupSql::identifier($table);
                $create = $this->db->query('SHOW CREATE TABLE ' . $name)->getRowArray();
                $out->fwrite("\n-- {$table}\nDROP TABLE IF EXISTS {$name};\n" . BackupSql::oneLine((string) ($create['Create Table'] ?? '')) . "\n");

                $columns = [];
                $binary  = [];

                foreach ($this->db->query('SHOW COLUMNS FROM ' . $name)->getResultArray() as $column) {
                    if (stripos((string) $column['Extra'], 'GENERATED') !== false) {
                        continue;
                    }

                    $columns[]                 = $column['Field'];
                    $binary[$column['Field']]  = BackupSql::isBinaryType((string) $column['Type']);
                }

                $query = $this->db->query('SELECT ' . implode(',', array_map(BackupSql::identifier(...), $columns)) . ' FROM ' . $name);
                $batch = [];
                $count = 0;

                while (is_array($row = $query->getUnbufferedRow('array'))) {
                    $batch[] = $row;
                    $count++;

                    if (count($batch) >= $this->config->batchRows) {
                        $out->fwrite(BackupSql::insert($table, $columns, $batch, $binary) . "\n");
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $out->fwrite(BackupSql::insert($table, $columns, $batch, $binary) . "\n");
                }

                $query->freeResult();
                $counts[$table] = $count;
            }
        } finally {
            $this->db->query('COMMIT');
        }

        $out->fwrite("\nSET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n");
        unset($out);

        return $counts;
    }

    /**
     * Drop all tables and run a database.sql file.
     *
     * @param string $sql File.
     *
     * @return void
     */
    private function loadDatabase(string $sql): void
    {
        $this->run('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tables() as $table) {
            $this->run('DROP TABLE IF EXISTS ' . BackupSql::identifier($table));
        }

        $file = new SplFileObject($sql, 'r');

        foreach (BackupSql::statements($file) as $statement) {
            $this->run($statement);
        }

        unset($file);
        $this->run('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Run one statement without query logging.
     *
     * @param string $sql Statement.
     *
     * @return void
     */
    private function run(string $sql): void
    {
        if ($this->db->simpleQuery($sql) === false) {
            $error = $this->db->error();

            throw new RuntimeException('SQL error ' . ($error['code'] ?? '') . ': ' . ($error['message'] ?? '') . ' in: ' . mb_substr($sql, 0, 120));
        }
    }

    /**
     * Base tables of the database.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $rows = $this->db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray();

        return array_map(static fn (array $row): string => (string) reset($row), $rows);
    }

    /**
     * Files under the uploads folder: relative path => absolute path.
     *
     * @return array<string, string>
     */
    private function uploadFiles(): array
    {
        $root  = rtrim(str_replace('\\', '/', $this->config->uploadsPath), '/') . '/';
        $files = [];

        if (! is_dir($root)) {
            return [];
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $item) {
            if ($item->isFile()) {
                $absolute                                  = str_replace('\\', '/', $item->getPathname());
                $files[substr($absolute, strlen($root))]   = $absolute;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Replace the uploads folder with the extracted backup copy.
     *
     * @param string $source Extracted uploads/ folder (may be missing when the backup had no files).
     *
     * @return void
     */
    private function replaceUploads(string $source): void
    {
        $root = rtrim($this->config->uploadsPath, '/\\') . '/';

        if (! is_dir($root)) {
            mkdir($root, 0775, true);
        }

        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir()) {
                $this->removeDir($item->getPathname() . '/');
            } elseif ($item->getFilename() !== 'index.html') {
                unlink($item->getPathname());
            }
        }

        if (! is_dir($source)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $target = $root . substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $source)));

            if ($item->isDir()) {
                is_dir($target) || mkdir($target, 0775, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Delete the session files (everybody logs in again after a restore).
     *
     * @return void
     */
    private function clearSessions(): void
    {
        foreach (glob(rtrim($this->config->sessionPath, '/\\') . '/ci_session*') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * New unique file name.
     *
     * @param string|null $label Label.
     *
     * @return string
     */
    private function newName(?string $label): string
    {
        if (! is_dir($this->config->path) && ! mkdir($this->config->path, 0775, true) && ! is_dir($this->config->path)) {
            throw new RuntimeException(lang('Backups.writeFailed', [$this->config->path]));
        }

        $suffix = $label !== null && $label !== '' ? '-' . $this->cleanLabel($label) : '';
        $base   = 'backup-' . date('Ymd-His');

        for ($n = 1; ; $n++) {
            $file = $base . $suffix . ($n > 1 ? '-' . $n : '') . '.tar.gz';

            if (! is_file($this->config->path . $file)) {
                return $file;
            }
        }
    }

    /**
     * Label for file names: lower case letters, digits and dashes, max. 40 characters.
     *
     * @param string $label Label.
     *
     * @return string
     */
    private function cleanLabel(string $label): string
    {
        $clean = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');

        return substr($clean === '' ? 'backup' : $clean, 0, 40);
    }

    /**
     * New temporary folder inside the backup folder.
     *
     * @return string Path with trailing slash.
     */
    private function tempDir(): string
    {
        $dir = rtrim($this->config->path, '/\\') . '/.tmp-' . bin2hex(random_bytes(6)) . '/';

        if (! mkdir($dir, 0775, true)) {
            throw new RuntimeException(lang('Backups.writeFailed', [$this->config->path]));
        }

        return $dir;
    }

    /**
     * Remove a folder recursively.
     *
     * @param string $dir Folder.
     *
     * @return void
     */
    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /**
     * Take the backup lock (fails at once when a backup or restore is running).
     *
     * @return void
     */
    private function acquireLock(): void
    {
        if (! is_dir($this->config->path)) {
            mkdir($this->config->path, 0775, true);
        }

        $handle = fopen($this->config->path . '.lock', 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException(lang('Backups.busy'));
        }

        $this->lock = $handle;
    }

    /**
     * Release the backup lock.
     *
     * @return void
     */
    private function releaseLock(): void
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }
}
