<?php
/**
 * Settings → Calculation: tax per line / per total, rounding, amount and unit price decimals, with live examples.
 *
 * @var \App\Services\CalcSettings                                $settings
 * @var bool                                                      $hasProfile Company profile saved.
 * @var array<string, array<string, mixed>>                       $examples   DocumentTotals results ('incl', 'excl').
 */
$value = static fn (string $field, string|int $current): string => (string) (old($field) ?? $current);
$radio = static function (string $name, array $options, string $current): string {
    $html = '';

    foreach ($options as $key => $label) {
        $id    = $name . '_' . strtolower((string) $key);
        $html .= '<div class="form-check"><input class="form-check-input" type="radio" name="' . $name . '" id="' . $id . '" value="' . esc((string) $key, 'attr') . '"'
            . ((string) $key === $current ? ' checked' : '') . '><label class="form-check-label" for="' . $id . '">' . esc($label) . '</label></div>';
    }

    return $html;
};
$exampleRows = static fn (array $result): string => '<tr><td>' . esc(lang('Settings.calcDpp')) . '</td><td class="text-end text-nowrap" data-ex="subtotal">' . esc(format_idr($result['subtotal'], 2)) . '</td></tr>'
    . '<tr><td>' . esc(lang('Settings.calcTax')) . '</td><td class="text-end text-nowrap" data-ex="tax_total">' . esc(format_idr($result['tax_total'], 2)) . '</td></tr>'
    . '<tr class="fw-semibold"><td>' . esc(lang('Settings.calcTotal')) . '</td><td class="text-end text-nowrap" data-ex="grand_total">' . esc(format_idr($result['grand_total'], 2)) . '</td></tr>';
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Settings.calculation') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Settings.calculation'),
    'subtitle'   => lang('Settings.calculationText'),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.calculation'), '']],
]) ?>

<?php if (! $hasProfile) : ?>
    <div class="alert alert-warning"><?= lang('Settings.calcNoProfile') ?></div>
<?php endif ?>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- full page load after saving, so every page gets the new settings (window.AppCalc in the head) -->
                <form action="<?= site_url('settings/calculation') ?>" method="post" class="row g-4" novalidate id="calculation-form" hx-boost="false">
                    <?= csrf_field() ?>
                    <fieldset class="col-12">
                        <legend class="form-label fs-6 fw-semibold"><?= lang('Settings.calcTaxMode') ?></legend>
                        <?= $radio('calc_tax_mode', ['LINE' => lang('Settings.calcTaxLine'), 'TOTAL' => lang('Settings.calcTaxTotal')], $value('calc_tax_mode', $settings->taxMode)) ?>
                        <div class="form-text"><?= lang('Settings.calcTaxHelp') ?></div>
                    </fieldset>
                    <fieldset class="col-12">
                        <legend class="form-label fs-6 fw-semibold"><?= lang('Settings.calcRounding') ?></legend>
                        <?= $radio('calc_rounding', ['HALF_UP' => lang('Settings.calcHalfUp'), 'DOWN' => lang('Settings.calcDown')], $value('calc_rounding', $settings->rounding)) ?>
                        <div class="form-text"><?= lang('Settings.calcRoundingHelp') ?></div>
                    </fieldset>
                    <fieldset class="col-12 col-md-6">
                        <legend class="form-label fs-6 fw-semibold"><?= lang('Settings.calcAmountDecimals') ?></legend>
                        <?= $radio('calc_amount_decimals', [2 => lang('Settings.calcAmount2'), 0 => lang('Settings.calcAmount0')], $value('calc_amount_decimals', $settings->amountDecimals)) ?>
                        <div class="form-text"><?= lang('Settings.calcAmountHelp') ?></div>
                    </fieldset>
                    <div class="col-12 col-md-6">
                        <label for="calc_unit_decimals" class="form-label fw-semibold"><?= lang('Settings.calcUnitDecimals') ?></label>
                        <select class="form-select" id="calc_unit_decimals" name="calc_unit_decimals">
                            <?php foreach (\App\Services\CalcSettings::UNIT_DECIMALS as $d) : ?>
                                <option value="<?= $d ?>"<?= (int) $value('calc_unit_decimals', $settings->unitDecimals) === $d ? ' selected' : '' ?>><?= $d ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text"><?= lang('Settings.calcUnitHelp') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-1"></i><?= lang('Settings.calcNotice') ?></div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"<?= $hasProfile ? '' : ' disabled' ?>><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                        <a href="<?= site_url('settings') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm" id="calculation-examples" data-examples="<?= esc(json_encode(\App\Controllers\Settings\Calculation::EXAMPLES), 'attr') ?>">
            <div class="card-header bg-white fw-semibold"><?= lang('Settings.calcExample') ?></div>
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= lang('Settings.calcExampleIncl') ?></div>
                <table class="table table-sm mb-3" data-example="incl"><tbody><?= $exampleRows($examples['incl']) ?></tbody></table>
                <div class="fw-semibold mb-1"><?= lang('Settings.calcExampleExcl') ?></div>
                <div class="text-body-secondary mb-1">10.333,33 + 20.454,55 + 5.050,51</div>
                <table class="table table-sm mb-0" data-example="excl"><tbody><?= $exampleRows($examples['excl']) ?></tbody></table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/calculation-form.js') ?>"></script>
<?= $this->endSection() ?>
