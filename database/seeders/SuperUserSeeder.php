<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Check if a SUPER user already exists
        if (!User::where('role', 'SUPER')->exists()) {
            User::create([
                'first_name' => 'admin',
                'last_name' => 'admin',
                'email' => 'admin@npg.co.zw',
                'password' => Hash::make('123456789'),
                'role' => 'SUPER',
                'is_activated' => true,
                'company_name' => 'Norah Payment Gateway',
            ]);

            echo "SUPER user created successfully.\n";
        } else {
            echo "SUPER user already exists.\n";
        }
    }
}
