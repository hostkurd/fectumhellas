<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Lookups managed in the admin (Stage 2, design note D4): cities, property
 * types and amenities. Each has one translatable name ({"en":…,"ar":…,"el":…})
 * and a sort order for the Reorder button.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $json = $db->driver() === 'sqlite' ? 'TEXT' : 'JSON';

        foreach (['cities', 'property_types', 'amenities'] as $table) {
            $db->query('CREATE TABLE IF NOT EXISTS `' . $table . '` (
                ' . $this->id($db) . ',
                `name` ' . $json . ' NULL,
                `sort` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL
            )' . $this->tableOptions($db));
        }
    }

    public function down(Database $db): void
    {
        foreach (['amenities', 'property_types', 'cities'] as $table) {
            $db->query('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }
};
