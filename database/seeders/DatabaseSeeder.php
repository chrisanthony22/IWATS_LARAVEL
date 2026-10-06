<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // <--- Import Hash facade here

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'admin@iwats.com'], // Checks if user exists by email
            [
                'name' => 'System Administrator',
                'email' => 'chrisanthony.monteon@bicol-u.edu.ph',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ]
        );
    }
}