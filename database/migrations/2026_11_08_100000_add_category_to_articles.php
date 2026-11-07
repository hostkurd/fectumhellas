<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Insights categories (Stage 3c): one category per article, from
 * Catalog::articleCategories(). Existing articles become buying guides.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $db->query('ALTER TABLE `articles` ADD COLUMN `category` VARCHAR(30) NOT NULL DEFAULT \'buying_guide\'');
        $db->query('CREATE INDEX `articles_category` ON `articles` (`category`, `publish_status`, `published_at`)');
    }

    public function down(Database $db): void
    {
        $db->query('DROP INDEX `articles_category` ON `articles`');
        $db->query('ALTER TABLE `articles` DROP COLUMN `category`');
    }
};
