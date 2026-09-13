<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    public function getAllRolesWithPermissions(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    public function getRolePermissions(int $roleId): array
    {
        $role = Role::with('permissions')->find($roleId);

        return $role ? $role->permissions->pluck('name')->toArray() : [];
    }

    public function createRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name]);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function updateRole(Role $role, string $name, array $permissions = []): Role
    {
        $role->update(['name' => $name]);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    public function togglePermission(Role $role, string $permission): string
    {
        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);

            return 'revoked';
        }

        $role->givePermissionTo($permission);

        return 'granted';
    }

    public function roleHasUsers(Role $role): bool
    {
        return $role->users()->count() > 0;
    }

    /**
     * Build permission groups dynamically from database.
     * Maps permissions to groups based on naming convention.
     * Any unmatched permissions go to 'Lainnya' group.
     *
     * Returns: ['Group Name' => [['name' => 'permission_key', 'label' => 'Human Label'], ...]]
     */
    public function buildPermissionGroups(): array
    {
        $groupMapping = [
            'Dashboard' => [
                ['name' => 'dashboard_view', 'label' => 'Lihat Dashboard'],
            ],
            'Perusahaan' => [
                ['name' => 'companies_view',         'label' => 'Lihat Perusahaan'],
                ['name' => 'companies_create',       'label' => 'Tambah Perusahaan'],
                ['name' => 'companies_update',       'label' => 'Edit Perusahaan'],
                ['name' => 'companies_delete',       'label' => 'Hapus Perusahaan'],
                ['name' => 'companies_export_excel', 'label' => 'Export Excel Perusahaan'],
                ['name' => 'companies_export_pdf',   'label' => 'Export PDF Perusahaan'],
                ['name' => 'manage_own_company',     'label' => 'Kelola Perusahaan Sendiri'],
            ],
            'Konfigurasi System' => [
                ['name' => 'configuration_view',         'label' => 'Lihat Konfigurasi'],
                ['name' => 'configuration_update',       'label' => 'Edit Konfigurasi'],
                ['name' => 'configuration_export_excel', 'label' => 'Export Excel Konfigurasi'],
                ['name' => 'configuration_export_pdf',   'label' => 'Export PDF Konfigurasi'],
            ],
            'Manajemen User' => [
                ['name' => 'users_view',         'label' => 'Lihat User'],
                ['name' => 'users_create',       'label' => 'Tambah User'],
                ['name' => 'users_update',       'label' => 'Edit User'],
                ['name' => 'users_delete',       'label' => 'Hapus User'],
                ['name' => 'users_export_excel', 'label' => 'Export Excel User'],
                ['name' => 'users_export_pdf',   'label' => 'Export PDF User'],
                ['name' => 'users_impersonate',  'label' => 'Impersonate User'],
            ],
            'Roles & Permissions' => [
                ['name' => 'roles_view',         'label' => 'Lihat Roles'],
                ['name' => 'roles_create',       'label' => 'Tambah Role'],
                ['name' => 'roles_update',       'label' => 'Edit Role'],
                ['name' => 'roles_delete',       'label' => 'Hapus Role'],
                ['name' => 'roles_export_excel', 'label' => 'Export Excel Roles'],
                ['name' => 'roles_export_pdf',   'label' => 'Export PDF Roles'],
            ],
            'Notifikasi' => [
                ['name' => 'notifications_view', 'label' => 'Lihat Notifikasi'],
                ['name' => 'notifications_send', 'label' => 'Kirim Notifikasi'],
            ],
            'Chat' => [
                ['name' => 'chat_view',   'label' => 'Lihat Chat'],
                ['name' => 'chat_create', 'label' => 'Buat Chat'],
                ['name' => 'chat_delete', 'label' => 'Hapus Chat'],
            ],
            'Kapal' => [
                ['name' => 'ships_view',         'label' => 'Lihat Kapal'],
                ['name' => 'ships_create',       'label' => 'Tambah Kapal'],
                ['name' => 'ships_update',       'label' => 'Edit Kapal'],
                ['name' => 'ships_delete',       'label' => 'Hapus Kapal'],
                ['name' => 'ships_export_excel', 'label' => 'Export Excel Kapal'],
                ['name' => 'ships_export_pdf',   'label' => 'Export PDF Kapal'],
            ],
            'Survey Kondisi' => [
                ['name' => 'surveys_view',         'label' => 'Lihat Survey'],
                ['name' => 'surveys_create',       'label' => 'Tambah Survey'],
                ['name' => 'surveys_update',       'label' => 'Edit Survey'],
                ['name' => 'surveys_delete',       'label' => 'Hapus Survey'],
                ['name' => 'surveys_export_excel', 'label' => 'Export Excel Survey'],
                ['name' => 'surveys_export_pdf',   'label' => 'Export PDF Survey'],
            ],
            'Template Form' => [
                ['name' => 'survey_templates_view',         'label' => 'Lihat Template'],
                ['name' => 'survey_templates_create',       'label' => 'Tambah Template'],
                ['name' => 'survey_templates_update',       'label' => 'Edit Template'],
                ['name' => 'survey_templates_delete',       'label' => 'Hapus Template'],
                ['name' => 'survey_templates_duplicate',    'label' => 'Duplikasi Template'],
                ['name' => 'survey_templates_export_excel', 'label' => 'Export Excel Template'],
                ['name' => 'survey_templates_export_pdf',   'label' => 'Export PDF Template'],
            ],
        ];

        $allPermissions = Permission::pluck('name')->toArray();
        $mapped = collect($groupMapping)->flatten(1)->pluck('name')->toArray();
        $unmapped = array_diff($allPermissions, $mapped);

        $groups = $groupMapping;

        if (! empty($unmapped)) {
            $groups['Lainnya'] = array_values(array_map(
                fn ($p) => ['name' => $p, 'label' => ucwords(str_replace('_', ' ', $p))],
                $unmapped
            ));
        }

        return $groups;
    }
}
