<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

/**
 * php flo db:seed: the starting lookups (cities, property types, amenities).
 * Safe on the live site: a lookup table that already has rows is skipped.
 * Sample content for testing is separate: php flo db:seed --class=DemoSeeder
 */
final class DatabaseSeeder extends Seeder
{
    public function run(Database $db): void
    {
        $this->call(LookupsSeeder::class);
    }
}
