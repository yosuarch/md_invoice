<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\BackupService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Settings → Backups: list, create, download and delete backups (backups.manage) and restore one (backups.restore,
 * confirmed by typing RESTORE). The work is done by App\Services\BackupService; the same is available on the
 * command line (php spark backup:create / backup:list / backup:restore).
 */
class Backups extends BaseController
{
    /** Word the user types to confirm a restore. */
    public const CONFIRM_WORD = 'RESTORE';

    /**
     * Backups list.
     *
     * @return string
     */
    public function index(): string
    {
        $service = new BackupService();
        $backups = $service->list();
        $userIds = array_values(array_unique(array_filter(array_map(static fn (array $b): int => (int) ($b['user_id'] ?? 0), $backups))));
        $users   = [];

        if ($userIds !== []) {
            foreach (db_connect()->table('users')->select('id, username, full_name')->whereIn('id', $userIds)->get()->getResultArray() as $u) {
                $users[(int) $u['id']] = $u['full_name'] ?: $u['username'];
            }
        }

        return view('settings/backups', [
            'backups'    => $backups,
            'users'      => $users,
            'folder'     => config('Backup')->path,
            'canRestore' => user_can('backups.restore'),
        ]);
    }

    /**
     * Create a backup now.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        $label = trim((string) $this->request->getPost('label'));

        try {
            $file = (new BackupService())->create('manual', $label === '' ? null : $label, $this->userId());
        } catch (RuntimeException $e) {
            return redirect()->to('settings/backups')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Backup create');
        }

        return redirect()->to('settings/backups')->with('message', lang('Backups.created', [$file]));
    }

    /**
     * Download a backup file.
     *
     * @param string $file File name.
     *
     * @return DownloadResponse|RedirectResponse
     */
    public function download(string $file): DownloadResponse|RedirectResponse
    {
        try {
            $path = (new BackupService())->path($file);
        } catch (RuntimeException $e) {
            return redirect()->to('settings/backups')->with('error', $e->getMessage());
        }

        AuditLogger::log('backup', 0, 'UPDATE', null, ['downloaded' => $file]);

        return $this->response->download($path, null)->setFileName($file);
    }

    /**
     * Delete a backup.
     *
     * @param string $file File name.
     *
     * @return RedirectResponse
     */
    public function delete(string $file): RedirectResponse
    {
        try {
            (new BackupService())->delete($file);
        } catch (RuntimeException $e) {
            return redirect()->to('settings/backups')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Backup delete');
        }

        return redirect()->to('settings/backups')->with('message', lang('Backups.deleted', [$file]));
    }

    /**
     * Restore a backup (replaces all data; everybody is logged out afterwards).
     *
     * @param string $file File name.
     *
     * @return RedirectResponse
     */
    public function restore(string $file): RedirectResponse
    {
        if (trim((string) $this->request->getPost('confirm')) !== self::CONFIRM_WORD) {
            return redirect()->to('settings/backups')->with('error', lang('Backups.restoreWrongWord'));
        }

        try {
            $result = (new BackupService())->restore($file, $this->userId());
        } catch (RuntimeException $e) {
            log_message('error', 'Restore of {file} failed: {message}', ['file' => $file, 'message' => $e->getMessage()]);

            return redirect()->to('settings/backups')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Backup restore');
        }

        auth()->logout();

        return redirect()->to('login')->with('message', lang('Backups.restored', [$file, $result['safety']]));
    }
}
