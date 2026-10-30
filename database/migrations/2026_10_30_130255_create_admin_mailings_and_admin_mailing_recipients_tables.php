<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Emails to users (flocms-admin 0.28).
 *
 * "admin_mailings": one row per mailing (subject, message, who it goes
 * to, progress). "admin_mailing_recipients": one row per person a mailing goes
 * to, while it runs and for a while after (the panel's 'mailings' => ['keep_days' => …]).
 * "admin_mail_optouts": the addresses that unsubscribed, as keyed hashes only.
 */
return new class extends Migration
{
    public bool $transactional = false;

    public function up(Database $db): void
    {
        $text = $db->driver() === 'mysql' ? 'LONGTEXT' : 'TEXT';
        $db->query('CREATE TABLE IF NOT EXISTS `admin_mailings` (
            ' . $this->id($db) . ',
            `subject` VARCHAR(255) NOT NULL,
            `body` ' . $text . ' NOT NULL,
            `audience` ' . $text . ' NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `total` INTEGER NOT NULL DEFAULT 0,
            `sent` INTEGER NOT NULL DEFAULT 0,
            `failed` INTEGER NOT NULL DEFAULT 0,
            `skipped` INTEGER NOT NULL DEFAULT 0,
            `user_id` INTEGER NULL,
            `user_name` VARCHAR(190) NULL,
            `lang` VARCHAR(16) NOT NULL DEFAULT \'\',
            `created_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL,
            `locked_until` VARCHAR(40) NULL
        )' . $this->tableOptions($db));
        $db->query('CREATE TABLE IF NOT EXISTS `admin_mailing_recipients` (
            ' . $this->id($db) . ',
            `mailing_id` INTEGER NOT NULL,
            `user_id` INTEGER NULL,
            `email` VARCHAR(190) NOT NULL,
            `name` VARCHAR(190) NOT NULL DEFAULT \'\',
            `status` VARCHAR(10) NOT NULL,
            `attempts` INTEGER NOT NULL DEFAULT 0,
            `error` VARCHAR(255) NULL,
            `sent_at` DATETIME NULL
        )' . $this->tableOptions($db));
        $db->query('CREATE TABLE IF NOT EXISTS `admin_mail_optouts` (
            `email_hash` CHAR(64) NOT NULL PRIMARY KEY,
            `created_at` DATETIME NOT NULL
        )' . $this->tableOptions($db));

        foreach ([
            'admin_mailings_status_index' => '`admin_mailings` (`status`)',
            'admin_mailing_recipients_mailing_index' => '`admin_mailing_recipients` (`mailing_id`, `status`)',
            'admin_mailing_recipients_sent_index' => '`admin_mailing_recipients` (`sent_at`)',
        ] as $index => $on) {
            try { $db->query('CREATE INDEX `' . $index . '` ON ' . $on); } catch (\PDOException) { /* already there */ }
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS `admin_mailing_recipients`');
        $db->query('DROP TABLE IF EXISTS `admin_mailings`');
        $db->query('DROP TABLE IF EXISTS `admin_mail_optouts`');
    }
};
