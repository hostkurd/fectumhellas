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
        $price = self::priceLabel($row);
        return [
            'id' => (int) $row->id,
            'title' => localized($row->title ?? ''),
            'url' => Site::url(self::path($row->slug)),
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

    /** The price as shown: "€1,200,000", "€1,800 / month", "Price on request", or "Sold" / "Rented". */
    public static function priceLabel(object $row): string
    {
        $status = (string) ($row->status ?? '');
        if (in_array($status, ['sold', 'rented'], true)) {
            return __((string) Catalog::listingStatuses()[$status]);
        }
        if ((int) ($row->price_on_request ?? 0) === 1) {
            return __('fh.price_on_request');
        }
        if (($row->price ?? null) === null || $row->price === '') {
            return '';
        }
        return Site::price($row->price) . ($status === 'for_rent' ? ' ' . __('fh.per_month') : '');
    }

    /**
     * Property search (Stage 3 D3). Filters: status tab, city, type, budget
     * (max price), bedrooms (min), area (min m²), build (new/construction/
     * resale), features (amenity ids, all required), closed (include sold and
     * rented), sort and page. Returns the cards of one page, the total and
     * the map points of every match (up to 300).
     *
     * @param array{status?: string, city?: int, type?: int, max?: int, beds?: int, area?: int, build?: string, features?: list<int>, closed?: bool, sort?: string, page?: int} $f
     * @return array{cards: list<array<string, mixed>>, total: int, page: int, pages: int, points: list<array<string, mixed>>}
     */
    public static function search(array $f, int $perPage = 12): array
    {
        $db = self::db();
        $empty = ['cards' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'points' => []];
        if ($db === null) {
            return $empty;
        }
        [$where, $params] = self::searchWhere($f);
        $sql = ' FROM `listings` WHERE ' . implode(' AND ', $where);
        $total = (int) $db->query('SELECT COUNT(*)' . $sql, $params)->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($f['page'] ?? 1)), $pages);
        $order = match ($f['sort'] ?? 'new') {
            'price_asc' => '`price_on_request` ASC, `price` IS NULL ASC, `price` ASC, `id` DESC',
            'price_desc' => '`price_on_request` ASC, `price` IS NULL ASC, `price` DESC, `id` DESC',
            default => '`id` DESC',
        };
        $rows = $db->query('SELECT *' . $sql . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params)->fetchAll(\PDO::FETCH_OBJ);
        self::preload($rows);
        $points = [];
        foreach ($db->query('SELECT `id`, `slug`, `status`, `price`, `price_on_request`, `latitude`, `longitude`' . $sql . ' AND `latitude` IS NOT NULL AND `longitude` IS NOT NULL ORDER BY ' . $order . ' LIMIT 300', $params)->fetchAll(\PDO::FETCH_OBJ) as $p) {
            // Rounded (about 100 m): the public map shows the area, not the exact address
            $points[] = ['id' => (int) $p->id, 'lat' => round((float) $p->latitude, 3), 'lng' => round((float) $p->longitude, 3), 'label' => self::shortPrice($p), 'url' => Site::url(self::path($p->slug))];
        }
        return ['cards' => array_map([self::class, 'card'], $rows), 'total' => $total, 'page' => $page, 'pages' => $pages, 'points' => $points];
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private static function searchWhere(array $f): array
    {
        $where = ["`publish_status` = 'published'"];
        $params = [];
        $tab = (string) ($f['status'] ?? '');
        $closed = !empty($f['closed']);
        $statuses = match ($tab) {
            'for_sale' => $closed ? ['for_sale', 'sold'] : ['for_sale'],
            'for_rent' => $closed ? ['for_rent', 'rented'] : ['for_rent'],
            'off_plan' => ['off_plan'],
            default => $closed ? array_keys(Catalog::listingStatuses()) : Catalog::availableStatuses(),
        };
        $where[] = '`status` IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')';
        array_push($params, ...$statuses);
        if (($f['city'] ?? 0) > 0) { $where[] = '`city_id` = ?'; $params[] = (int) $f['city']; }
        if (($f['type'] ?? 0) > 0) { $where[] = '`property_type_id` = ?'; $params[] = (int) $f['type']; }
        if (($f['max'] ?? 0) > 0) { $where[] = '`price_on_request` = 0 AND `price` IS NOT NULL AND `price` <= ?'; $params[] = (int) $f['max']; }
        if (($f['beds'] ?? 0) > 0) { $where[] = '`bedrooms` >= ?'; $params[] = (int) $f['beds']; }
        if (($f['area'] ?? 0) > 0) { $where[] = '`area_m2` >= ?'; $params[] = (int) $f['area']; }
        $recent = (int) date('Y') - self::NEW_BUILD_YEARS;
        match ((string) ($f['build'] ?? '')) {
            'construction' => $where[] = '`under_construction` = 1',
            'new' => [$where[] = '`under_construction` = 0 AND `construction_year` >= ?', $params[] = $recent],
            'resale' => [$where[] = '`under_construction` = 0 AND (`construction_year` IS NULL OR `construction_year` < ?)', $params[] = $recent],
            default => null,
        };
        foreach (array_slice(array_values(array_unique(array_filter(array_map('intval', $f['features'] ?? []), static fn (int $id): bool => $id > 0))), 0, 12) as $amenity) {
            $where[] = 'EXISTS (SELECT 1 FROM `listing_amenity` la WHERE la.`listing_id` = `listings`.`id` AND la.`amenity_id` = ?)';
            $params[] = $amenity;
        }
        return [$where, $params];
    }

    /** A home built in the last five years and finished counts as a "new build" in the filter. */
    public const NEW_BUILD_YEARS = 5;

    /** "€1.2M", "€640K", "€1,800/mo" for map pins. */
    public static function shortPrice(object $row): string
    {
        $status = (string) ($row->status ?? '');
        if (in_array($status, ['sold', 'rented'], true)) {
            return __((string) Catalog::listingStatuses()[$status]);
        }
        if ((int) ($row->price_on_request ?? 0) === 1 || ($row->price ?? null) === null) {
            return '€ —';
        }
        $n = (float) $row->price;
        if ($status === 'for_rent') {
            return Site::price($n) . __('fh.per_month_short');
        }
        return $n >= 1000000 ? '€' . rtrim(rtrim(number_format($n / 1000000, 2, '.', ''), '0'), '.') . 'M' : '€' . (int) round($n / 1000) . 'K';
    }

    /** Slugs as the admin makes them (Str::slug keeps Greek and Arabic letters). */
    public const SLUG = '/^[\p{L}\p{N}][\p{L}\p{N}-]{0,190}$/u';

    /** "properties/<slug>", the slug encoded for the address. */
    public static function path(string $slug): string
    {
        return 'properties/' . rawurlencode($slug);
    }

    /** A published property by its slug, or null (drafts answer 404). */
    public static function bySlug(string $slug): ?object
    {
        if (preg_match(self::SLUG, $slug) !== 1) {
            return null;
        }
        $rows = self::db()?->table('listings')->where('slug', '=', $slug)->where('publish_status', '=', 'published')->limit(1)->get() ?? [];
        return $rows[0] ?? null;
    }

    /** The amenities of a listing, in its order, in the visitor's language. */
    public static function amenities(int $listingId): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->query('SELECT a.`id`, a.`name` FROM `listing_amenity` la JOIN `amenities` a ON a.`id` = la.`amenity_id` WHERE la.`listing_id` = ? ORDER BY la.`sort`, a.`sort`, a.`id`', [$listingId])->fetchAll(\PDO::FETCH_OBJ);
        return array_values(array_filter(array_map(static fn (object $r): string => localized($r->name ?? ''), $rows), static fn (string $n): bool => $n !== ''));
    }

    /** Up to $limit other available properties: same city and type first, then same city or type. */
    public static function similar(object $row, int $limit = 3): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $statuses = Catalog::availableStatuses();
        $marks = implode(', ', array_fill(0, count($statuses), '?'));
        $rows = $db->query(
            'SELECT * FROM `listings` WHERE `publish_status` = \'published\' AND `id` <> ? AND `status` IN (' . $marks . ') AND (`city_id` = ? OR `property_type_id` = ?)'
            . ' ORDER BY (`city_id` = ?) + (`property_type_id` = ?) DESC, ABS(COALESCE(`price`, 0) - ?) ASC, `id` DESC LIMIT ' . $limit,
            [(int) $row->id, ...$statuses, (int) ($row->city_id ?? 0), (int) ($row->property_type_id ?? 0), (int) ($row->city_id ?? 0), (int) ($row->property_type_id ?? 0), (float) ($row->price ?? 0)]
        )->fetchAll(\PDO::FETCH_OBJ);
        self::preload($rows);
        return array_map([self::class, 'card'], $rows);
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
