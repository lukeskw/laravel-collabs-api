<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@example.com',
            ],
        ];

        foreach ($users as $user) {
            User::factory()->create([
                ...$user,
                'password' => 'a1b2c3d4e5',
            ]);
        }
    }
}
