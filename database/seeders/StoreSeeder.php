<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::updateOrCreate(
            ['email' => 'store@client.test'],
            [
                'name' => 'Demo Store',
                'owner_name' => 'Demo Owner',
                'phone' => '0999999999',
                'password' => Hash::make('Store@12345'),
                'address' => 'Demo Address',
                'status' => 'active',
            ],
        );
    }
}
