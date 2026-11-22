<?php
/**
 * Shared layout for printed documents (Dompdf, A4 portrait). Document labels are Indonesian (printed for schools);
 * all code stays English. Sections: title, content.
 *
 * @var string|null $watermark  Big diagonal text (DRAFT, BATAL, LUNAS) or null.
 * @var string|null $groupLogo  Data URI of the group logo.
 * @var string      $groupName  Group name.
 */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title><?= $this->renderSection('title') ?></title>
<style>
    @page { margin: 28pt 38pt 50pt 38pt; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.3pt; color: #1f2a30; line-height: 1.35; }
    h1 { font-size: 17pt; margin: 0; color: #1f2a30; letter-spacing: .5pt; }
    h2 { font-size: 10pt; margin: 0 0 4pt; }
    .muted { color: #6b7780; }
    .small { font-size: 7.4pt; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .nowrap { white-space: nowrap; }
    .mono { font-family: "DejaVu Sans Mono", monospace; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }

    .head td { padding: 0; }
    .logo { width: 60pt; height: 60pt; }
    .doc-meta td { padding: .6pt 0 .6pt 8pt; text-align: right; }
    .doc-meta .label { color: #6b7780; }

    .parties { margin-top: 9pt; }
    .party { padding: 6pt 8pt; }
    .party-from { background: #eef1f2; }
    .party-to { border: 1pt solid #9aa5ab; }
    .party-title { font-size: 7.4pt; color: #6b7780; margin-bottom: 3pt; }
    .party-name { font-weight: bold; font-size: 9.6pt; margin-bottom: 2pt; }

    .items { margin-top: 9pt; }
    .items th { background: #1f3a44; color: #fff; font-weight: bold; padding: 4pt 5pt; font-size: 7.8pt; text-align: left; }
    .items td { padding: 3pt 5pt; border-bottom: .6pt solid #d5dadd; }
    .items .num { text-align: right; white-space: nowrap; }
    .items tr.empty td { color: #9aa5ab; text-align: center; padding: 10pt; }

    .totals td { padding: 1.6pt 5pt; }
    .totals .grand td { background: #eef1f2; font-weight: bold; font-size: 9.6pt; border-top: 1pt solid #1f3a44; }
    .box { border: 1pt solid #9aa5ab; padding: 6pt 8pt; }
    .section-title { font-weight: bold; margin: 7pt 0 2pt; font-size: 8.4pt; text-transform: uppercase; letter-spacing: .4pt; color: #1f3a44; }
    ol.terms { margin: 2pt 0 0 0; padding-left: 14pt; }
    ol.terms li { margin-bottom: 2pt; }

    .signatures { margin-top: 10pt; page-break-inside: avoid; }
    .keep { page-break-inside: avoid; }
    .sign { text-align: center; padding: 0 6pt; }
    .sign-space { height: 40pt; }
    .sign-name { font-weight: bold; text-decoration: underline; }
    .stamp { display: inline-block; border: .8pt dashed #9aa5ab; color: #9aa5ab; font-size: 6.5pt; padding: 8pt 6pt; margin-top: 6pt; }
    .qr { width: 62pt; height: 62pt; }

    .watermark { position: fixed; top: 300pt; left: 40pt; width: 440pt; text-align: center; font-size: 96pt; font-weight: bold;
                 color: rgba(200, 40, 40, .13); transform: rotate(-30deg); }
    .group { position: fixed; bottom: -36pt; right: 115pt; font-size: 7pt; color: #6b7780; }
    .group img { height: 14pt; vertical-align: middle; }
</style>
</head>
<body>
<?php if (! empty($watermark)) : ?><div class="watermark"><?= esc($watermark) ?></div><?php endif ?>
<?php if (! empty($groupLogo)) : ?><div class="group"><img src="<?= $groupLogo ?>" alt=""> <?= esc($groupName) ?> group</div><?php endif ?>
<?= $this->renderSection('content') ?>
</body>
</html>
