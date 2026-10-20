<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Super Admin - All permissions (bypass via Gate::before in AuthServiceProvider)
        $superAdmin = Role::firstOrCreate(['name' => 'super admin']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. Admin - Operasional penuh, tanpa manajemen role/konfigurasi sistem/impersonate
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(
            Permission::whereNotIn('name', [
                'users_impersonate',
                'configuration_view',
                'configuration_update',
                'configuration_export_excel',
                'configuration_export_pdf',
                'roles_view',
                'roles_create',
                'roles_update',
                'roles_delete',
                'roles_export_excel',
                'roles_export_pdf',
            ])->get()
        );

        // 3. Inspektur - Surveyor lapangan: kerjakan survey, susun folder, generate & edit laporan
        $inspektur = Role::firstOrCreate(['name' => 'inspektur']);
        $inspektur->syncPermissions([
            'dashboard_view',
            'notifications_view',
            'chat_view',
            'chat_create',
            'chat_delete',
            'companies_view',
            'ships_view',
            'surveys_view',
            'surveys_create',
            'surveys_update',
            'surveys_export_excel',
            'surveys_export_pdf',
            'survey_folders_create',
            'survey_folders_update',
            'survey_reports_view',
            'survey_reports_update',
            'survey_reports_generate',
            'survey_reports_edit_word',
            'survey_templates_view',
        ]);

        // 4. User - Akses dasar (dashboard, notifikasi, chat)
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'dashboard_view',
            'notifications_view',
            'chat_view',
            'chat_create',
            'chat_delete',
        ]);
    }
}
