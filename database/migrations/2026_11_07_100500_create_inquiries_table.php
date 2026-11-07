<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Inquiries from the website (D10). The website saves the row first, then
 * emails the team; email_sent stays 0 when that fails. Staff change only
 * status, owner and note. listing_id, project_id and owner_id have no
 * foreign keys on purpose: a listing deleted to the trash and restored keeps
 * its inquiries (a SET NULL key would lose the link for good); a missing
 * record shows as "#12 (missing)".
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS `inquiries` (
            ' . $this->id($db) . ',
            `reference` VARCHAR(20) NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT \'contact\',
            `listing_id` INT UNSIGNED NULL,
            `project_id` INT UNSIGNED NULL,
            `service` VARCHAR(60) NULL,
            `saved_ids` TEXT NULL,
            `name` VARCHAR(190) NOT NULL DEFAULT \'\',
            `email` VARCHAR(190) NOT NULL DEFAULT \'\',
            `phone` VARCHAR(40) NULL,
            `preferred_contact` VARCHAR(20) NULL,
            `message` TEXT NULL,
            `consent_at` DATETIME NULL,
            `lang` VARCHAR(10) NULL,
            `page_url` VARCHAR(500) NULL,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT \'new\',
            `owner_id` INT UNSIGNED NULL,
            `note` TEXT NULL,
            `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE (`reference`),
            INDEX `inquiries_status` (`status`, `created_at`),
            INDEX `inquiries_listing` (`listing_id`),
            INDEX `inquiries_project` (`project_id`),
            INDEX `inquiries_owner` (`owner_id`)
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `inquiries`');
    }
};
