<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Pdf;
use App\Libraries\PdfMerge;
use App\Models\DeliveryNoteModel;
use App\Models\PaymentModel;
use RuntimeException;
use Throwable;

/**
 * Combined order report: one PDF with a cover + index, the chosen printed documents of a finished order (penawaran,
 * SPK, invoice, kwitansi, surat jalan, BAST) and their PDF / image attachments, in order-flow order.
 * Built on demand (not stored); the school uses it for its accountability report (SPJ).
 */
final class OrderBundle
{
    /** Printed document kinds (OrderFiles keys "kind:id") => PrintDocuments method. */
    public const KINDS = [
        'proposal'  => 'proposal',
        'agreement' => 'agreement',
        'invoice'   => 'invoice',
        'receipt'   => 'receipt',
        'delivery'  => 'deliveryNote',
        'handover'  => 'handover',
        'timeline'  => 'orderTimeline', // "Riwayat pesanan" appendix (OrderTimeline)
    ];

    /** Attachment entity type => the printed document kind it is listed under. */
    private const PARENT = ['proposal' => 'proposal', 'invoice' => 'invoice', 'payment' => 'receipt', 'delivery_note' => 'delivery'];

    /** Attachment MIME types that can be merged. */
    public const MERGEABLE = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private AccessScope $scope;

    /**
     * @param AccessScope $scope Access scope of the current user.
     */
    public function __construct(AccessScope $scope)
    {
        $this->scope = $scope;
    }

