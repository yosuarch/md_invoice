<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\BankAccountModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * The company's receiving bank accounts.
 */
class BankAccounts extends BaseController
{
    private const FIELDS = ['bank_name', 'bank_account_number', 'bank_account_name', 'is_active'];

    private BankAccountModel $model;

    public function __construct()
    {
        $this->model = new BankAccountModel();
    }

    /**
     * List all accounts.
     *
     * @return string
     */
    public function index(): string
    {
        return view('settings/bank_accounts/index', [
            'accounts' => $this->model->orderBy('is_active', 'DESC')->orderBy('bank_name')->findAll(),
        ]);
    }

    /**
     * Create form.
     *
     * @return string
     */
    public function new(): string
    {
        return view('settings/bank_accounts/form', ['account' => []]);
    }

    /**
     * Store a new account.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        try {
            if ($this->model->insert($this->postFields(self::FIELDS)) === false) {
                return $this->backWithErrors($this->model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, 'Bank account create');
        }

        return redirect()->to('settings/bank-accounts')->with('message', lang('Messages.bankAdded'));
    }

    /**
     * Edit form.
     *
     * @param int $id Account id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        return view('settings/bank_accounts/form', ['account' => $this->findOr404($this->model, $id)]);
    }

    /**
     * Update an account.
     *
     * @param int $id Account id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->findOr404($this->model, $id);

        try {
            if (! $this->model->update($id, $this->postFields(self::FIELDS))) {
                return $this->backWithErrors($this->model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, 'Bank account update');
        }

        return redirect()->to('settings/bank-accounts')->with('message', lang('Messages.bankUpdated'));
    }

    /**
     * Delete an account that no document uses.
     *
     * @param int $id Account id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $this->findOr404($this->model, $id);

        if ($this->model->isInUse($id)) {
            return redirect()->back()->with('error', lang('Messages.bankInUse'));
        }

        try {
            $this->model->delete($id);
        } catch (Throwable $e) {
            return $this->failed($e, 'Bank account delete');
        }

        return redirect()->to('settings/bank-accounts')->with('message', lang('Messages.bankDeleted'));
    }
}
