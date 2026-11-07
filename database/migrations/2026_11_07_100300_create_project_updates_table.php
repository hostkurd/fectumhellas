<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Dated construction progress updates of a development (D6), managed on the
 * development's edit page. A development with updates can't be deleted
 * (RESTRICT): delete its updates first.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $json = $db->driver() === 'sqlite' ? 'TEXT' : 'JSON';

        $db->query('CREATE TABLE IF NOT EXISTS `project_updates` (
            ' . $this->id($db) . ',
            `project_id` INT UNSIGNED NOT NULL,
            `update_date` DATE NULL,
            `title` ' . $json . ' NULL,
            `body` ' . $json . ' NULL,
            `photos` TEXT NULL,
            `publish_status` VARCHAR(20) NOT NULL DEFAULT \'published\',
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            INDEX `project_updates_project` (`project_id`, `update_date`),
            FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT
        )' . $this->tableOptions($db));
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `project_updates`');
    }
};
