<?php

// Add this INSIDE the 'connections' => [...] array in your existing
// config/database.php, as a sibling to the 'mysql' entry that's already
// there (from `laravel new`). Don't replace the whole file — just add this
// one new key.

'legacy' => [
    'driver' => 'mysql',
    'host' => env('LEGACY_DB_HOST', 'localhost'),
    'port' => env('LEGACY_DB_PORT', '3306'),
    'database' => env('LEGACY_DB_DATABASE', 'u844281546_arewatech'),
    'username' => env('LEGACY_DB_USERNAME', 'forge'),
    'password' => env('LEGACY_DB_PASSWORD', ''),
    'unix_socket' => env('LEGACY_DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
],
