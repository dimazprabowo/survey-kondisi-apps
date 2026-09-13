<?php

namespace App\Livewire\Traits;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Notification;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

trait HasMenuItems
{
    public function getMenuItems(): array
    {
        $user = Auth::user();

        // Cache per request — Sidebar and Navigation both call this, avoid double DB hit
        static $cache = [];
        $cacheKey = 'menu_'.$user->id;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // All checks go through Gate → policies (respects Gate::before super-admin bypass)
        $perms = [
            'dashboard_view' => Gate::allows('viewStats'),
            'companies_view' => Gate::allows('viewAny', Company::class),
            'ships_view' => Gate::allows('viewAny', \App\Models\Ship::class),
            'surveys_view' => Gate::allows('viewAny', \App\Models\Survey::class),
            'survey_templates_view' => Gate::allows('viewAny', \App\Models\SurveyTemplate::class),
            'notifications_view' => Gate::allows('viewAny', Notification::class),
            'notifications_send' => Gate::allows('send', Notification::class),
            'chat_view' => Gate::allows('viewAny', Chat::class),
            'configuration_view' => Gate::allows('viewAny', SystemConfiguration::class),
            'users_view' => Gate::allows('viewAny', User::class),
            'roles_view' => Gate::allows('viewAny', Role::class),
        ];

        $req = request();
        $items = [];

        // Dashboard — all roles
        $items[] = [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'home',
            'active' => $req->routeIs('dashboard'),
        ];

        // Master Data
        $masterDataChildren = [];
        if ($perms['companies_view']) {
            $masterDataChildren[] = [
                'name' => 'Perusahaan',
                'route' => 'master-data.companies',
                'active' => $req->routeIs('master-data.companies'),
            ];
        }
        if ($perms['ships_view']) {
            $masterDataChildren[] = [
                'name' => 'Kapal',
                'route' => 'master-data.ships',
                'active' => $req->routeIs('master-data.ships'),
            ];
        }
        if ($perms['survey_templates_view']) {
            $masterDataChildren[] = [
                'name' => 'Template Form',
                'route' => 'master-data.survey-templates.index',
                'active' => $req->routeIs('master-data.survey-templates.*'),
            ];
        }
        if (! empty($masterDataChildren)) {
            $items[] = [
                'name' => 'Master Data',
                'icon' => 'database',
                'active' => $req->routeIs('master-data.*'),
                'children' => $masterDataChildren,
            ];
        }

        // Survey Kondisi
        if ($perms['surveys_view']) {
            $items[] = [
                'name' => 'Survey Kondisi',
                'route' => 'surveys.index',
                'icon' => 'clipboard-check',
                'active' => $req->routeIs('surveys.*'),
            ];
        }

        // Notifikasi
        $canView = $perms['notifications_view'];
        $canSend = $perms['notifications_send'];

        if ($canView || $canSend) {
            $notifChildren = [];
            if ($canView) {
                $notifChildren[] = [
                    'name' => 'Kotak Masuk',
                    'route' => 'notifications.index',
                    'active' => $req->routeIs('notifications.index'),
                ];
            }
            if ($canSend) {
                $notifChildren[] = [
                    'name' => 'Kirim Notifikasi',
                    'route' => 'notifications.send',
                    'active' => $req->routeIs('notifications.send'),
                ];
            }

            if ($canView && $canSend) {
                $items[] = [
                    'name' => 'Notifikasi',
                    'icon' => 'bell',
                    'active' => $req->routeIs('notifications.*'),
                    'children' => $notifChildren,
                ];
            } else {
                $items[] = [
                    'name' => 'Notifikasi',
                    'route' => $canView ? 'notifications.index' : 'notifications.send',
                    'icon' => 'bell',
                    'active' => $req->routeIs('notifications.*'),
                ];
            }
        }

        // Chat
        if ($perms['chat_view']) {
            $items[] = [
                'name' => 'Chat',
                'route' => 'chat.index',
                'icon' => 'chat',
                'active' => $req->routeIs('chat.*'),
            ];
        }

        // Pengaturan
        $settingsChildren = [];
        if ($perms['configuration_view']) {
            $settingsChildren[] = [
                'name' => 'Konfigurasi System',
                'route' => 'settings.system',
                'active' => $req->routeIs('settings.system'),
            ];
        }
        if ($perms['users_view']) {
            $settingsChildren[] = [
                'name' => 'Manajemen User',
                'route' => 'settings.users',
                'active' => $req->routeIs('settings.users'),
            ];
        }
        if ($perms['roles_view']) {
            $settingsChildren[] = [
                'name' => 'Roles & Permissions',
                'route' => 'settings.roles',
                'active' => $req->routeIs('settings.roles'),
            ];
        }
        if (! empty($settingsChildren)) {
            $items[] = [
                'name' => 'Pengaturan',
                'icon' => 'cog',
                'active' => $req->routeIs('settings.*'),
                'children' => $settingsChildren,
            ];
        }

        $result = array_filter($items);
        $cache[$cacheKey] = $result;

        return $result;
    }
}
