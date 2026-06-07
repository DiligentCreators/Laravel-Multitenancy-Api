<?php

namespace Database\Seeders\Central;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;

class AssignRoleToUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userEmails = [
            '1' => 'superadmin@shaz3e.com',
            '2' => 'tester@shaz3e.com',
            '3' => 'developer@shaz3e.com',
            '4' => 'admin@shaz3e.com',
            '5' => 'manager@shaz3e.com',
            '6' => 'staff@shaz3e.com',
        ];

        $users = CentralUser::all();

        foreach ($users as $user) {
            foreach ($userEmails as $key => $email) {
                if ($user->email == $email) {
                    $user->roles()->attach($key);
                    break;
                }
            }
        }
    }
}
