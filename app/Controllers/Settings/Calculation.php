<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\CompanyProfileModel;
use App\Services\CalcSettings;
use App\Services\DocumentTotals;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Settings → Calculation (permission settings.manage): how document amounts are calculated — tax per line or per
 * document total, rounding mode, whole-rupiah amounts and unit price decimals (CalcSettings, stored on
 * company_profile). New and edited drafts use the settings; issued documents keep their stored amounts.
 */
class Calculation extends BaseController
{
    /**
     * Example lines shown on the page: a line priced incl. tax and three lines excl. tax.
     *
     * @var array<string, list<array<string, string>>>
     */
    public const EXAMPLES = [
        'incl' => [['quantity' => '150', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '190000', 'discount_incl' => '0']],
        'excl' => [
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '10333.33', 'discount_amount' => '0'],
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '20454.55', 'discount_amount' => '0'],
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '5050.51', 'discount_amount' => '0'],
        ],
    ];

    /**
     * The settings form with worked examples.
     *
     * @return string
     */
    public function edit(): string
    {
        $profile  = (new CompanyProfileModel())->getProfile();
        $settings = CalcSettings::fromProfile($profile);

        return view('settings/calculation', [
            'settings'   => $settings,
            'hasProfile' => $profile !== null,
            'examples'   => array_map(static fn (array $lines): array => DocumentTotals::calculate($lines, '0', $settings), self::EXAMPLES),
        ]);
    }

    /**
     * Save the settings.
     *
     * @return RedirectResponse
     */
    public function update(): RedirectResponse
    {
        $model   = new CompanyProfileModel();
        $profile = $model->getProfile();

        if ($profile === null) {
            return redirect()->back()->with('error', lang('Settings.calcNoProfile'));
        }

        $data = $this->postFields(['calc_tax_mode', 'calc_rounding', 'calc_amount_decimals', 'calc_unit_decimals']);
        $data = [
            'id'                   => (int) $profile['id'],
            'calc_tax_mode'        => in_array($data['calc_tax_mode'], CalcSettings::TAX_MODES, true) ? $data['calc_tax_mode'] : 'LINE',
            'calc_rounding'        => in_array($data['calc_rounding'], CalcSettings::ROUNDING_MODES, true) ? $data['calc_rounding'] : 'HALF_UP',
            'calc_amount_decimals' => in_array((int) $data['calc_amount_decimals'], CalcSettings::AMOUNT_DECIMALS, true) ? (int) $data['calc_amount_decimals'] : 2,
            'calc_unit_decimals'   => in_array((int) $data['calc_unit_decimals'], CalcSettings::UNIT_DECIMALS, true) ? (int) $data['calc_unit_decimals'] : 2,
        ];

        try {
            if (! $model->update((int) $profile['id'], $data)) {
                return $this->backWithErrors($model->errors());
            }

            CalcSettings::clearCache();
        } catch (Throwable $e) {
            return $this->failed($e, 'Calculation settings update');
        }

        return redirect()->to('settings/calculation')->with('message', lang('Settings.calcSaved'));
    }
}
