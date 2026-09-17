<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@agency.com'],
            [
                'name' => 'Admin',
                'password' => \Illuminate\Support\Facades\Hash::make(env('DEFAULT_ADMIN_PASSWORD', 'password123')),
            ]
        );
    }
}
