<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * The trash and the activity log (flocms-admin 0.19).
 *
 * "admin_trash": a copy of each record deleted in the admin panel (its row as
 * JSON and its many-to-many links), so it can be restored with the same id. The
 * row itself leaves its own table, so the website never shows deleted records.
 *
 * "admin_activity": who created, changed, deleted or restored what, and when,
 * with the fields that changed (passwords and hidden fields are never written).
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $text = $db->driver() === 'sqlite' ? 'TEXT' : 'LONGTEXT';

        $db->query('CREATE TABLE IF NOT EXISTS `admin_trash` (
            ' . $this->id($db) . ',
            `resource` VARCHAR(64) NOT NULL,
            `record_id` VARCHAR(191) NOT NULL,
            `title` TEXT NULL,
            `data` ' . $text . ' NOT NULL,
            `links` ' . $text . ' NULL,
            `files` TEXT NULL,
            `batch` CHAR(32) NOT NULL,
            `deleted_by` INTEGER NULL,
            `deleted_by_name` VARCHAR(190) NULL,
            `deleted_at` DATETIME NOT NULL
        )' . $this->tableOptions($db));

        $db->query('CREATE TABLE IF NOT EXISTS `admin_activity` (
            ' . $this->id($db) . ',
            `action` VARCHAR(40) NOT NULL,
            `resource` VARCHAR(64) NULL,
            `record_id` VARCHAR(191) NULL,
            `title` TEXT NULL,
            `user_id` INTEGER NULL,
            `user_name` VARCHAR(190) NULL,
            `changes` ' . $text . ' NULL,
            `meta` TEXT NULL,
            `batch` CHAR(32) NULL,
            `created_at` DATETIME NOT NULL
        )' . $this->tableOptions($db));

        foreach ([
            'admin_trash_resource_index' => '`admin_trash` (`resource`, `deleted_at`)',
            'admin_trash_batch_index' => '`admin_trash` (`batch`)',
            'admin_trash_deleted_index' => '`admin_trash` (`deleted_at`)',
            'admin_activity_record_index' => '`admin_activity` (`resource`, `record_id`)',
            'admin_activity_created_index' => '`admin_activity` (`created_at`)',
            'admin_activity_user_index' => '`admin_activity` (`user_id`)',
        ] as $index => $on) {
            try { $db->query('CREATE INDEX `' . $index . '` ON ' . $on); } catch (\PDOException) { /* already there */ }
        }
    }

    /** Rolling back drops both tables: the records waiting in the trash are lost. */
    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `admin_activity`');
        $db->query('DROP TABLE IF EXISTS `admin_trash`');
    }
};
