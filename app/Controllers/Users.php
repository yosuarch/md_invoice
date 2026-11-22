<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/**
 * Staff user management (permission users.manage). Public registration is disabled.
 */
class Users extends BaseController
{
    private const FIELDS = ['username', 'full_name', 'email', 'password', 'password_confirm'];

    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    /**
     * List users with roles and status.
     *
     * @return string
     */
    public function index(): string
    {
        return view('users/index', [
            'users'  => $this->service->all(),
            'groups' => UserService::groupOptions(),
        ]);
    }

    /**
     * Create form.
     *
     * @return string
     */
    public function new(): string
    {
        return view('users/form', ['user' => null, 'groups' => UserService::groupOptions()]);
    }

    /**
     * Store a new user.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        $data = $this->formData();

        if (! $this->validateData($data, UserService::rules(null))) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        try {
            $this->service->create($data);
        } catch (Throwable $e) {
            return $this->failed($e, 'User create');
        }

        return redirect()->to('users')->with('message', lang('Messages.userCreated', [$data['username']]));
    }

    /**
     * Edit form.
     *
     * @param int $id User id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        return view('users/form', ['user' => $this->findUser($id), 'groups' => UserService::groupOptions()]);
    }

    /**
     * Update a user.
     *
     * @param int $id User id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->findUser($id);
        $data = $this->formData();

        if (! $this->validateData($data, UserService::rules($id))) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        try {
            $this->service->update($id, $data, $this->userId());
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'User update');
        }

        return redirect()->to('users')->with('message', lang('Messages.userUpdated', [$data['username']]));
    }

    /**
     * Deactivate (ban) a user.
     *
     * @param int $id User id.
     *
     * @return RedirectResponse
     */
    public function deactivate(int $id): RedirectResponse
    {
        return $this->setBanned($id, true);
    }

    /**
     * Reactivate (unban) a user.
     *
     * @param int $id User id.
     *
     * @return RedirectResponse
     */
    public function activate(int $id): RedirectResponse
    {
        return $this->setBanned($id, false);
    }

    /**
     * @return RedirectResponse
     */
    private function setBanned(int $id, bool $ban): RedirectResponse
    {
        $user = $this->findUser($id);

        try {
            $this->service->setBanned($id, $ban, $this->userId());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'User ban/unban');
        }

        return redirect()->to('users')->with('message', lang($ban ? 'Messages.userDeactivated' : 'Messages.userReactivated', [$user->username]));
    }

    /**
     * Submitted form values, with groups as a list.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $data           = $this->postFields(self::FIELDS);
        $groups         = $this->request->getPost('groups');
        $data['groups'] = is_array($groups) ? array_values(array_filter($groups, 'is_string')) : [];

        return $data;
    }

    /**
     * @throws PageNotFoundException
     */
    private function findUser(int $id): User
    {
        return $this->service->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
