<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;
use App\Services\DocumentVerification;

/**
 * document_verification.code: short, human-readable verification code printed under the QR code
 * (8 characters Crockford Base32, unique), typed on the public page GET verify. Existing rows get a code.
 */
class AddVerificationCode extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('document_verification', [
            'code' => ['type' => 'CHAR', 'constraint' => 8, 'null' => true, 'after' => 'token'],
        ]);

        $table = $this->db->table('document_verification');
        $used  = [];

        foreach ($table->select('id')->where('code', null)->get()->getResultArray() as $row) {
            do {
                $code = DocumentVerification::randomCode();
            } while (isset($used[$code]));

            $used[$code] = true;
            $this->db->table('document_verification')->where('id', $row['id'])->update(['code' => $code]);
        }

        $this->db->query('ALTER TABLE document_verification ADD UNIQUE KEY document_verification_code (code)');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if ($this->indexExists('document_verification', 'document_verification_code')) {
            $this->db->query('ALTER TABLE document_verification DROP INDEX document_verification_code');
        }

        $this->forge->dropColumn('document_verification', 'code');
    }
}
