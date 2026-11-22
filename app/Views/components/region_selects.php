<?php
/**
 * Province → regency/city → district (kecamatan) dropdowns (cascading, see assets/js/cascade-select.js). Values are stored as names
 * (lower case, see normalize_name()); options are shown in upper case and matched case-insensitively.
 * A stored value that is not in the list (older free-text data) is kept as an extra option.
 *
 * @var array<string, mixed>                       $record    Current record (empty on create).
 * @var array{provinces: list<array{code: string, name: string}>, cities: list<array{code: string, name: string}>, districts?: list<array{code: string, name: string}>} $regions
 * @var string|null                                $districtField Default 'district'.
 * @var string|null                                $provinceField Default 'province'.
 * @var string|null                                $cityField     Default 'city'.
 * @var string|null                                $colClass      Bootstrap column classes per select.
 */
$provinceField ??= 'province';
$cityField     ??= 'city';
$districtField ??= 'district';
$colClass      ??= 'col-12 col-md-6';

$province = form_value($provinceField, $record);
$city     = form_value($cityField, $record);
$district = form_value($districtField, $record);

/**
 * Options HTML; adds the current value if it is not in the list.
 *
 * @param list<array{code: string, name: string}> $rows
 */
$options = static function (array $rows, string $current): string {
    $html  = '';
    $found = false;

    foreach ($rows as $row) {
        $selected = mb_strtolower($row['name']) === mb_strtolower($current);
        $found    = $found || $selected;
        $html    .= '<option value="' . esc(up($row['name']), 'attr') . '" data-code="' . esc($row['code'], 'attr') . '"' . ($selected ? ' selected' : '') . '>' . esc(up($row['name'])) . '</option>';
    }

    if ($current !== '' && ! $found) {
        $html .= '<option value="' . esc(up($current), 'attr') . '" selected>' . esc(up($current)) . ' (' . esc(lang('Customers.notInList')) . ')</option>';
    }

    return $html;
};
?>
<div class="<?= esc($colClass, 'attr') ?>">
    <label for="<?= esc($provinceField, 'attr') ?>" class="form-label"><?= lang('Customers.province') ?></label>
    <select class="form-select<?= invalid($provinceField) ?>" id="<?= esc($provinceField, 'attr') ?>" name="<?= esc($provinceField, 'attr') ?>"
            data-cascade-target="#<?= esc($cityField, 'attr') ?>" data-cascade-url="<?= site_url('api/regions') ?>?parent=">
        <option value=""><?= lang('Customers.selectProvince') ?></option>
        <?= $options($regions['provinces'], $province) ?>
    </select>
    <?= field_error($provinceField) ?>
</div>
<div class="<?= esc($colClass, 'attr') ?>">
    <label for="<?= esc($cityField, 'attr') ?>" class="form-label"><?= lang('Customers.city') ?></label>
    <select class="form-select<?= invalid($cityField) ?>" id="<?= esc($cityField, 'attr') ?>" name="<?= esc($cityField, 'attr') ?>"
            data-cascade-target="#<?= esc($districtField, 'attr') ?>" data-cascade-url="<?= site_url('api/regions') ?>?parent="
            data-cascade-placeholder="<?= esc(lang('Customers.selectProvinceFirst'), 'attr') ?>" data-cascade-empty="<?= esc(lang('Customers.selectCity'), 'attr') ?>">
        <option value=""><?= $regions['cities'] === [] && $province === '' ? lang('Customers.selectProvinceFirst') : lang('Customers.selectCity') ?></option>
        <?= $options($regions['cities'], $city) ?>
    </select>
    <?= field_error($cityField) ?>
</div>
<div class="<?= esc($colClass, 'attr') ?>">
    <label for="<?= esc($districtField, 'attr') ?>" class="form-label"><?= lang('Customers.district') ?></label>
    <select class="form-select<?= invalid($districtField) ?>" id="<?= esc($districtField, 'attr') ?>" name="<?= esc($districtField, 'attr') ?>"
            data-cascade-placeholder="<?= esc(lang('Customers.selectCityFirst'), 'attr') ?>" data-cascade-empty="<?= esc(lang('Customers.selectDistrict'), 'attr') ?>">
        <option value=""><?= ($regions['districts'] ?? []) === [] && $city === '' ? lang('Customers.selectCityFirst') : lang('Customers.selectDistrict') ?></option>
        <?= $options($regions['districts'] ?? [], $district) ?>
    </select>
    <?= field_error($districtField) ?>
</div>
