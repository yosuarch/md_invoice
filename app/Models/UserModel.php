<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/**
 * Shield user provider with the app's extra columns (see migration AddFullNameToUsers).
 * Registered in Config\Auth::$userProvider.
 */
class UserModel extends ShieldUserModel
{
    /**
     * Add app-specific columns to Shield's allowed fields.
     *
     * @return void
     */
    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields = [
            ...$this->allowedFields,
            'full_name',
            'locale',
        ];
    }
}
