<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/**
 * Indonesian administrative regions (level 1 provinces, level 2 regencies/cities, level 3 districts / kecamatan)
 * from data/regions.csv.
 *
 * Source: Kepmendagri No 300.2.2-2138 Tahun 2025 via github.com/cahyadsn/wilayah (MIT License, (c) cahya dsn),
 * extracted 2026-11-22 (dataset edit 2026-02-13): 38 provinces, 514 regencies/cities, 7,265 districts (kecamatan). Upsert by code, so it is safe to run again
 * after replacing the CSV with a newer Kepmendagri release.
 */
class RegionSeeder extends Seeder
{
    /**
     * Insert new regions and update names of existing codes.
     *
     * @return void
     *
     * @throws RuntimeException When the CSV is missing.
     */
    public function run(): void
    {
        $file = __DIR__ . '/data/regions.csv';
        $fh   = fopen($file, 'rb') ?: throw new RuntimeException("Cannot read {$file}");
        $now  = date('Y-m-d H:i:s');

        fgetcsv($fh, null, ',', '"', ''); // header

        $this->db->transException(true)->transStart();

        try {
            while (($row = fgetcsv($fh, null, ',', '"', '')) !== false) {
                [$code, $parent, $name] = $row;

                $this->db->query(
                    'INSERT INTO region (code, parent_code, name, level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
                    . ' ON DUPLICATE KEY UPDATE name = VALUES(name), parent_code = VALUES(parent_code), updated_at = IF(name = VALUES(name), updated_at, VALUES(updated_at))',
                    [$code, $parent === '' ? null : $parent, $name, substr_count($code, '.') + 1, $now, $now],
                );
            }

            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        } finally {
            fclose($fh);
        }
    }
}
