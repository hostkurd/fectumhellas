<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Insights articles (public URL /<lang>/insights/<slug>) and the team shown
 * on About.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $json = $db->driver() === 'sqlite' ? 'TEXT' : 'JSON';

        $db->query('CREATE TABLE IF NOT EXISTS `articles` (
            ' . $this->id($db) . ',
            `title` ' . $json . ' NULL,
            `slug` VARCHAR(190) NOT NULL,
            `excerpt` ' . $json . ' NULL,
            `body` ' . $json . ' NULL,
            `cover_id` INT UNSIGNED NULL,
            `published_at` DATETIME NULL,
            `meta_title` ' . $json . ' NULL,
            `meta_description` ' . $json . ' NULL,
            `social_image_id` INT UNSIGNED NULL,
            `featured` TINYINT(1) NOT NULL DEFAULT 0,
            `publish_status` VARCHAR(20) NOT NULL DEFAULT \'draft\',
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE (`slug`),
            INDEX `articles_published` (`publish_status`, `published_at`)
        )' . $this->tableOptions($db));

        $db->query('CREATE TABLE IF NOT EXISTS `team_members` (
            ' . $this->id($db) . ',
            `name` VARCHAR(120) NOT NULL DEFAULT \'\',
            `role` ' . $json . ' NULL,
            `bio` ' . $json . ' NULL,
            `photo_id` INT UNSIGNED NULL,
            `email` VARCHAR(190) NULL,
            `linkedin` VARCHAR(255) NULL,
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            `sort` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `team_members`');
        $db->query('DROP TABLE IF EXISTS `articles`');
    }
};
