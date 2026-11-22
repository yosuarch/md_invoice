<?php
/**
 * @var string               $type
 * @var array<string, mixed> $def
 * @var array<string, mixed> $row  Empty on create.
 */
$isEdit = $row !== [];
$title  = lang($isEdit ? 'Settings.lookupEditTitle' : 'Settings.lookupAddTitle', [$def['title']]);
$action = $isEdit ? site_url("settings/lookups/{$type}/{$row['id']}") : site_url("settings/lookups/{$type}");
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [$def['title'], site_url("settings/lookups/{$type}")], [$isEdit ? lang('App.edit') : lang('App.add'), '']],
]) ?>

<div class="card shadow-sm" style="max-width: 820px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($def['fields'] as $name => $field) : ?>
                <?php
                $locked = $isEdit && $name === 'code';
                $id     = esc($name, 'attr');
                $width  = $field['width'] ?? (in_array($field['type'], ['number', 'date', 'select'], true) ? 'col-md-6' : '');
                ?>
                <?php if ($field['type'] === 'checkbox') : ?>
                    <div class="col-12">
                        <input type="hidden" name="<?= $id ?>" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input<?= invalid($name) ?>" type="checkbox" role="switch" id="<?= $id ?>" name="<?= $id ?>" value="1"<?= form_checked($name, $row, ! empty($field['default'])) ?>>
                            <label class="form-check-label" for="<?= $id ?>"><?= esc($field['label']) ?></label>
                            <?= field_error($name) ?>
                        </div>
                    </div>
                    <?php continue ?>
                <?php endif ?>

                <div class="col-12 <?= $width ?>">
                    <label for="<?= $id ?>" class="form-label"><?= esc($field['label']) ?></label>
                    <?php if ($field['type'] === 'select') : ?>
                        <select class="form-select<?= invalid($name) ?>" id="<?= $id ?>" name="<?= $id ?>">
                            <?php foreach ($field['options'] as $value => $label) : ?>
                                <option value="<?= esc((string) $value, 'attr') ?>"<?= form_value($name, $row) === (string) $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                    <?php elseif ($field['type'] === 'textarea') : ?>
                        <textarea class="form-control<?= invalid($name) ?>" id="<?= $id ?>" name="<?= $id ?>" rows="2"><?= esc(form_value($name, $row)) ?></textarea>
                    <?php else : ?>
                        <input type="<?= esc($field['type'], 'attr') ?>"
                               class="form-control<?= $name === 'code' ? ' font-monospace text-uppercase' : '' ?><?= invalid($name) ?>"
                               id="<?= $id ?>" name="<?= $id ?>" value="<?= esc(form_value($name, $row)) ?>"
                               <?= isset($field['step']) ? 'step="' . esc($field['step'], 'attr') . '"' : '' ?>
                               <?= $locked ? 'readonly' : '' ?>>
                    <?php endif ?>
                    <?= field_error($name) ?>
                    <?php if ($locked) : ?>
                        <div class="form-text"><?= lang('Settings.codesFixed') ?></div>
                    <?php elseif (! empty($field['help'])) : ?>
                        <div class="form-text"><?= esc($field['help']) ?></div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url("settings/lookups/{$type}") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
