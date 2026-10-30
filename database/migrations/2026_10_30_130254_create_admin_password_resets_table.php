<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Forgot password (flocms-admin 0.26).
 *
 * "admin_password_resets": one row per reset request ("request") and per wrong or old
 * reset link that was opened ("attempt"). Only hashes are stored: of the
 * address, of the visitor's IP address and of the link's token, never the
 * address, the IP or the token themselves. The rows count requests for the
 * rate limits and are deleted after a day.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        $db->query('CREATE TABLE IF NOT EXISTS `admin_password_resets` (
            ' . $this->id($db) . ',
            `kind` VARCHAR(10) NOT NULL,
            `user_id` INTEGER NULL,
            `email_hash` CHAR(64) NULL,
            `ip_hash` CHAR(64) NOT NULL,
            `token_hash` CHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NULL,
            `used_at` DATETIME NULL
        )' . $this->tableOptions($db));

        foreach ([
            'admin_password_resets_token_index' => '`admin_password_resets` (`token_hash`)',
            'admin_password_resets_email_index' => '`admin_password_resets` (`email_hash`, `created_at`)',
            'admin_password_resets_ip_index' => '`admin_password_resets` (`ip_hash`, `created_at`)',
            'admin_password_resets_user_index' => '`admin_password_resets` (`user_id`)',
        ] as $index => $on) {
            try { $db->query('CREATE INDEX `' . $index . '` ON ' . $on); } catch (\PDOException) { /* already there */ }
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `admin_password_resets`');
    }
};
