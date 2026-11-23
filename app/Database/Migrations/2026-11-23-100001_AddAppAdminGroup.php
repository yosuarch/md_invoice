<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Split of the old all-powerful `admin` group (Config\AuthGroups):
 * - `app_admin` (developer: users, backups, calculation / numbering settings, audit log; full access as a fallback)
 * - `management` (oversight and approvals)
 * - `admin` (business administration without approvals)
 *
 * Shield groups are config, only the memberships are data: every current `admin` member also joins
 * `app_admin`, so nobody loses the rights they had before the split.
 */
class AddAppAdminGroup extends BaseMigration
{
    /**
     * Add `app_admin` to every member of `admin` who does not have it yet.
     *
     * @return void
     */
    public function up(): void
    {
        $now     = date('Y-m-d H:i:s');
        $members = $this->db->table('auth_groups_users')->select('user_id')->where('group', 'admin')->get()->getResultArray();

        foreach ($members as $row) {
            $exists = $this->db->table('auth_groups_users')
                ->where(['user_id' => $row['user_id'], 'group' => 'app_admin'])
                ->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('auth_groups_users')->insert([
                    'user_id'    => $row['user_id'],
                    'group'      => 'app_admin',
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * Remove the `app_admin` memberships (the `admin` memberships stay).
     *
     * @return void
     */
    public function down(): void
    {
        $this->db->table('auth_groups_users')->where('group', 'app_admin')->delete();
        $this->db->table('auth_groups_users')->where('group', 'management')->delete();
    }
}
