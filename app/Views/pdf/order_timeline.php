<?php
/**
 * "Riwayat pesanan": readable history of an order (App\Services\OrderTimeline), appendix of the combined report.
 * Printed in Indonesian; event codes are labelled here.
 *
 * @var array<string, mixed>                                              $proposal Proposal with details.
 * @var list<array{at: string, code: string, detail: string, user: ?string}> $events
 * @var string|null                                                        $logo
 * @var string                                                             $number   Proposal number.
 */
$labels = [
    'PROPOSAL_CREATED'          => 'Penawaran dibuat',
    'STATUS_DRAFT'              => 'Kembali ke draf',
    'STATUS_PROPOSE'            => 'Penawaran dikirim',
    'STATUS_UNDER_REVIEW'       => 'Permintaan perubahan dari pelanggan',
    'STATUS_STORE_APPROVE'      => 'Penawaran disetujui',
    'STATUS_CANCEL_BY_CLIENT'   => 'Dibatalkan oleh pelanggan',
    'STATUS_CANCEL_BY_STORE'    => 'Dibatalkan oleh toko',
    'REVISION'                  => 'Revisi penawaran dicatat',
    'AGREEMENT'                 => 'Surat Perintah Kerja dibuat',
    'INVOICE_CREATED'           => 'Draf invoice dibuat',
    'INVOICE_ISSUED'            => 'Invoice diterbitkan',
    'INVOICE_VOIDED'            => 'Invoice dibatalkan (void)',
    'PAYMENT'                   => 'Pembayaran dicatat',
    'PAYMENT_REVERSED'          => 'Pembayaran dibatalkan',
    'REFUND'                    => 'Pengembalian dana',
    'REFUND_REVERSED'           => 'Pengembalian dana dibatalkan',
    'CREDIT_NOTE'               => 'Nota kredit',
    'DELIVERY_CREATED'          => 'Surat jalan dibuat',
    'DELIVERY_SHIPPED'          => 'Barang dikirim',
    'DELIVERY_UPDATE'           => 'Info pengiriman',
    'DELIVERY_DELIVERED'        => 'Barang diterima',
    'DELIVERY_CANCELLED'        => 'Surat jalan dibatalkan',
    'ATTACHMENT'                => 'Lampiran diunggah',
    'BUNDLE'                    => 'Laporan gabungan dibuat',
];
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Riwayat pesanan <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'RIWAYAT PESANAN', 'meta' => [
    'No. Penawaran' => $number,
    'Dicetak'       => format_date_id(date('Y-m-d')),
]]) ?>

<p class="small muted" style="margin-top: 10pt">
    Catatan otomatis dari aplikasi: setiap langkah pesanan dengan waktu dan petugas yang mencatatnya.
</p>

<table class="items">
    <thead>
        <tr>
            <th style="width: 17%">Waktu</th>
            <th style="width: 27%">Kejadian</th>
            <th>Keterangan</th>
            <th style="width: 17%">Petugas</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($events === []) : ?>
            <tr class="empty"><td colspan="4">Belum ada catatan.</td></tr>
        <?php endif ?>
        <?php foreach ($events as $e) : ?>
            <tr>
                <td class="nowrap small"><?= esc(date('d/m/Y H:i', strtotime($e['at']))) ?></td>
                <td><?= esc($labels[$e['code']] ?? $e['code']) ?></td>
                <td class="small"><?= esc($e['detail']) ?></td>
                <td class="small"><?= esc($e['user'] ?? '-') ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>

<?= $this->endSection() ?>
