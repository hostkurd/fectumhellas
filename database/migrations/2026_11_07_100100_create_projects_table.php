<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Developments (admin slug "projects", design note D2), e.g. Residence One.
 * Residences are listings with a development_id (D1); construction stages
 * are a JSON list (Repeater), progress updates live in project_updates (D6).
 * Media columns hold media-library ids (D7); brochure is a public PDF (D8).
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $json = $db->driver() === 'sqlite' ? 'TEXT' : 'JSON';

        $db->query('CREATE TABLE IF NOT EXISTS `projects` (
            ' . $this->id($db) . ',
            `title` ' . $json . ' NULL,
            `slug` VARCHAR(190) NOT NULL,
            `summary` ' . $json . ' NULL,
            `description` ' . $json . ' NULL,
            `city_id` INT UNSIGNED NULL,
            `area` ' . $json . ' NULL,
            `address` VARCHAR(255) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `progress` VARCHAR(20) NOT NULL DEFAULT \'planned\',
            `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `expected_completion` ' . $json . ' NULL,
            `stages` ' . $json . ' NULL,
            `key_facts` ' . $json . ' NULL,
            `cover_id` INT UNSIGNED NULL,
            `gallery` TEXT NULL,
            `video_url` VARCHAR(255) NULL,
            `brochure` VARCHAR(255) NULL,
            `meta_title` ' . $json . ' NULL,
            `meta_description` ' . $json . ' NULL,
            `social_image_id` INT UNSIGNED NULL,
            `featured` TINYINT(1) NOT NULL DEFAULT 0,
            `publish_status` VARCHAR(20) NOT NULL DEFAULT \'draft\',
            `sort` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE (`slug`),
            FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE RESTRICT
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `projects`');
    }
};
