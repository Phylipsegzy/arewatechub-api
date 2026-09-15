<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WorkspaceSeeder::class,
            CohortSeeder::class,
            LegacyCustomerSeeder::class,
            LegacyAdminSeeder::class,
        ]);
    }
}
