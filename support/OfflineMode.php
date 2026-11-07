<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Admin\Translation\Languages;
use FloCMS\Core\App;
use FloCMS\Core\Database;

/**
 * The "Website offline" switch on Settings → Website status.
 *
 * flocms-core already has an offline mode: while the setting offline_mode is
 * '1' in the visitor's language, every website page shows
 * templates/default/offline.html, and anyone signed in to the admin panel
 * still sees the real website. Core keeps that setting as one row per
 * language (param, value, lang), the same rows `php flo down` writes. This
 * class writes them for every site language when the switch is saved.
 */
final class OfflineMode
{
    public static function set(bool $offline): void
    {
        $db = App::db();
        if (!$db instanceof Database) {
            return;
        }
        $value = $offline ? '1' : '0';
        foreach (Languages::all() as $lang) {
            $exists = (int) $db->query(
                'SELECT COUNT(*) FROM settings WHERE param = ? AND lang = ?',
                ['offline_mode', $lang]
            )->fetchColumn() > 0;
            if ($exists) {
                $db->query('UPDATE settings SET value = ? WHERE param = ? AND lang = ?', [$value, 'offline_mode', $lang]);
            } else {
                $db->query('INSERT INTO settings (param, value, lang) VALUES (?, ?, ?)', ['offline_mode', $value, $lang]);
            }
        }
    }

    /** True while core shows the offline page to visitors (default language row). */
    public static function isOn(): bool
    {
        $db = App::db();
        if (!$db instanceof Database) {
            return false;
        }
        $value = $db->query(
            'SELECT value FROM settings WHERE param = ? AND lang = ? LIMIT 1',
            ['offline_mode', Languages::default()]
        )->fetchColumn();
        return (string) $value === '1';
    }
}
