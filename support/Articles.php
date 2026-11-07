<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\App;

/**
 * Insights & Property Guide (Stage 3c): published articles whose date has
 * come, by category, newest first.
 */
final class Articles
{
    public const PER_PAGE = 9;

    /** "insights/<slug>", the slug encoded for the address. */
    public static function path(string $slug): string
    {
        return 'insights/' . rawurlencode($slug);
    }

    /** A published article by its slug, or null (drafts and future dates answer 404). */
    public static function bySlug(string $slug): ?object
    {
        if (preg_match(Listings::SLUG, $slug) !== 1) {
            return null;
        }
        $rows = self::published()?->where('slug', '=', $slug)->limit(1)->get() ?? [];
        return $rows[0] ?? null;
    }

    /**
     * One page of articles. $category '' = all; $exclude leaves out the featured one.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function page(string $category, int $page, int $exclude = 0, int $perPage = self::PER_PAGE): array
    {
        $count = self::published($category);
        $list = self::published($category);
        if ($count === null || $list === null) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        if ($exclude > 0) {
            $count->where('id', '<>', $exclude);
            $list->where('id', '<>', $exclude);
        }
        $total = $count->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = $list->orderBy('published_at', 'DESC')->orderBy('id', 'DESC')->limit($perPage)->offset(($page - 1) * $perPage)->get();
        return ['items' => self::cards($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** The newest featured article (of the category, when one is chosen), or null. */
    public static function featured(string $category = ''): ?array
    {
        $rows = self::published($category)?->where('featured', '=', 1)->orderBy('published_at', 'DESC')->orderBy('id', 'DESC')->limit(1)->get() ?? [];
        return $rows !== [] ? self::cards($rows)[0] : null;
    }

    /** Up to $limit other articles: the same category first, then the newest. */
    public static function related(object $row, int $limit = 3): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->query(
            'SELECT * FROM `articles` WHERE `publish_status` = ? AND (`published_at` IS NULL OR `published_at` <= ?) AND `id` <> ?'
            . ' ORDER BY (`category` = ?) DESC, `published_at` DESC, `id` DESC LIMIT ' . $limit,
            ['published', date('Y-m-d H:i:s'), (int) $row->id, (string) ($row->category ?? '')]
        )->fetchAll(\PDO::FETCH_OBJ);
        return self::cards($rows);
    }

    /** Number of published articles per category. @return array<string, int> */
    public static function counts(): array
    {
        $out = [];
        foreach ((self::db()?->query('SELECT `category`, COUNT(*) AS `n` FROM `articles` WHERE `publish_status` = ? AND (`published_at` IS NULL OR `published_at` <= ?) GROUP BY `category`', ['published', date('Y-m-d H:i:s')])->fetchAll(\PDO::FETCH_ASSOC) ?? []) as $r) {
            $out[(string) $r['category']] = (int) $r['n'];
        }
        return $out;
    }

    /** What an article card shows. @param list<object> $rows */
    public static function cards(array $rows): array
    {
        if (function_exists('media_preload')) {
            media_preload(array_values(array_filter(array_map(static fn (object $r): mixed => $r->cover_id ?? null, $rows))));
        }
        return array_map(static fn (object $row): array => self::card($row), $rows);
    }

    public static function card(object $row): array
    {
        $category = (string) ($row->category ?? '');
        return [
            'id' => (int) $row->id,
            'title' => localized($row->title ?? ''),
            'excerpt' => localized($row->excerpt ?? ''),
            'url' => Site::url(self::path((string) $row->slug)),
            'category' => $category,
            'category_label' => isset(Catalog::articleCategories()[$category]) ? __(Catalog::articleCategories()[$category]) : '',
            'date' => Listings::date((string) ($row->published_at ?? '')),
            'datetime' => substr((string) ($row->published_at ?? ''), 0, 10),
            'read' => __('fh.min_read', ['n' => (string) self::minutes(localized($row->body ?? ''))]),
            'cover' => function_exists('media') ? media($row->cover_id ?? null) : null,
        ];
    }

    /** Reading time in minutes (about 200 words a minute, at least 1). */
    public static function minutes(string $html): int
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
        return max(1, (int) round($words / 200));
    }

    /** Published articles whose date has come, optionally of one category. */
    private static function published(string $category = ''): ?\FloCMS\Core\Database
    {
        $db = self::db();
        if ($db === null) {
            return null;
        }
        $query = $db->table('articles')->where('publish_status', '=', 'published')->whereRaw('(`published_at` IS NULL OR `published_at` <= ?)', [date('Y-m-d H:i:s')]);
        if ($category !== '') {
            $query->where('category', '=', $category);
        }
        return $query;
    }

    private static function db(): ?\FloCMS\Core\Database
    {
        try {
            return App::db();
        } catch (\Throwable) {
            return null;
        }
    }
}
