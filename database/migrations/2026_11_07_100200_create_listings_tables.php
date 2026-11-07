<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Properties (admin slug "listings"; public URL /<lang>/properties/<slug>).
 * A listing may belong to a development (residence, D1). Sales status and
 * draft/published are separate columns (D3). Compare fields per D5.
 * Amenities are linked through listing_amenity (D4); the panel removes and
 * restores those rows itself (trash), so the foreign keys are RESTRICT.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $json = $db->driver() === 'sqlite' ? 'TEXT' : 'JSON';

        $db->query('CREATE TABLE IF NOT EXISTS `listings` (
            ' . $this->id($db) . ',
            `reference` VARCHAR(30) NOT NULL,
            `title` ' . $json . ' NULL,
            `slug` VARCHAR(190) NOT NULL,
            `project_id` INT UNSIGNED NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT \'for_sale\',
            `price` DECIMAL(12,2) NULL,
            `price_on_request` TINYINT(1) NOT NULL DEFAULT 0,
            `property_type_id` INT UNSIGNED NULL,
            `bedrooms` TINYINT UNSIGNED NULL,
            `bathrooms` TINYINT UNSIGNED NULL,
            `area_m2` DECIMAL(9,2) NULL,
            `outdoor_m2` DECIMAL(9,2) NULL,
            `floor` ' . $json . ' NULL,
            `parking` TINYINT UNSIGNED NULL,
            `energy_class` VARCHAR(3) NULL,
            `construction_year` SMALLINT UNSIGNED NULL,
            `under_construction` TINYINT(1) NOT NULL DEFAULT 0,
            `city_id` INT UNSIGNED NULL,
            `area` ' . $json . ' NULL,
            `address` VARCHAR(255) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `summary` ' . $json . ' NULL,
            `description` ' . $json . ' NULL,
            `cover_id` INT UNSIGNED NULL,
            `gallery` TEXT NULL,
            `floor_plans` TEXT NULL,
            `videos` TEXT NULL,
            `video_url` VARCHAR(255) NULL,
            `brochure` VARCHAR(255) NULL,
            `meta_title` ' . $json . ' NULL,
            `meta_description` ' . $json . ' NULL,
            `social_image_id` INT UNSIGNED NULL,
            `featured` TINYINT(1) NOT NULL DEFAULT 0,
            `publish_status` VARCHAR(20) NOT NULL DEFAULT \'draft\',
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE (`reference`),
            UNIQUE (`slug`),
            INDEX `listings_search` (`publish_status`, `status`, `city_id`, `price`),
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`property_type_id`) REFERENCES `property_types` (`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE RESTRICT
        )' . $this->tableOptions($db));

        $db->query('CREATE TABLE IF NOT EXISTS `listing_amenity` (
            `listing_id` INT UNSIGNED NOT NULL,
            `amenity_id` INT UNSIGNED NOT NULL,
            `sort` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`listing_id`, `amenity_id`),
            FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE RESTRICT
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `listing_amenity`');
        $db->query('DROP TABLE IF EXISTS `listings`');
    }
};
