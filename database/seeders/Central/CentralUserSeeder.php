<?php

namespace Database\Seeders\Central;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;

class CentralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'superadmin',
                'email' => 'superadmin@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'tester',
                'email' => 'tester@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'developer',
                'email' => 'developer@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'admin',
                'email' => 'admin@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'manager',
                'email' => 'manager@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'staff',
                'email' => 'staff@shaz3e.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        ];

        foreach ($users as $user) {
            CentralUser::create($user);
        }
    }
}
