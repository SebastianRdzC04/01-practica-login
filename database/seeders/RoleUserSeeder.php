<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Cliente Demo',
                'email' => 'cliente@example.com',
                'role' => User::ROLE_CLIENT,
                'password' => 'Cliente123!',
                'two_factor_secret' => null,
                'two_factor_enabled' => false,
            ],
            [
                'name' => 'Usuario Demo',
                'email' => 'usuario@example.com',
                'role' => User::ROLE_USER,
                'password' => 'Usuario123!',
                'two_factor_secret' => null,
                'two_factor_enabled' => false,
            ],
            [
                'name' => 'Admin Demo',
                'email' => 'admin@example.com',
                'role' => User::ROLE_ADMIN,
                'password' => 'Admin123!',
                'two_factor_secret' => null,
                'two_factor_enabled' => false,
            ],
            [
                'name' => 'Logger Demo',
                'email' => 'logger@example.com',
                'role' => User::ROLE_LOGGER,
                'password' => 'Logger123!',
                'two_factor_secret' => null,
                'two_factor_enabled' => false,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($data['password']),
                ]
            );
        }
    }
}
