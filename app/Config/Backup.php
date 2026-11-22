<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Backups (App\Services\BackupService, Settings → Backups, php spark backup:*).
 * Override in .env: backup.path, backup.keepDays, backup.keepMin.
 */
class Backup extends BaseConfig
{
    /**
     * Folder for the backup archives (outside public/; copy it off the server daily).
     */
    public string $path = WRITEPATH . 'backups/';

    /**
     * Uploaded files included in every backup (company logo, product pictures, attachments).
     */
    public string $uploadsPath = WRITEPATH . 'uploads/';

    /**
     * Session files removed after a restore (everybody logs in again).
     */
    public string $sessionPath = WRITEPATH . 'session/';

    /**
     * Scheduled backups older than this many days are removed by `backup:create --prune` ...
     */
    public int $keepDays = 30;

    /**
     * ... but the newest this many scheduled backups always stay.
     */
    public int $keepMin = 7;

    /**
     * Rows per INSERT statement in database.sql.
     */
    public int $batchRows = 500;
}
