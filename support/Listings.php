<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\App;
use FloCMS\Core\Database;

/**
 * Reading published content for the website: properties (listings),
 * developments (projects), articles and team members, and turning a
 * listing row into the values a property card shows. Only published
 * records ever leave this class.
 */
final class Listings
{
    /** @var array<string, array<int, string>> */
    private static array $names = [];

    private static function db(): ?Database
    {
        $db = App::db();
        return $db instanceof Database ? $db : null;
    }

    /** Featured published properties, newest first; the newest published ones when none is featured. */
    public static function featured(int $limit = 3): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('listings')->where('publish_status', '=', 'published')->where('featured', '=', 1)
            ->orderBy('id', 'DESC')->limit($limit)->get();
        if ($rows === []) {
            $rows = $db->table('listings')->where('publish_status', '=', 'published')
                ->whereIn('status', Catalog::availableStatuses())->orderBy('id', 'DESC')->limit($limit)->get();
        }
        self::preload($rows);
        return array_map([self::class, 'card'], $rows);
    }

    /** Loads the covers of these rows in one query (media() then reads them from memory). */
    private static function preload(array $rows): void
    {
        if (function_exists('media_preload')) {
            media_preload(array_values(array_filter(array_map(static fn (object $row): mixed => $row->cover_id ?? null, $rows))));
        }
    }

    /** Published developments in the editors' order, with their number of residences. */
    public static function projects(int $limit = 6): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('projects')->where('publish_status', '=', 'published')->orderBy('sort')->orderBy('id', 'DESC')->limit($limit)->get();
        self::preload($rows);
        // Residences of all these developments in one query.
        $counts = [];
        if ($rows !== []) {
            $ids = array_map(static fn (object $row): int => (int) $row->id, $rows);
            $marks = implode(', ', array_fill(0, count($ids), '?'));
            foreach ($db->query('SELECT `project_id`, COUNT(*) AS `n` FROM `listings` WHERE `publish_status` = ? AND `project_id` IN (' . $marks . ') GROUP BY `project_id`', ['published', ...$ids])->fetchAll(\PDO::FETCH_ASSOC) as $count) {
                $counts[(int) $count['project_id']] = (int) $count['n'];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $residences = $counts[(int) $row->id] ?? 0;
            $out[] = [
                'title' => localized($row->title ?? ''),
                'url' => Site::url('developments/' . $row->slug),
                'location' => self::location($row),
                'progress' => __((string) (Catalog::projectProgress()[$row->progress ?? ''] ?? '')),
                'residences' => $residences,
                'cover' => function_exists('media') ? media($row->cover_id ?? null) : null,
            ];
        }
        return $out;
    }

    /** The newest published articles. */
    public static function articles(int $limit = 3): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('articles')->where('publish_status', '=', 'published')
            ->whereRaw('(`published_at` IS NULL OR `published_at` <= ?)', [date('Y-m-d H:i:s')])->orderBy('published_at', 'DESC')->limit($limit)->get();
        self::preload($rows);
        return array_map(static fn (object $row): array => [
            'title' => localized($row->title ?? ''),
            'excerpt' => localized($row->excerpt ?? ''),
            'url' => Site::url('insights/' . $row->slug),
            'date' => self::date((string) ($row->published_at ?? '')),
            'datetime' => substr((string) ($row->published_at ?? ''), 0, 10),
            'cover' => function_exists('media') ? media($row->cover_id ?? null) : null,
        ], $rows);
    }

    /** Visible team members in the editors' order. */
    public static function team(): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('team_members')->where('visible', '=', 1)->orderBy('sort')->orderBy('id')->get();
        if (function_exists('media_preload')) {
            media_preload(array_values(array_filter(array_map(static fn (object $row): mixed => $row->photo_id ?? null, $rows))));
        }
        return array_map(static fn (object $row): array => [
            'name' => (string) $row->name,
            'role' => localized($row->role ?? ''),
            'bio' => localized($row->bio ?? ''),
            'email' => (string) ($row->email ?? ''),
            'linkedin' => (string) ($row->linkedin ?? ''),
            'photo' => function_exists('media') ? media($row->photo_id ?? null) : null,
        ], $rows);
    }

    /** What a property card shows. */
    public static function card(object $row): array
    {
        $status = (string) ($row->status ?? '');
        $price = (int) ($row->price_on_request ?? 0) === 1
            ? __('fh.price_on_request')
            : (($row->price ?? null) !== null && $row->price !== '' ? Site::price($row->price) . ($status === 'for_rent' ? ' ' . __('fh.per_month') : '') : '');
        return [
            'id' => (int) $row->id,
            'title' => localized($row->title ?? ''),
            'url' => Site::url('properties/' . $row->slug),
            'reference' => (string) ($row->reference ?? ''),
            'status' => __((string) (Catalog::listingStatuses()[$status] ?? '')),
            'status_key' => $status,
            'location' => self::location($row),
            'type' => self::name('property_types', (int) ($row->property_type_id ?? 0)),
            'beds' => ($row->bedrooms ?? null) !== null ? (int) $row->bedrooms : null,
            'baths' => ($row->bathrooms ?? null) !== null ? (int) $row->bathrooms : null,
            'area' => ($row->area_m2 ?? null) !== null ? Site::number($row->area_m2, 1) . ' m²' : '',
            'price' => $price,
            'cover' => function_exists('media') ? media($row->cover_id ?? null) : null,
        ];
    }

    /** "Kolonaki, Athens": the area and the city in the visitor's language. */
    public static function location(object $row): string
    {
        $area = localized($row->area ?? '');
        $city = self::name('cities', (int) ($row->city_id ?? 0));
        return implode(Site::isRtl() ? '، ' : ', ', array_filter([$area, $city], static fn (string $part): bool => $part !== ''));
    }

    /** @return array<int, string> A lookup table's names (cities, property types) in their order, for filters. */
    public static function lookup(string $table): array
    {
        $out = [];
        foreach ((self::db()?->table($table)->select(['id', 'name'])->orderBy('sort')->orderBy('id')->get() ?? []) as $row) {
            $out[(int) $row->id] = localized($row->name ?? '');
        }
        return $out;
    }

    /** A lookup's name (city, property type) in the visitor's language. */
    public static function name(string $table, int $id): string
    {
        if ($id < 1) {
            return '';
        }
        $lang = Site::lang();
        if (!isset(self::$names[$table . ':' . $lang])) {
            self::$names[$table . ':' . $lang] = [];
            foreach ((self::db()?->table($table)->select(['id', 'name'])->get() ?? []) as $row) {
                self::$names[$table . ':' . $lang][(int) $row->id] = localized($row->name ?? '');
            }
        }
        return self::$names[$table . ':' . $lang][$id] ?? '';
    }

    /** 7 November 2026 in the visitor's language. */
    public static function date(string $value): string
    {
        $time = strtotime($value);
        if ($time === false) {
            return '';
        }
        $months = [
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'ar' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
            'el' => ['Ιανουαρίου', 'Φεβρουαρίου', 'Μαρτίου', 'Απριλίου', 'Μαΐου', 'Ιουνίου', 'Ιουλίου', 'Αυγούστου', 'Σεπτεμβρίου', 'Οκτωβρίου', 'Νοεμβρίου', 'Δεκεμβρίου'],
        ][Site::lang()] ?? null;
        $months ??= ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return (int) date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
    }
}
