<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Settings.banks') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Settings.banks'),
    'subtitle'   => lang('Settings.banksSubtitle'),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.banks'), '']],
    'actions'    => '<a href="' . site_url('settings/bank-accounts/new') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . esc(lang('Settings.addAccount')) . '</a>',
]) ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('Settings.bank') ?></th>
                    <th><?= lang('Settings.accountNumber') ?></th>
                    <th><?= lang('Settings.accountName') ?></th>
                    <th><?= lang('App.status') ?></th>
                    <th class="text-end"><?= lang('App.actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a) : ?>
                    <tr>
                        <td class="fw-semibold"><?= esc(up($a['bank_name'])) ?></td>
                        <td class="font-monospace"><?= esc($a['bank_account_number']) ?></td>
                        <td><?= esc(up($a['bank_account_name'])) ?></td>
                        <td><?= active_badge($a['is_active']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('settings/bank-accounts/' . $a['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> <?= lang('App.edit') ?></a>
                            <?= view('components/delete_button', [
                                'action'  => site_url('settings/bank-accounts/' . $a['id'] . '/delete'),
                                'label'   => lang('App.delete'),
                                'icon'    => 'trash',
                                'confirm' => lang('Settings.deleteBankConfirm', [up($a['bank_name']), $a['bank_account_number']]),
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($accounts === []) : ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-4"><i class="bi bi-bank fs-3 d-block mb-1"></i><?= lang('Settings.noBanks') ?></td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
