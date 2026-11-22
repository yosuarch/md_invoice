<?php
/**
 * @var string                     $type
 * @var array<string, mixed>       $def
 * @var list<array<string, mixed>> $rows
 */
$readonly = ! empty($def['readonly']);
$formats  = $def['formats'] ?? [];
$actions  = $readonly ? '' : '<a href="' . site_url("settings/lookups/{$type}/new") . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . esc(lang('Settings.addEntry')) . '</a>';

/**
 * Format a cell according to the column format.
 */
$cell = static function (mixed $value, ?string $format): string {
    return match ($format) {
        'percent' => $value === null ? '<span class="text-body-secondary">-</span>' : esc(rtrim(rtrim((string) $value, '0'), '.')) . '%',
        'money'   => (float) $value > 0 ? esc(format_idr($value, 0)) : '<span class="text-body-secondary">-</span>',
        'date'    => $value ? esc(format_date((string) $value)) : '<span class="text-body-secondary">' . esc(lang('Settings.open')) . '</span>',
        'bool'    => $value ? '<i class="bi bi-check-circle-fill text-success" title="' . esc(lang('App.yes'), 'attr') . '"></i>' : '<i class="bi bi-dash text-body-secondary" title="' . esc(lang('App.no'), 'attr') . '"></i>',
        default   => esc((string) ($value ?? '')),
    };
};

// Select fields show their option label instead of the stored code.
foreach ($def['fields'] as $name => $field) {
    if ($field['type'] === 'select' && isset($def['columns'][$name]) && ! isset($formats[$name])) {
        $formats[$name] = 'option';
    }
}

$display = static function (string $col, mixed $value) use ($cell, $formats, $def): string {
    if (($formats[$col] ?? null) === 'option') {
        return esc((string) ($def['fields'][$col]['options'][$value] ?? $value));
    }

    return $cell($value, $formats[$col] ?? null);
};
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($def['title']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $def['title'],
    'subtitle'   => $def['description'],
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [$def['title'], '']],
    'actions'    => $actions,
]) ?>

<?php if ($readonly) : ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i><?= lang('Settings.statusesInfo') ?></div>
<?php endif ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <?php foreach ($def['columns'] as $label) : ?>
                        <th class="text-nowrap"><?= esc($label) ?></th>
                    <?php endforeach ?>
                    <?php if (! $readonly) : ?><th class="text-end"><?= lang('App.actions') ?></th><?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <?php foreach (array_keys($def['columns']) as $col) : ?>
                            <td class="<?= $col === 'code' ? 'font-monospace fw-semibold text-nowrap' : (in_array($formats[$col] ?? '', ['money', 'date', 'percent'], true) ? 'text-nowrap' : '') ?>"><?= $display($col, $row[$col] ?? null) ?></td>
                        <?php endforeach ?>
                        <?php if (! $readonly) : ?>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url("settings/lookups/{$type}/{$row['id']}/edit") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> <?= lang('App.edit') ?></a>
                                <?= view('components/delete_button', [
                                    'action'  => site_url("settings/lookups/{$type}/{$row['id']}/delete"),
                                    'label'   => lang('App.delete'),
                                    'icon'    => 'trash',
                                    'confirm' => lang('Settings.deleteEntryConfirm', [$row['code']]),
                                ]) ?>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($rows === []) : ?>
                    <tr><td colspan="<?= count($def['columns']) + 1 ?>" class="text-center text-body-secondary py-4"><?= lang('Settings.noEntries') ?></td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
