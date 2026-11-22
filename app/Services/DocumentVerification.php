<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * "Scan to verify" for printed documents: every document gets a random token (document_verification); the QR code
 * on the PDF opens the public page verify/{token}, which shows the document's key data from the database, so a
 * school can check that a printed or forwarded document is genuine and still valid.
 */
final class DocumentVerification
{
    /**
     * Document types and their labels on the verification page.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'QUO'  => 'Surat Penawaran Harga',
        'SPK'  => 'Surat Perintah Kerja',
        'INV'  => 'Invoice',
        'KW'   => 'Kwitansi',
        'DO'   => 'Surat Jalan',
        'BAST' => 'Berita Acara Serah Terima',
    ];

    /**
     * Token for a document (created on first use; stable afterwards).
     *
     * @param string $type  TYPES key.
     * @param int    $docId Id of the document row (BAST uses the delivery note id).
     *
     * @return string 32 hex characters.
     */
    public function tokenFor(string $type, int $docId): string
    {
        $db  = db_connect();
        $row = $db->table('document_verification')->where(['doc_type' => $type, 'doc_id' => $docId])->get()->getRowArray();

        if ($row !== null) {
            return $row['token'];
        }

        $token = bin2hex(random_bytes(16));
        $db->query(
            'INSERT IGNORE INTO document_verification (doc_type, doc_id, token, created_at) VALUES (?, ?, ?, ?)',
            [$type, $docId, $token, date('Y-m-d H:i:s')],
        );

        // A parallel request may have won the INSERT: read back what is stored.
        return $db->table('document_verification')->where(['doc_type' => $type, 'doc_id' => $docId])->get()->getRowArray()['token'];
    }

    /**
     * Document of a token.
     *
     * @param string $token Token from the URL.
     *
     * @return array{doc_type: string, doc_id: int}|null
     */
    public function resolve(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return null;
        }

        $row = db_connect()->table('document_verification')->where('token', $token)->get()->getRowArray();

        return $row === null ? null : ['doc_type' => $row['doc_type'], 'doc_id' => (int) $row['doc_id']];
    }

    /**
     * Public verification URL of a document.
     *
     * @param string $type  TYPES key.
     * @param int    $docId Document id.
     *
     * @return string
     */
    public function url(string $type, int $docId): string
    {
        return site_url('verify/' . $this->tokenFor($type, $docId));
    }

    /**
     * QR code image (PNG data URI) for a text/URL.
     *
     * @param string $data Encoded text.
     *
     * @return string data:image/png;base64,...
     */
    public static function qrDataUri(string $data): string
    {
        $options = new QROptions([
            'outputType'   => QROutputInterface::GDIMAGE_PNG,
            'scale'        => 5,
            'quietzoneSize' => 1,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }
}
