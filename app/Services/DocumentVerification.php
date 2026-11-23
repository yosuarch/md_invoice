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
 *
 * Every document also has a short code printed under the QR (8 characters, Crockford Base32, shown as "K7Q2-M9XD"):
 * typed on the public page GET verify, for people who cannot scan. Unique, no I / L / O / U, input is forgiving
 * (case, spaces, dashes; O → 0, I / L → 1).
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

    /** Crockford Base32 alphabet (no I, L, O, U). */
    public const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Length of a verification code. */
    public const CODE_LENGTH = 8;

    /**
     * Random verification code (not checked for uniqueness, see newCode()).
     *
     * @return string 8 characters of CODE_ALPHABET.
     */
    public static function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, 31)];
        }

        return $code;
    }

    /**
     * Random code that is not used yet.
     *
     * @return string
     */
    public static function newCode(): string
    {
        $table = db_connect()->table('document_verification');

        do {
            $code = self::randomCode();
        } while ($table->where('code', $code)->countAllResults() > 0);

        return $code;
    }

    /**
     * Code as typed by a person → stored form, or null when it cannot be a code.
     * Ignores case, spaces and dashes; reads O as 0 and I / L as 1.
     *
     * @param string $input Typed code, e.g. "k7q2 m9xd".
     *
     * @return string|null
     */
    public static function normalizeCode(string $input): ?string
    {
        $code = strtr(strtoupper((string) preg_replace('/[\s\-_.]+/', '', $input)), ['O' => '0', 'I' => '1', 'L' => '1']);

        return strlen($code) === self::CODE_LENGTH && strspn($code, self::CODE_ALPHABET) === self::CODE_LENGTH ? $code : null;
    }

    /**
     * Code for display: "K7Q2-M9XD".
     *
     * @param string $code Stored code.
     *
     * @return string
     */
    public static function formatCode(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /**
     * Verification code of a document (created with the token).
     *
     * @param string $type  TYPES key.
     * @param int    $docId Document id.
     *
     * @return string Stored code (8 characters).
     */
    public function codeFor(string $type, int $docId): string
    {
        $this->tokenFor($type, $docId);

        return (string) db_connect()->table('document_verification')->where(['doc_type' => $type, 'doc_id' => $docId])->get()->getRowArray()['code'];
    }

    /**
     * Document of a typed code.
     *
     * @param string $input Code as typed.
     *
     * @return array{doc_type: string, doc_id: int}|null
     */
    public function resolveCode(string $input): ?array
    {
        $code = self::normalizeCode($input);
        $row  = $code === null ? null : db_connect()->table('document_verification')->where('code', $code)->get()->getRowArray();

        return $row === null ? null : ['doc_type' => $row['doc_type'], 'doc_id' => (int) $row['doc_id']];
    }

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

        if ($row !== null && ! empty($row['code'])) {
            return $row['token'];
        }

        if ($row !== null) {
            // Row from before the codes (should not happen after the migration backfill).
            $db->table('document_verification')->where('id', $row['id'])->where('code', null)->update(['code' => self::newCode()]);

            return $row['token'];
        }

        $token = bin2hex(random_bytes(16));
        $db->query(
            'INSERT IGNORE INTO document_verification (doc_type, doc_id, token, code, created_at) VALUES (?, ?, ?, ?, ?)',
            [$type, $docId, $token, self::newCode(), date('Y-m-d H:i:s')],
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
