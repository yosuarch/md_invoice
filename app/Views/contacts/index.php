<?php
/**
 * People linked to the schools the user may see.
 *
 * @var list<array<string, mixed>> $people  With 'schools' (GROUP_CONCAT, see ContactPersonModel::parseSchools()).
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var string                     $search
 */

use App\Models\ContactPersonModel;
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Contacts.contacts') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Contacts.contacts'),
    'subtitle' => lang('Contacts.listSubtitle'),
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-8">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($search) ?>" placeholder="<?= esc(lang('Contacts.searchHint'), 'attr') ?>">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($search !== '') : ?><a href="<?= site_url('contacts') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a><?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('Contacts.person') ?></th>
                    <th><?= lang('Contacts.phone') ?></th>
                    <th><?= lang('Contacts.schools') ?></th>
                    <th><?= lang('App.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($people as $p) : ?>
                    <?php $wa = $p['cp_whatsapp'] ? ContactPersonModel::whatsappUrl($p['cp_phone']) : null ?>
                    <tr>
                        <td>
                            <a href="<?= site_url('contacts/' . $p['id']) ?>" class="fw-semibold text-decoration-none"><?= esc(ContactPersonModel::displayName($p)) ?></a>
                            <?php if ($p['cp_email']) : ?><div class="small text-body-secondary text-break"><?= esc($p['cp_email']) ?></div><?php endif ?>
                        </td>
                        <td class="small text-nowrap">
                            <?= esc($p['cp_phone'] ?? '') ?>
                            <?php if ($wa !== null) : ?><a href="<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener" class="ms-1 text-success" title="<?= esc(lang('Contacts.chatWhatsapp'), 'attr') ?>"><i class="bi bi-whatsapp"></i></a><?php endif ?>
                        </td>
                        <td class="small">
                            <?php foreach (ContactPersonModel::parseSchools($p['schools']) as $s) : ?>
                                <div class="<?= $s['current'] ? '' : 'text-body-tertiary' ?>">
                                    <?= esc(up($s['name'])) ?><?= $s['role'] !== '' ? ' · ' . esc(code_label('contactRole', $s['role'])) : '' ?><?= $s['current'] ? '' : ' (' . esc(lang('Contacts.left')) . ')' ?>
                                </div>
                            <?php endforeach ?>
                        </td>
                        <td><?= active_badge($p['is_active']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($people === []) : ?>
                    <tr><td colspan="4" class="text-center text-body-secondary py-4"><i class="bi bi-person-lines-fill fs-3 d-block mb-1"></i><?= $search !== '' ? lang('Contacts.noMatch') : lang('Contacts.noneYet') ?></td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Contacts.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
