<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'username'     => 'panitia',
                'password'     => 'jsgi2026',
                'role'         => 'panitia',
                'display_name' => 'Panitia',
            ],
            [
                'username'     => 'eo',
                'password'     => 'jsgi2026',
                'role'         => 'eo',
                'display_name' => 'EO Gate',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['username' => $data['username']],
                $data,
            );
        }
    }
}