    /**
     * Pickable parts of the order, in order-flow order (each document followed by its attachments).
     *
     * @param int $proposalId Proposal id (visibility must be checked by the caller, e.g. guardProposal()).
     *
     * @return array{complete: bool, items: list<array{key: string, group: string, label: string, type: string, checked: bool, disabled: bool, icon: string}>}
     */
    public function options(int $proposalId): array
    {
        $flow  = (new OrderFlow())->forProposal($proposalId);
        $files = $flow['files'];

        $byParent = [];
        $orphans  = [];
        $docKeys  = array_column($files['prints'], 'key');
        $attachments = $files['attachments'];
        usort($attachments, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        foreach ($attachments as $a) {
            if (! (new AttachmentService())->canSee($this->scope, (string) $a['entity_type'], (int) $a['entity_id'])) {
                continue;
            }

            $parent = (self::PARENT[$a['entity_type']] ?? '') . ':' . (int) $a['entity_id'];
            $item   = [
                'key'      => 'att:' . (int) $a['id'],
                'label'    => (string) $a['file_name'],
                'type'     => 'attachment',
                'checked'  => in_array($a['mime_type'], self::MERGEABLE, true),
                'disabled' => ! in_array($a['mime_type'], self::MERGEABLE, true),
                'icon'     => AttachmentService::icon((string) $a['file_name']),
            ];

            if (in_array($parent, $docKeys, true)) {
                $byParent[$parent][] = $item;
            } else {
                $orphans[] = $item + ['group' => (string) $a['entity_label']];
            }
        }

        $items = [];

        foreach ($files['prints'] as $p) {
            $items[] = [
                'key'      => $p['key'],
                'group'    => $p['group'],
                'label'    => $p['label'],
                'type'     => 'document',
                'checked'  => ! $p['muted'],
                'disabled' => false,
                'icon'     => $p['icon'],
            ];

            foreach ($byParent[$p['key']] ?? [] as $a) {
                $items[] = $a + ['group' => $p['group']];
            }
        }

        foreach ($orphans as $a) {
            $items[] = $a;
        }

        $items[] = [
            'key'      => 'timeline:' . $proposalId,
            'group'    => lang('Bundle.appendix'),
            'label'    => lang('Bundle.timeline'),
            'type'     => 'document',
            'checked'  => true,
            'disabled' => false,
            'icon'     => 'clock-history',
        ];

        return ['complete' => OrderFlow::isComplete($flow), 'items' => $items];
    }

    /**
     * Only the keys that are valid, enabled parts of this order, in order-flow order.
     *
     * @param list<array{key: string, disabled: bool}> $items Options (see options()).
     * @param list<mixed>                              $keys  Posted keys.
     *
     * @return list<string>
     */
    public static function validKeys(array $items, array $keys): array
    {
        $wanted = array_flip(array_map('strval', array_filter($keys, 'is_scalar')));
        $valid  = [];

        foreach ($items as $item) {
            if (! $item['disabled'] && isset($wanted[$item['key']])) {
                $valid[] = $item['key'];
            }
        }

        return $valid;
    }

    /**
     * Build the combined PDF.
     *
     * @param int          $proposalId Proposal id (visibility checked by the caller).
     * @param list<string> $keys       Selected parts ("proposal:5", "att:12", ...).
     *
     * @return array{bytes: string, filename: string}
     *
     * @throws RuntimeException When the order is not finished or nothing valid was selected.
     */
    public function build(int $proposalId, array $keys): array
    {
        $options = $this->options($proposalId);

        if (! $options['complete']) {
            throw new RuntimeException(lang('Bundle.notComplete'));
        }

        $selected = self::validKeys($options['items'], $keys);

        if ($selected === []) {
            throw new RuntimeException(lang('Bundle.nothingSelected'));
        }

        @set_time_limit(120);
        // Own folder per run directly in writable/cache (no shared subfolder whose owner could differ between the CLI and the web server).
        $dir = WRITEPATH . 'cache/bundle-' . bin2hex(random_bytes(8));

        if (! @mkdir($dir, 0775) && ! is_dir($dir)) {
            throw new \RuntimeException('Cannot create the temp folder ' . $dir . ' (check the owner of writable/cache).');
        }

        try {
            $byKey = array_column($options['items'], null, 'key');
            $parts = [];

            foreach ($selected as $i => $key) {
                $parts[] = $this->preparePart($byKey[$key], $dir . '/' . $i);
            }

            $result = $this->merge($proposalId, $parts, $dir);

            // Audit trail: who made which report (also listed in the order timeline).
            AuditLogger::log('order_bundle', $proposalId, AuditLogger::CREATE, null, ['parts' => implode(',', $selected), 'pages' => $result['pages']]);

            return ['bytes' => $result['bytes'], 'filename' => $result['filename']];
        } finally {
            self::removeDir($dir);
        }
    }

    /**
     * Render or locate one part and count its pages.
     *
     * @param array<string, mixed> $item Option item.
     * @param string               $base Temp file path without extension.
     *
     * @return array{item: array<string, mixed>, kind: string, path: ?string, pages: int, merged: bool}
     */
    private function preparePart(array $item, string $base): array
    {
        [$kind, $id] = explode(':', $item['key'], 2);
        $id = (int) $id;

        if ($kind !== 'att') {
            $method = self::KINDS[$kind];
            $doc    = (new PrintDocuments())->{$method}($id);
            // Without its own page numbers: the report numbers all pages (PdfMerge::setStamp()).
            file_put_contents($base . '.pdf', Pdf::render($doc['view'], $doc['data'], $doc['footer'], false));

            return ['item' => $item, 'kind' => 'pdf', 'path' => $base . '.pdf', 'pages' => PdfMerge::pageCount($base . '.pdf') ?? 1, 'merged' => true];
        }

        $attachment = (new \App\Models\AttachmentModel())->find($id);
        $path       = $attachment !== null ? AttachmentService::absolutePath($attachment) : null;
        $mime       = (string) ($attachment['mime_type'] ?? '');
        $notice     = ['item' => $item, 'kind' => 'notice', 'path' => null, 'pages' => 1, 'merged' => false];

        if ($path === null) {
            return $notice;
        }

        if ($mime === 'application/pdf') {
            $pages = PdfMerge::pageCount($path);

            return $pages === null ? $notice : ['item' => $item, 'kind' => 'pdf', 'path' => $path, 'pages' => $pages, 'merged' => true];
        }

        if ($mime === 'image/jpeg') {
            return ['item' => $item, 'kind' => 'image', 'path' => $path, 'pages' => 1, 'merged' => true];
        }

        // PNG (may be interlaced), WEBP, GIF: re-encode as plain PNG for FPDF.
        return PdfMerge::toPng($path, $base . '.png')
            ? ['item' => $item, 'kind' => 'image', 'path' => $base . '.png', 'pages' => 1, 'merged' => true]
            : $notice;
    }

    /**
     * Cover + index (two passes for the page numbers), then every part, stamped.
     *
     * @param int                              $proposalId Proposal id.
     * @param list<array<string, mixed>>       $parts      Prepared parts.
     * @param string                           $dir        Temp directory.
     *
     * @return array{bytes: string, filename: string, pages: int}
     */
    private function merge(int $proposalId, array $parts, string $dir): array
    {
        $invoice = $this->paidInvoice($proposalId);
        $dates   = $invoice !== null ? $this->dates((int) $invoice['id']) : ['paid' => null, 'delivered' => null];
        $printer = new PrintDocuments();
        $times   = (new LeadTime())->timestamps([$proposalId])[$proposalId] ?? [];
        $lead    = LeadTime::durations($times);
        $goods   = (bool) ($times['has_goods'] ?? false);

        $coverPages = 1;
        $cover      = null;

        for ($pass = 0; $pass < 3; $pass++) {
            $page = $coverPages + 1;
            $rows = [];

            foreach ($parts as $part) {
                $rows[] = [
                    'title'  => $part['item']['label'],
                    'group'  => $part['item']['group'],
                    'type'   => $part['item']['type'],
                    'page'   => $page,
                    'pages'  => $part['pages'],
                    'merged' => $part['merged'],
                ];
                $page += $part['pages'];
            }

            $cover = $printer->bundleCover($proposalId, $invoice, $rows, $dates, $lead, $goods);
            file_put_contents($dir . '/cover.pdf', Pdf::render($cover['view'], $cover['data'], $cover['footer'], false));
            $actual = PdfMerge::pageCount($dir . '/cover.pdf') ?? 1;

            if ($actual === $coverPages) {
                break;
            }

            $coverPages = $actual;
        }

        $total  = $coverPages + array_sum(array_column($parts, 'pages'));
        $number = (string) $cover['data']['number'];
        $merge  = (new PdfMerge())->setStamp('Laporan ' . $number, $total);
        $merge->addPdf($dir . '/cover.pdf');

        foreach ($parts as $part) {
            try {
                match ($part['kind']) {
                    'pdf'   => $merge->addPdf($part['path']),
                    'image' => $merge->addImage($part['path']),
                    default => $merge->addNotice($part['item']['label'], 'Lampiran ini tidak dapat digabungkan ke laporan. Unduh file aslinya dari aplikasi.'),
                };
            } catch (Throwable $e) {
                log_message('error', 'Order bundle part {key} failed: {message}', ['key' => $part['item']['key'], 'message' => $e->getMessage()]);
                $merge->addNotice($part['item']['label'], 'Lampiran ini tidak dapat digabungkan ke laporan. Unduh file aslinya dari aplikasi.');
            }
        }

        return ['bytes' => $merge->output(), 'filename' => 'Laporan ' . $number, 'pages' => $total];
    }

    /**
     * The paid (latest non-void) invoice of the proposal, with details.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array<string, mixed>|null
     */
    private function paidInvoice(int $proposalId): ?array
    {
        $invoices = (new \App\Models\InvoiceModel())->withDetails()->where('invoice.proposal_id', $proposalId)->orderBy('invoice.id')->findAll();
        $active   = null;

        foreach ($invoices as $inv) {
            if ($inv['status_code'] !== InvoiceWorkflow::VOID) {
                $active = $inv;
            }
        }

        return $active;
    }

    /**
     * Date of the last (non-reversed) payment and of the last delivery.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return array{paid: ?string, delivered: ?string}
     */
    private function dates(int $invoiceId): array
    {
        $paid = (new PaymentModel())->where('invoice_id', $invoiceId)->where('reversed_at', null)->selectMax('payment_date')->first();
        $done = (new DeliveryNoteModel())->where('invoice_id', $invoiceId)->where('cancelled_at', null)->selectMax('delivered_at')->first();

        return [
            'paid'      => $paid['payment_date'] ?? null,
            'delivered' => isset($done['delivered_at']) ? substr((string) $done['delivered_at'], 0, 10) : null,
        ];
    }

    /**
     * Remove a temp directory with its files.
     *
     * @param string $dir Absolute path.
     *
     * @return void
     */
    private static function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
