<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * The media library (flocms-admin 0.17): one row per uploaded image or video,
 * and where each one is used. Files live in the public media folder; the
 * website reads them with media($id).
 *
 * A "media" table that already exists with other columns is left alone and
 * the migration stops: set 'media_library' => ['table' => …, 'usage_table' => …]
 * in config/admin.php, delete this migration and run php flo admin:install again.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        foreach (['media' => ['kind', 'path', 'thumb_path', 'mime', 'alt'], 'media_usage' => ['media_id', 'resource', 'record_id', 'field']] as $table => $columns) {
            $existing = $this->columns($db, $table);
            if ($existing !== null && array_diff($columns, $existing) !== []) {
                throw new \RuntimeException('A table "' . $table . '" already exists and is not the media library\'s. Rename the library\'s tables in config/admin.php (\'media_library\' => [\'table\' => …, \'usage_table\' => …]), delete this migration and run php flo admin:install again.');
            }
        }

        $db->query('CREATE TABLE IF NOT EXISTS `media` (
            ' . $this->id($db) . ',
            `kind` VARCHAR(10) NOT NULL,
            `path` VARCHAR(255) NOT NULL,
            `thumb_path` VARCHAR(255) NULL,
            `mime` VARCHAR(100) NOT NULL,
            `bytes` INTEGER NOT NULL DEFAULT 0,
            `width` INTEGER NULL,
            `height` INTEGER NULL,
            `file_name` VARCHAR(255) NOT NULL DEFAULT \'\',
            `title` VARCHAR(255) NOT NULL DEFAULT \'\',
            `alt` TEXT NULL,
            `uploaded_by` INTEGER NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL
        )' . $this->tableOptions($db));
        $db->query('CREATE TABLE IF NOT EXISTS `media_usage` (
            `media_id` INTEGER NOT NULL,
            `resource` VARCHAR(64) NOT NULL,
            `record_id` VARCHAR(191) NOT NULL,
            `field` VARCHAR(64) NOT NULL,
            PRIMARY KEY (`media_id`, `resource`, `record_id`, `field`)
        )' . $this->tableOptions($db));
        foreach (['media_kind_index' => '`media` (`kind`)', 'media_created_index' => '`media` (`created_at`)', 'media_usage_record_index' => '`media_usage` (`resource`, `record_id`)'] as $index => $on) {
            try { $db->query('CREATE INDEX `' . $index . '` ON ' . $on); } catch (\PDOException) { /* already there */ }
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `media_usage`');
        $db->query('DROP TABLE IF EXISTS `media`');
    }

    /** @return list<string>|null Column names, or null when the table does not exist. */
    private function columns(Database $db, string $table): ?array
    {
        try {
            if ($db->driver() === 'sqlite') {
                $rows = $db->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(\PDO::FETCH_ASSOC);
                return $rows === [] ? null : array_map(static fn (array $row): string => strtolower((string) $row['name']), $rows);
            }
            $rows = $db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(static fn (array $row): string => strtolower((string) $row['Field']), $rows);
        } catch (\Throwable) {
            return null;
        }
    }
};
