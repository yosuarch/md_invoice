<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * Staff user management on top of Shield (users, auth_identities, auth_groups_users).
 * Changes are written to audit_log manually because Shield's model is not an App\Models\BaseModel.
 */
final class UserService
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? auth()->getProvider();
    }

    /**
     * All staff users with identities and groups, ordered by username.
     *
     * @return list<User>
     */
    public function all(): array
    {
        return $this->users->withIdentities()->withGroups()->orderBy('username')->findAll();
    }

    /**
     * @param int $id User id.
     *
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * Active (not banned) staff users for "assigned to" selects: id => name (upper case) or username.
     *
     * @return array<int, string>
     */
    public static function activeOptions(): array
    {
        $rows = db_connect()->table('users')->select('id, username, full_name')
            ->where('deleted_at', null)->groupStart()->where('status', null)->orWhere('status !=', 'banned')->groupEnd()
            ->orderBy('username')->get()->getResultArray();

        $options = [];

        foreach ($rows as $r) {
            $options[(int) $r['id']] = $r['full_name'] ? up($r['full_name']) : $r['username'];
        }

        return $options;
    }

    /**
     * Group options: key => title in the screen language.
     *
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        $options = [];

        foreach (config('AuthGroups')->groups as $key => $g) {
            $options[$key] = code_label('group', $key, $g['title']);
        }

        return $options;
    }

    /**
     * Validation rules for the user form.
     *
     * @param int|null $id Null when creating.
     *
     * @return array<string, string>
     */
    public static function rules(?int $id): array
    {
        $groups = implode(',', array_keys(self::groupOptions()));

        return [
            'username' => [
                'label' => 'Users.username',
                'rules' => 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]|is_unique[users.username' . ($id ? ",id,{$id}" : '') . ']',
                'errors' => ['regex_match' => 'Messages.usernameChars'],
            ],
            'full_name' => ['label' => 'Users.fullName', 'rules' => 'required|max_length[128]'],
            'email'     => [
                'label' => 'Users.email',
                'rules' => 'required|max_length[254]|valid_email|is_unique[auth_identities.secret' . ($id ? ",user_id,{$id}" : '') . ']',
                'errors' => ['is_unique' => 'Messages.emailTaken'],
            ],
            'password'         => ['label' => 'Users.password', 'rules' => ($id ? 'permit_empty' : 'required') . '|' . Passwords::getMaxLengthRule() . '|strong_password'],
            'password_confirm' => ['label' => 'Users.confirmPassword', 'rules' => ($id ? 'permit_empty|' : 'required|') . 'matches[password]',
                'errors' => ['matches' => 'Messages.passwordsMismatch']],
            'groups'   => ['label' => 'Users.roles', 'rules' => 'required', 'errors' => ['required' => 'Messages.selectRole']],
            'groups.*' => ['label' => 'Users.role', 'rules' => "in_list[{$groups}]"],
        ];
    }

    /**
     * Create an active staff user with the given groups.
     *
     * @param array{username: string, full_name: string, email: string, password: string, groups: list<string>} $data
     *
     * @return int New user id.
     */
    public function create(array $data): int
    {
        $data['full_name'] = normalize_name((string) $data['full_name']); // stored lower case, shown with up()

        return Transaction::run(function () use ($data): int {
            $user = new User([
                'username'  => $data['username'],
                'full_name' => $data['full_name'],
                'email'     => $data['email'],
                'password'  => $data['password'],
                'active'    => 1,
            ]);

            $this->users->save($user);
            $id   = (int) $this->users->getInsertID();
            $user = $this->users->findById($id);
            $user->syncGroups(...$data['groups']);

            AuditLogger::log('users', $id, AuditLogger::CREATE, null, [
                'username'  => $data['username'],
                'full_name' => $data['full_name'],
                'email'     => $data['email'],
                'groups'    => implode(',', $data['groups']),
            ]);

            return $id;
        });
    }

    /**
     * Update profile, email, optional password and groups.
     *
     * @param int                  $id     User id.
     * @param array<string, mixed> $data   Validated form data.
     * @param int                  $editor Id of the user performing the change.
     *
     * @return void
     *
     * @throws RuntimeException When an app administrator tries to remove their own app_admin role.
     */
    public function update(int $id, array $data, int $editor): void
    {
        $user              = $this->findOrFail($id);
        $data['full_name'] = normalize_name((string) ($data['full_name'] ?? ''));

        if ($id === $editor && $user->inGroup('app_admin') && ! in_array('app_admin', $data['groups'], true)) {
            throw new RuntimeException(lang('Messages.ownAdminRole'));
        }

        Transaction::run(function () use ($user, $data): void {
            $before = $this->snapshot($user);

            $user->fill([
                'username'  => $data['username'],
                'full_name' => $data['full_name'],
                'email'     => $data['email'],
            ]);

            if (($data['password'] ?? '') !== '') {
                $user->password = $data['password'];
            }

            $this->users->save($user);
            $user->syncGroups(...$data['groups']);

            $after = $this->snapshot($this->findOrFail((int) $user->id));
            [$old, $new] = AuditLogger::diff($before, $after);

            if (($data['password'] ?? '') !== '') {
                $new['password_changed'] = true;
            }

            if ($new !== []) {
                AuditLogger::log('users', (int) $user->id, AuditLogger::UPDATE, $old, $new);
            }
        });
    }

    /**
     * Ban (deactivate) or unban a user. Banned users cannot log in.
     *
     * @param int  $id     User id.
     * @param bool $ban    True to ban, false to unban.
     * @param int  $editor Id of the user performing the change.
     *
     * @return void
     *
     * @throws RuntimeException When banning yourself or the last active app administrator.
     */
    public function setBanned(int $id, bool $ban, int $editor): void
    {
        $user = $this->findOrFail($id);

        if ($ban && $id === $editor) {
            throw new RuntimeException(lang('Messages.ownAccount'));
        }

        if ($ban && $user->inGroup('app_admin') && $this->activeAdminCount() <= 1) {
            throw new RuntimeException(lang('Messages.lastAdmin'));
        }

        $ban ? $user->ban('Deactivated by an administrator.') : $user->unBan();

        AuditLogger::log('users', $id, AuditLogger::UPDATE, ['status' => $ban ? null : 'banned'], ['status' => $ban ? 'banned' : null]);
    }

    /**
     * @return User
     *
     * @throws RuntimeException
     */
    private function findOrFail(int $id): User
    {
        return $this->users->findById($id) ?? throw new RuntimeException(lang('Messages.notFoundUser'));
    }

    /**
     * Comparable view of a user for the audit diff.
     *
     * @return array<string, string|null>
     */
    private function snapshot(User $user): array
    {
        $groups = $user->getGroups() ?? [];
        sort($groups);

        return [
            'username'  => $user->username,
            'full_name' => $user->full_name ?? null,
            'email'     => $user->email,
            'groups'    => implode(',', $groups),
        ];
    }

    /**
     * Number of app administrators (the group that manages users) who are not banned.
     */
    private function activeAdminCount(): int
    {
        return db_connect()->table('auth_groups_users g')
            ->join('users u', 'u.id = g.user_id')
            ->where('g.group', 'app_admin')
            ->where('u.deleted_at', null)
            ->groupStart()->where('u.status', null)->orWhere('u.status !=', 'banned')->groupEnd()
            ->countAllResults();
    }
}
