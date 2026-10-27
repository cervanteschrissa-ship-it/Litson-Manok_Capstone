<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Creates the three branches and the owner/staff login accounts if missing. */
    public function run(): void
    {
        $branches = [
            ['branch_name' => 'Calauan Plaza',  'location' => 'Calauan, Laguna'],
            ['branch_name' => 'Dayap Palengke', 'location' => 'Dayap, Calauan, Laguna'],
            ['branch_name' => 'San Pablo City', 'location' => 'San Pablo City, Laguna'],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(['branch_name' => $branch['branch_name']], $branch);
        }

        User::firstOrCreate(['username' => 'owner'], [
            'name' => 'Shop Owner', 'password' => 'owner123', 'role' => 'owner',
        ]);
        User::firstOrCreate(['username' => 'staff'], [
            'name' => 'Shop Staff', 'password' => 'staff123', 'role' => 'staff',
        ]);
    }
}