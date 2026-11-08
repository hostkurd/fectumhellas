<?php
declare(strict_types=1);

namespace App\Admin\Widgets\Support;

use FloCMS\Admin\Admin;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Admin\Translation\Translations;
use FloCMS\Core\App;

/**
 * Shared pieces of the FECTUM Hellas dashboard cards (design note "Admin
 * dashboard redesign"): the agreed limits, words with numbers, and the two
 * checks that need more than a count.
 */
final class Dash
{
    /** A New inquiry is waiting too long after this many hours (Q2). */
    public const WAITING_HOURS = 48;
    /** A published development needs a progress update after this many days (Q3). */
    public const UPDATE_DAYS = 30;
    /** Fields checked for missing Greek or Arabic text (Q6). */
    public const TEXT_FIELDS = ['title', 'summary', 'description'];

    /** A site lang key with :name replacements. */
    public static function t(string $key, array $replace = []): string
    {
        $text = AdminLang::label($key);
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    /** A translated JSON column in the panel's language, or the English text. */
    public static function text(mixed $value): string
    {
        $text = trim((string) Translations::get($value, AdminLang::language()));
        return $text !== '' ? $text : trim((string) Translations::get($value, 'en'));
    }

    /** "Y-m-d H:i:s" of the moment after which a New inquiry is not yet late. */
    public static function waitingSince(?int $now = null): string
    {
        return date('Y-m-d H:i:s', ($now ?? time()) - self::WAITING_HOURS * 3600);
    }

    /**
     * A list of the panel, already filtered and sorted. The reactive list keeps
     * its state in admin-resource_filters (JSON), admin-resource_sort and
     * admin-resource_dir (flocms-admin docs/tables-and-login.md).
     */
    public static function listUrl(string $slug, array $filters = [], ?string $sort = null, string $dir = 'asc'): string
    {
        $query = [];
        if ($filters !== []) {
            $query['admin-resource_filters'] = json_encode($filters, JSON_UNESCAPED_UNICODE);
        }
        if ($sort !== null) {
            $query['admin-resource_sort'] = $sort;
            $query['admin-resource_dir'] = $dir;
        }
        return Admin::url($slug, $query);
    }

    /**
     * Properties with an empty title, summary or description in a language,
     * counted as the list's "Missing in" filter counts them.
     *
     * @return array<string, int> language => count
     */
    public static function missingText(array $languages): array
    {
        $counts = array_fill_keys($languages, 0);
        $db = App::db();
        if ($db === null || $languages === []) {
            return $counts;
        }
        $rows = $db->table('listings')->select(array_merge(['id'], self::TEXT_FIELDS))->get();
        foreach ($rows as $row) {
            foreach ($languages as $lang) {
                foreach (self::TEXT_FIELDS as $field) {
                    // all() leaves empty languages out (get() would fall back to English)
                    $value = (string) (Translations::all($row->{$field} ?? '')[$lang] ?? '');
                    if (trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
                        $counts[$lang]++;
                        break;
                    }
                }
            }
        }
        return $counts;
    }

    /**
     * Published developments without a published progress update for
     * UPDATE_DAYS days, the longest waiting first.
     *
     * @return list<array{id: int, title: string, days: int|null}> days null: no update yet
     */
    public static function staleProjects(?int $now = null): array
    {
        $db = App::db();
        if ($db === null) {
            return [];
        }
        $now ??= time();
        $last = [];
        foreach ($db->table('project_updates')->selectRaw('`project_id`, MAX(`update_date`) AS last_update')
            ->where('publish_status', '=', 'published')->groupBy('project_id')->get() as $row) {
            $last[(int) $row->project_id] = (string) $row->last_update;
        }
        $stale = [];
        foreach ($db->table('projects')->select(['id', 'title', 'created_at'])->where('publish_status', '=', 'published')->get() as $project) {
            $since = $last[(int) $project->id] ?? null;
            $from = strtotime((string) ($since ?? $project->created_at ?? ''));
            if ($from === false) {
                continue;
            }
            $days = intdiv(max(0, $now - $from), 86400);
            if ($days >= self::UPDATE_DAYS) {
                $stale[] = ['id' => (int) $project->id, 'title' => self::text($project->title), 'days' => $since === null ? null : $days, 'wait' => $days];
            }
        }
        usort($stale, static fn (array $a, array $b): int => $b['wait'] <=> $a['wait']);
        return array_map(static fn (array $p): array => ['id' => $p['id'], 'title' => $p['title'], 'days' => $p['days']], $stale);
    }
}
