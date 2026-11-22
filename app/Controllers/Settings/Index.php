<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;

/**
 * Settings landing page: company profile, bank accounts and lookup tables.
 */
class Index extends BaseController
{
    /**
     * Show the settings sections.
     *
     * @return string
     */
    public function index(): string
    {
        $lookups = [];

        foreach (config('Lookups')->types as $slug => $def) {
            $lookups[$slug] = localize_lookup($slug, $def);
        }

        return view('settings/index', ['lookups' => $lookups]);
    }
}
