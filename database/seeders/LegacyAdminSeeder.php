<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Imports your real admin login from the legacy admindb so you can use the
// same admin credentials tonight — password hash preserved (bcrypt, Laravel-compatible).
class LegacyAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@arewatechub.com.ng'],
            [
                'password' => '$2y$10$06v1btdmpN7HrWgc6GT1Y.8sCSS86u2N9y06IBn3lmwA6NnnlYGYe',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
