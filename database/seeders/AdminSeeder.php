<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '6281200000000'],
            [
                'name'     => 'Admin AGROENDER',
                'role'     => 'admin',
                'password' => 'Admin12345',
            ]
        );

        User::updateOrCreate(
            ['phone' => '6281200000001'],
            [
                'name' => 'Petani Contoh',
                'role' => 'petani',
            ]
        );
    }
}
