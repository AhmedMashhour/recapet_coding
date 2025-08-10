<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        Admin::create([
            'name' => 'System Auditor',
            'email' => 'auditor@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }
}
