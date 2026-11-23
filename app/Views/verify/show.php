<?php
/**
 * Public document verification page (Indonesian, like the printed documents).
 *
 * @var array{type: string, rows: array<string, string>, valid: bool, status: string}|null $summary
 * @var bool        $formOnly Only the code form (GET verify without a code).
 * @var string|null $code     Code typed by the visitor (null when opened from the QR code).
 * @var string      $company Store name.
 * @var string|null $logo    Store logo data URI.
 */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verifikasi dokumen | <?= esc($company) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon-64.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css?v=5.3.3') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css?v=1.11.3') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
</head>
<body class="auth-area d-flex flex-column min-vh-100">
<main class="container py-5 flex-grow-1" style="max-width: 560px">
    <div class="text-center mb-3">
        <?php if ($logo !== null) : ?><img src="<?= $logo ?>" alt="<?= esc($company, 'attr') ?>" style="width: 96px; height: 96px"><?php endif ?>
        <h1 class="h5 mt-2 mb-0"><?= esc($company) ?></h1>
        <div class="small text-body-secondary">Verifikasi keaslian dokumen</div>
    </div>

    <?php $formOnly ??= false; $code ??= null; ?>
    <?php if ($formOnly || $code !== null) : ?>
        <form method="get" action="<?= site_url('verify') ?>" class="card shadow-sm mb-3">
            <div class="card-body">
                <label for="code" class="form-label fw-semibold">Kode verifikasi</label>
                <div class="input-group input-group-lg">
                    <input type="text" class="form-control font-monospace text-uppercase" id="code" name="code" value="<?= esc((string) $code, 'attr') ?>"
                           placeholder="XXXX-XXXX" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" required<?= $formOnly ? ' autofocus' : '' ?>>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i><span class="visually-hidden">Periksa</span></button>
                </div>
                <div class="form-text">Kode 8 karakter yang tercetak di bawah kode QR pada dokumen, misalnya <span class="font-monospace text-nowrap">K7Q2-M9XD</span>.</div>
            </div>
        </form>
    <?php endif ?>

    <?php if (! $formOnly) : ?>
    <div class="card shadow-sm">
        <?php if ($summary === null) : ?>
            <div class="card-body text-center py-4">
                <i class="bi bi-x-octagon text-danger fs-1"></i>
                <h2 class="h5 mt-2">Dokumen tidak ditemukan</h2>
                <p class="text-body-secondary mb-0">Kode verifikasi ini tidak terdaftar. Dokumen mungkin tidak asli. Hubungi <?= esc($company) ?> untuk konfirmasi.</p>
            </div>
        <?php else : ?>
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= esc($summary['type']) ?></span>
                <span class="badge <?= $summary['valid'] ? 'bg-success' : 'bg-danger' ?>"><?= esc($summary['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="alert <?= $summary['valid'] ? 'alert-success' : 'alert-danger' ?> small py-2">
                    <i class="bi bi-<?= $summary['valid'] ? 'patch-check' : 'exclamation-triangle' ?> me-1"></i>
                    <?= $summary['valid']
                        ? 'Dokumen ini asli dan tercatat di sistem ' . esc($company) . '.'
                        : 'Dokumen ini tercatat, tetapi sudah DIBATALKAN dan tidak berlaku.' ?>
                </div>
                <dl class="row mb-0 small">
                    <?php foreach ($summary['rows'] as $label => $value) : ?>
                        <dt class="col-5 text-body-secondary fw-normal"><?= esc($label) ?></dt>
                        <dd class="col-7"><?= esc($value) ?></dd>
                    <?php endforeach ?>
                </dl>
            </div>
            <div class="card-footer bg-white small text-body-secondary">Data di atas diambil langsung dari sistem pada <?= esc(format_date_id(date('Y-m-d'))) ?>. Bandingkan dengan dokumen yang Anda terima.</div>
        <?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($code === null && ! $formOnly) : ?>
        <p class="text-center small mt-3"><a href="<?= site_url('verify') ?>">Periksa dokumen lain dengan kode</a></p>
    <?php endif ?>
</main>
</body>
</html>
