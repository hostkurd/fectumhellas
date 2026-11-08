<?php
declare(strict_types=1);

use FloCMS\CLI\Database\Migration;
use FloCMS\Core\Database;

/**
 * Indexes for the admin dashboard (flocms-admin 0.42): inquiries counted by
 * received date and by where they were sent from stay quick as they grow.
 */
return new class extends Migration
{
    public function up(Database $db): void
    {
        foreach ([
            'inquiries_created' => '`inquiries` (`created_at`)',
            'inquiries_type' => '`inquiries` (`type`, `created_at`)',
        ] as $index => $on) {
            try { $db->query('CREATE INDEX `' . $index . '` ON ' . $on); } catch (\PDOException) { /* already there */ }
        }
    }

    public function down(Database $db): void
    {
        foreach (['inquiries_created', 'inquiries_type'] as $index) {
            try {
                $db->query($db->driver() === 'sqlite' ? 'DROP INDEX IF EXISTS `' . $index . '`' : 'DROP INDEX `' . $index . '` ON `inquiries`');
            } catch (\PDOException) { /* not there */ }
        }
    }
};
