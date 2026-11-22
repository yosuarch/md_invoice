<?php

/**
 * Label kode tersimpan (status, jenis), dibaca dengan code_label('group', 'CODE').
 */
return [
    // Proposal statuses
    'proposalStatus_DRAFT'            => 'Draf',
    'proposalStatus_PROPOSE'          => 'Terkirim',
    'proposalStatus_UNDER_REVIEW'     => 'Negosiasi',
    'proposalStatus_STORE_APPROVE'    => 'Disetujui',
    'proposalStatus_CANCEL_BY_CLIENT' => 'Dibatalkan pelanggan',
    'proposalStatus_CANCEL_BY_STORE'  => 'Dibatalkan toko',

    // Proposal actions (from_to)
    'proposalAction_DRAFT_PROPOSE'                 => 'Tandai terkirim',
    'proposalAction_DRAFT_PROPOSE_help'            => 'Penawaran sudah dikirim ke pelanggan. Penawaran tidak dapat diubah sampai negosiasi dimulai.',
    'proposalAction_DRAFT_CANCEL_BY_STORE'         => 'Batalkan',
    'proposalAction_DRAFT_CANCEL_BY_STORE_help'    => 'Batalkan draf ini. Dapat dibuka kembali nanti.',
    'proposalAction_PROPOSE_UNDER_REVIEW'          => 'Pelanggan minta perubahan',
    'proposalAction_PROPOSE_UNDER_REVIEW_note'     => 'Apa yang diminta pelanggan?',
    'proposalAction_PROPOSE_UNDER_REVIEW_help'     => 'Catat permintaan pelanggan (mis. harga lebih rendah, jumlah lain). Penawaran dapat diubah; kirim versi revisi setelah siap.',
    'proposalAction_PROPOSE_STORE_APPROVE'         => 'Setujui',
    'proposalAction_PROPOSE_STORE_APPROVE_help'    => 'Setujui penawaran. Penawaran dikunci dan dapat dibuatkan invoice.',
    'proposalAction_PROPOSE_CANCEL_BY_CLIENT'      => 'Ditolak pelanggan',
    'proposalAction_PROPOSE_CANCEL_BY_CLIENT_help' => 'Pelanggan menolak atau membatalkan penawaran.',
    'proposalAction_PROPOSE_CANCEL_BY_STORE'       => 'Batalkan',
    'proposalAction_PROPOSE_CANCEL_BY_STORE_help'  => 'Kami menarik penawaran ini.',
    'proposalAction_UNDER_REVIEW_PROPOSE'          => 'Kirim revisi',
    'proposalAction_UNDER_REVIEW_PROPOSE_note'     => 'Apa yang berubah? (opsional)',
    'proposalAction_UNDER_REVIEW_PROPOSE_help'     => 'Penawaran revisi sudah dikirim ke pelanggan. Disimpan sebagai revisi baru dan dikunci kembali.',
    'proposalAction_UNDER_REVIEW_STORE_APPROVE'    => 'Setujui',
    'proposalAction_UNDER_REVIEW_STORE_APPROVE_help' => 'Setujui penawaran. Penawaran dikunci dan dapat dibuatkan invoice.',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_CLIENT' => 'Ditolak pelanggan',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_CLIENT_help' => 'Pelanggan menolak atau membatalkan penawaran.',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_STORE'  => 'Batalkan',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_STORE_help' => 'Kami menarik penawaran ini.',
    'proposalAction_STORE_APPROVE_UNDER_REVIEW'    => 'Cabut persetujuan',
    'proposalAction_STORE_APPROVE_UNDER_REVIEW_help' => 'Kembali ke negosiasi. Hanya bisa selama belum ada invoice dari penawaran ini.',
    'proposalAction_CANCEL_BY_CLIENT_DRAFT'        => 'Buka kembali sebagai draf',
    'proposalAction_CANCEL_BY_CLIENT_DRAFT_help'   => 'Lanjutkan pengerjaan penawaran ini.',
    'proposalAction_CANCEL_BY_STORE_DRAFT'         => 'Buka kembali sebagai draf',
    'proposalAction_CANCEL_BY_STORE_DRAFT_help'    => 'Lanjutkan pengerjaan penawaran ini.',

    // Invoice statuses
    'invoiceStatus_DRAFT'          => 'Draf',
    'invoiceStatus_ISSUED'         => 'Terbit',
    'invoiceStatus_PARTIALLY_PAID' => 'Dibayar sebagian',
    'invoiceStatus_PAID'           => 'Lunas',
    'invoiceStatus_OVERDUE'        => 'Jatuh tempo',
    'invoiceStatus_VOID'           => 'Dibatalkan (void)',
    'invoiceStatus_CANCELLED'      => 'Batal',

    // Delivery note statuses and tracking events
    'deliveryStatus_PREPARING' => 'Disiapkan',
    'deliveryStatus_SHIPPED'   => 'Dalam perjalanan',
    'deliveryStatus_DELIVERED' => 'Diterima',
    'deliveryStatus_CANCELLED' => 'Batal',
    'deliveryEvent_CREATED'    => 'Surat jalan dibuat',
    'deliveryEvent_SHIPPED'    => 'Dikirim',
    'deliveryEvent_UPDATE'     => 'Pembaruan pelacakan',
    'deliveryEvent_DELIVERED'  => 'Diterima',
    'deliveryEvent_CANCELLED'  => 'Batal',

    // Customers
    'schoolStatus_PUBLIC'  => 'Sekolah negeri',
    'schoolStatus_PRIVATE' => 'Sekolah swasta',
    'schoolStatusShort_PUBLIC'  => 'Negeri',
    'schoolStatusShort_PRIVATE' => 'Swasta',
    'addressType_BILLING'  => 'Penagihan',
    'addressType_SHIPPING' => 'Pengiriman',

    // Credit notes
    'reasonType_RETURN'           => 'Barang dikembalikan',
    'reasonType_PRICE_CORRECTION' => 'Koreksi harga',
    'reasonType_CANCEL_BY_CLIENT' => 'Dibatalkan pelanggan',
    'reasonType_CANCEL_BY_STORE'  => 'Dibatalkan toko',
    'reasonType_OTHER'            => 'Lainnya',

    // Withholding kinds
    'taxKind_PPN'       => 'PPN dipungut',
    'taxKind_PPH22'     => 'PPh 22',
    'taxKind_PPH23'     => 'PPh 23',
    'taxKind_PPH_FINAL' => 'PPh final UMKM',

    // Attachments ("belongs to")
    'attachmentType_proposal'      => 'Penawaran',
    'attachmentType_invoice'       => 'Invoice',
    'attachmentType_payment'       => 'Pembayaran',
    'attachmentType_delivery_note' => 'Surat jalan',

    // Reports
    'bucket_current' => 'Belum jatuh tempo',
    'bucket_d1_30'   => '1–30 hari',
    'bucket_d31_60'  => '31–60 hari',
    'bucket_d61_90'  => '61–90 hari',
    'bucket_d90'     => '> 90 hari',
    'reconState_ok'            => 'Dipotong, bukti tercatat',
    'reconState_missing_proof' => 'Dipotong, nomor bukti belum ada',
    'reconState_difference'    => 'Jumlah berbeda',
    'reconState_not_withheld'  => 'Belum dipotong',
    'reconState_unexpected'    => 'Dipotong tetapi tidak diharapkan',

    // Document numbering types
    'docType_QUO' => 'Surat Penawaran Harga',
    'docType_SPK' => 'Surat Perintah Kerja (SPK)',
    'docType_INV' => 'Invoice',
    'docType_KW' => 'Kwitansi',
    'docType_CN' => 'Nota kredit',
    'docType_DO' => 'Surat jalan',
    'docType_BAST' => 'Berita Acara Serah Terima (BAST)',
    'docType_SKU' => 'SKU produk',

    // User groups (Config\AuthGroups)
    'group_admin' => 'Admin',
    'groupDesc_admin' => 'Akses penuh: pengaturan, pengguna, persetujuan, void.',
    'group_sales' => 'Sales',
    'groupDesc_sales' => 'Penawaran untuk sekolahnya sendiri; melihat invoice dan surat jalannya.',
    'group_finance' => 'Keuangan',
    'groupDesc_finance' => 'Invoice, pembayaran dan nota kredit.',
    'group_warehouse' => 'Gudang',
    'groupDesc_warehouse' => 'Surat jalan: buat, kirim, lacak, terima dan batalkan (semua sekolah).',

    // Contact roles (contact_role)
    'contactRole_HEADMASTER' => 'Kepala sekolah',
    'contactRole_TREASURER' => 'Bendahara BOS',
    'contactRole_OPERATOR' => 'Operator sekolah',
    'contactRole_COMMITTEE' => 'Komite sekolah',
    'contactRole_FOUNDATION' => 'Yayasan',
    'contactRole_OTHER' => 'Lainnya',
];
