<?php

use Database\Seeders\DatabaseSeeder;

test('production seeding cannot create the development account with a known password', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed(DatabaseSeeder::class);
    $this->assertDatabaseCount('users', 0);
});
