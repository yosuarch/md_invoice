<?php

declare(strict_types=1);

use App\Models\CustomerModel;
use App\Models\ProductModel;
use App\Services\BackupService;
use Config\Backup;
use Tests\Support\AppTestCase;

/**
 * Backups and restore on the test database (temporary backup, uploads and session folders).
 *
 * @internal
 */
final class BackupRestoreTest extends AppTestCase
{
    private string $root;
    private Backup $config;
    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root                = rtrim(sys_get_temp_dir(), '/\\') . '/md-backup-test-' . bin2hex(random_bytes(4)) . '/';
        $this->config              = new Backup();
        $this->config->path        = $this->root . 'backups/';
        $this->config->uploadsPath = $this->root . 'uploads/';
        $this->config->sessionPath = $this->root . 'session/';

        foreach (['uploads/products', 'session'] as $dir) {
            mkdir($this->root . $dir, 0775, true);
        }

        $this->service = new BackupService($this->config);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);

        parent::tearDown();
    }

    public function testBackupAndRestoreRoundTrip(): void
    {
        $admin    = $this->createUser('admin');
        $customer = $this->createCustomer();
        $product  = $this->createProduct('GOODS', '190000');
        $this->approvedProposal($customer, [['product_id' => $product, 'quantity' => '150', 'unit_price' => '190000', 'price_base' => 'INCL']], $admin);
        file_put_contents($this->root . 'uploads/products/picture.png', 'ORIGINAL');
        file_put_contents($this->root . 'uploads/index.html', 'index');
        file_put_contents($this->root . 'session/ci_session_abc', 'session');

        $file   = $this->service->create('manual', 'Round trip!', $admin);
        $before = $this->checksums();

        $this->assertMatchesRegularExpression('/^backup-\d{8}-\d{6}-round-trip\.tar\.gz$/', $file);
        $manifest = $this->service->manifest($file);
        $this->assertSame(BackupService::FORMAT, $manifest['format']);
        $this->assertSame('manual', $manifest['trigger']);
        $this->assertSame(1, $manifest['tables']['client_proposal_list']);
        $this->assertSame(2, $manifest['uploads']);

        // Change data and files after the backup.
        (new CustomerModel())->insert(['cl_organization_name' => 'later school', 'cl_id' => \App\Libraries\Uuid::v7(), 'is_active' => 1]);
        $sku = (new ProductModel())->find($product)['pl_sku'];
        (new ProductModel())->update($product, ['id' => $product, 'pl_sku' => $sku, 'pl_title' => 'changed title']);
        db_connect()->table('client_proposal_item')->where('id >', 0)->delete();
        file_put_contents($this->root . 'uploads/products/picture.png', 'CHANGED');
        file_put_contents($this->root . 'uploads/products/new.png', 'NEW');
        $this->assertNotSame($before, $this->checksums());

        $result = $this->service->restore($file, $admin);

        $this->assertSame($before, $this->checksums(), 'every table is back as it was');
        $this->assertSame('ORIGINAL', file_get_contents($this->root . 'uploads/products/picture.png'));
        $this->assertFileDoesNotExist($this->root . 'uploads/products/new.png');
        $this->assertFileDoesNotExist($this->root . 'session/ci_session_abc', 'everybody is logged out');
        $this->assertFalse($result['migrated']);
        $this->assertSame('pre-restore', $this->service->manifest($result['safety'])['trigger']);
        $this->assertSame(1, db_connect()->table('audit_log')->where('table_name', 'backup')->where('action', 'UPDATE')->countAllResults());

        // The safety backup holds the changed data.
        $this->assertSame(2, $this->service->manifest($result['safety'])['tables']['customer_list']);
    }

    public function testDamagedOrNewerBackupsAreRefused(): void
    {
        $file    = $this->service->create('cli');
        $sidecar = $this->config->path . substr($file, 0, -7) . '.json';
        $data    = json_decode((string) file_get_contents($sidecar), true);

        file_put_contents($sidecar, json_encode(['sha256' => str_repeat('0', 64)] + $data));

        try {
            $this->service->restore($file);
            $this->fail('Damaged backup restored');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
        }

        $this->assertCount(1, $this->service->list(), 'no safety backup before the checks pass');

        file_put_contents($sidecar, json_encode(['app_version' => '9999-12-31-999999'] + $data));
        $this->assertFalse($this->service->list()[0]['restorable']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('newer version');
        $this->service->restore($file);
    }

    public function testOnlyAdminsManageBackups(): void
    {
        $users = new \CodeIgniter\Shield\Models\UserModel();

        $this->assertTrue($users->findById($this->createUser('admin'))->can('backups.manage', 'backups.restore'));

        foreach (['sales', 'finance', 'warehouse'] as $group) {
            $user = $users->findById($this->createUser($group));
            $this->assertFalse($user->can('backups.manage'), $group);
            $this->assertFalse($user->can('backups.restore'), $group);
        }
    }

    public function testFileNamesAreChecked(): void
    {
        foreach (['../.env', 'backup-1.tar.gz', 'backup-20261123-000000.tar.gz', '..\\backup-20261123-000000.tar.gz'] as $name) {
            try {
                $this->service->path($name);
                $this->fail("Accepted {$name}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPruneKeepsTheNewestScheduledBackups(): void
    {
        mkdir($this->config->path, 0775, true);

        for ($day = 1; $day <= 10; $day++) {
            $base = $this->config->path . sprintf('backup-202601%02d-003000', $day);
            file_put_contents($base . '.tar.gz', 'x');
            file_put_contents($base . '.json', json_encode(['format' => 1, 'trigger' => 'scheduled', 'app_version' => '']));
            touch($base . '.tar.gz', strtotime('-60 days'));
        }

        file_put_contents($this->config->path . 'backup-20260101-120000-manual.tar.gz', 'x');
        file_put_contents($this->config->path . 'backup-20260101-120000-manual.json', json_encode(['format' => 1, 'trigger' => 'manual']));
        touch($this->config->path . 'backup-20260101-120000-manual.tar.gz', strtotime('-60 days'));

        $removed = $this->service->prune();

        $this->assertSame(['backup-20260103-003000.tar.gz', 'backup-20260102-003000.tar.gz', 'backup-20260101-003000.tar.gz'], $removed);
        $this->assertCount(8, $this->service->list(), '7 scheduled + the manual one');
    }

    /**
     * CHECKSUM TABLE of every table except audit_log (backups and restores write audit rows).
     *
     * @return array<string, string>
     */
    private function checksums(): array
    {
        $db     = db_connect();
        $tables = array_map(static fn (array $r): string => (string) reset($r), $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray());
        $tables = array_values(array_diff($tables, ['audit_log']));
        sort($tables);
        $result = [];

        foreach ($db->query('CHECKSUM TABLE ' . implode(', ', array_map(static fn (string $t): string => "`{$t}`", $tables)))->getResultArray() as $row) {
            $result[$row['Table']] = (string) $row['Checksum'];
        }

        return $result;
    }

    /**
     * Remove a folder recursively.
     *
     * @param string $dir Folder.
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
}
