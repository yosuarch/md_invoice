<?php
/**
 * "Dari" (store) and "Kepada" (customer) boxes.
 *
 * @var array<string, mixed> $company  Company profile.
 * @var array<string, mixed> $customer Customer with address_text, level_name.
 * @var string|null          $toTitle  Title of the right box (default "Kepada").
 * @var string|null          $toAddress Address to print instead of the customer's default billing address.
 * @var string|null          $toExtra  Extra line under the customer (e.g. contact person).
 */
$toTitle ??= 'Kepada';
$address = $toAddress ?? $customer['address_text'] ?? '';
$school  = implode(' · ', array_filter([
    ! empty($customer['npsn']) ? 'NPSN ' . $customer['npsn'] : null,
    ! empty($customer['cl_tax_id']) ? 'NPWP ' . $customer['cl_tax_id'] : null,
]));
?>
<table class="parties">
    <tr>
        <td style="width: 49%" class="party party-from">
            <div class="party-title">Dari</div>
            <div class="party-name"><?= esc($company['company_name']) ?></div>
            <?php if (! empty($company['address'])) : ?><div><?= nl2br(esc($company['address'])) ?></div><?php endif ?>
            <div class="small" style="margin-top: 3pt">
                <?= esc(implode(' · ', array_filter([
                    ! empty($company['tax_id']) ? 'NPWP ' . $company['tax_id'] : null,
                    $company['phone'] ?? null,
                ]))) ?>
                <?php if (! empty($company['email']) || ! empty($company['website'])) : ?>
                    <br><?= esc(implode(' · ', array_filter([$company['email'] ?? null, $company['website'] ?? null]))) ?>
                <?php endif ?>
            </div>
        </td>
        <td style="width: 2%"></td>
        <td style="width: 49%" class="party party-to">
            <div class="party-title"><?= esc($toTitle) ?></div>
            <div class="party-name"><?= esc($customer['cl_organization_name'] ?? '') ?></div>
            <?php if ($address !== '') : ?><div><?= nl2br(esc($address)) ?></div><?php endif ?>
            <?php if ($school !== '') : ?><div class="small" style="margin-top: 3pt"><?= esc($school) ?></div><?php endif ?>
            <?php if (! empty($toExtra)) : ?><div class="small"><?= esc($toExtra) ?></div><?php endif ?>
        </td>
    </tr>
</table>
