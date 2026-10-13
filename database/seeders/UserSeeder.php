<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@app.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'phone' => '021-1234566',
                'position' => 'Super Administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (! $superAdmin->hasRole('super admin')) {
            $superAdmin->assignRole('super admin');
        }

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@app.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'phone' => '021-1234567',
                'position' => 'System Administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // User
        $user = User::firstOrCreate(
            ['email' => 'user@app.com'],
            [
                'name' => 'Sample User',
                'password' => Hash::make('password'),
                'phone' => '021-1234568',
                'position' => 'Staff',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        // User tambahan agar halaman manajemen user lebih hidup
        $extraUsers = [
            ['name' => 'Capt. Andi Prasetya', 'email' => 'andi.prasetya@app.com', 'position' => 'Surveyor Senior'],
            ['name' => 'Ir. Budi Santoso', 'email' => 'budi.santoso@app.com', 'position' => 'Surveyor'],
            ['name' => 'Capt. Dewi Lestari', 'email' => 'dewi.lestari@app.com', 'position' => 'Surveyor'],
            ['name' => 'Ir. Hendra Wijaya', 'email' => 'hendra.wijaya@app.com', 'position' => 'Surveyor'],
            ['name' => 'Capt. Rizal Fahmi', 'email' => 'rizal.fahmi@app.com', 'position' => 'Surveyor'],
            ['name' => 'Ir. Sari Rahmawati', 'email' => 'sari.rahmawati@app.com', 'position' => 'Surveyor'],
            ['name' => 'Dinda Kusuma', 'email' => 'dinda.kusuma@app.com', 'position' => 'Staff Administrasi'],
            ['name' => 'Bayu Nugroho', 'email' => 'bayu.nugroho@app.com', 'position' => 'Staff Administrasi'],
        ];

        foreach ($extraUsers as $i => $extra) {
            $extraUser = User::firstOrCreate(
                ['email' => $extra['email']],
                [
                    'name' => $extra['name'],
                    'password' => Hash::make('password'),
                    'phone' => '0812'.str_pad((string) (10000000 + $i * 137913), 8, '0', STR_PAD_LEFT),
                    'position' => $extra['position'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            if (! $extraUser->hasRole('user')) {
                $extraUser->assignRole('user');
            }
        }
    }
}
