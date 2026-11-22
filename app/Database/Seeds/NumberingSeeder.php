<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Documents;

/**
 * Initial document number formats (Config\Documents::$sequences). Existing rows are kept (edited in Settings).
 */
class NumberingSeeder extends Seeder
{
    /**
     * @return void
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (config(Documents::class)->sequences as $type => $def) {
            $this->db->table('document_numbering')->ignore(true)->insert([
                'doc_type'   => $type,
                'pattern'    => $def['pattern'],
                'period'     => $def['period'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
