<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Core\App;

/**
 * Developments (Stage 3c): the list, one development, its residences (the
 * published listings linked to it) and its progress updates.
 */
final class Projects
{
    /** Residences that are no longer for sale. */
    private const CLOSED = ['sold', 'rented'];

    /** "developments/<slug>", the slug encoded for the address. */
    public static function path(string $slug): string
    {
        return 'developments/' . rawurlencode($slug);
    }

    /** A published development by its slug, or null (drafts answer 404). */
    public static function bySlug(string $slug): ?object
    {
        if (preg_match(Listings::SLUG, $slug) !== 1) {
            return null;
        }
        $rows = self::db()?->table('projects')->where('slug', '=', $slug)->where('publish_status', '=', 'published')->limit(1)->get() ?? [];
        return $rows[0] ?? null;
    }

    /**
     * Published developments for the list, in the editors' order, each with
     * its residence counts and lowest available price. $progress filters
     * (planned / under_construction / completed), '' for all.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(string $progress = ''): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $query = $db->table('projects')->where('publish_status', '=', 'published');
        if ($progress !== '') {
            $query->where('progress', '=', $progress);
        }
        $rows = $query->orderBy('sort')->orderBy('id', 'DESC')->get();
        if (function_exists('media_preload')) {
            media_preload(array_values(array_filter(array_map(static fn (object $r): mixed => $r->cover_id ?? null, $rows))));
        }
        $stats = self::stats(array_map(static fn (object $r): int => (int) $r->id, $rows));
        return array_map(static fn (object $row): array => self::card($row, $stats[(int) $row->id] ?? ['total' => 0, 'available' => 0, 'from' => 0.0]), $rows);
    }

    /** Number of published developments per progress value. @return array<string, int> */
    public static function counts(): array
    {
        $out = [];
        foreach ((self::db()?->query('SELECT `progress`, COUNT(*) AS `n` FROM `projects` WHERE `publish_status` = ? GROUP BY `progress`', ['published'])->fetchAll(\PDO::FETCH_ASSOC) ?? []) as $r) {
            $out[(string) $r['progress']] = (int) $r['n'];
        }
        return $out;
    }

    /** What a development card shows. */
    public static function card(object $row, array $stats): array
    {
        $progress = (string) ($row->progress ?? 'planned');
        $completion = localized($row->expected_completion ?? '');
        return [
            'id' => (int) $row->id,
            'title' => localized($row->title ?? ''),
            'summary' => localized($row->summary ?? ''),
            'url' => Site::url(self::path((string) $row->slug)),
            'location' => Listings::location($row),
            'progress' => $progress,
            'status' => __((string) (Catalog::projectProgress()[$progress] ?? '')),
            'percent' => max(0, min(100, (int) ($row->progress_percent ?? 0))),
            'completion' => $completion,
            'total' => $stats['total'],
            'available' => $stats['available'],
            'from' => $stats['from'] > 0 ? Site::price($stats['from']) : '',
            'featured' => (int) ($row->featured ?? 0) === 1,
            'cover' => function_exists('media') ? media($row->cover_id ?? null) : null,
        ];
    }

    /**
     * Residence counts and the lowest available price per development, in one query.
     *
     * @param list<int> $ids
     * @return array<int, array{total: int, available: int, from: float}>
     */
    public static function stats(array $ids): array
    {
        $db = self::db();
        if ($db === null || $ids === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $closed = implode(', ', array_fill(0, count(self::CLOSED), '?'));
        $sql = 'SELECT `project_id`, COUNT(*) AS `total`,'
            . ' SUM(CASE WHEN `status` NOT IN (' . $closed . ') THEN 1 ELSE 0 END) AS `available`,'
            . ' MIN(CASE WHEN `status` NOT IN (' . $closed . ') AND `price_on_request` = 0 AND `price` > 0 THEN `price` END) AS `from_price`'
            . ' FROM `listings` WHERE `publish_status` = ? AND `project_id` IN (' . $marks . ') GROUP BY `project_id`';
        $out = [];
        foreach ($db->query($sql, [...self::CLOSED, ...self::CLOSED, 'published', ...$ids])->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['project_id']] = ['total' => (int) $r['total'], 'available' => (int) $r['available'], 'from' => (float) ($r['from_price'] ?? 0)];
        }
        return $out;
    }

    /**
     * The residences table: published listings of the development, by reference.
     *
     * @return list<array<string, mixed>>
     */
    public static function units(int $projectId): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('listings')->where('project_id', '=', $projectId)->where('publish_status', '=', 'published')->orderBy('reference')->get();
        $planIds = [];
        foreach ($rows as $row) {
            $first = MediaPicker::ids($row->floor_plans ?? '')[0] ?? null;
            if ($first !== null) { $planIds[] = $first; }
        }
        if ($planIds !== [] && function_exists('media_preload')) {
            media_preload($planIds);
        }
        $out = [];
        foreach ($rows as $row) {
            $status = (string) $row->status;
            $open = !in_array($status, self::CLOSED, true);
            $planId = MediaPicker::ids($row->floor_plans ?? '')[0] ?? null;
            $plan = $planId !== null && function_exists('media') ? media($planId) : null;
            $brochure = trim((string) ($row->brochure ?? ''));
            $outdoor = ($row->outdoor_m2 ?? null) !== null && (float) $row->outdoor_m2 > 0 ? Site::number((float) $row->outdoor_m2, 1) . ' m²' : '';
            $out[] = [
                'id' => (int) $row->id,
                'ref' => (string) $row->reference,
                'title' => localized($row->title ?? ''),
                'url' => Site::url(Listings::path((string) $row->slug)),
                'floor' => localized($row->floor ?? ''),
                'type' => Listings::name('property_types', (int) ($row->property_type_id ?? 0)),
                'area' => ($row->area_m2 ?? null) !== null && (float) $row->area_m2 > 0 ? Site::number((float) $row->area_m2, 1) . ' m²' : '',
                'beds' => ($row->bedrooms ?? null) !== null ? (string) (int) $row->bedrooms : '',
                'outdoor' => $outdoor,
                'price' => $open ? Listings::priceLabel($row) : __('fh.unit.sold'),
                'open' => $open,
                'state' => $open ? 'available' : 'sold',
                'state_label' => __($open ? 'fh.unit.available' : 'fh.unit.sold'),
                'plan' => $plan !== null && $plan->isImage() ? $plan : null,
                'pdf' => $brochure !== '' && class_exists(\FloCMS\Admin\Uploads\Uploads::class) ? \FloCMS\Admin\Uploads\Uploads::publicUrl($brochure) : '',
            ];
        }
        return $out;
    }

    /**
     * Published progress updates, newest first, with their photos.
     *
     * @return list<array<string, mixed>>
     */
    public static function updates(int $projectId, int $limit = 6): array
    {
        $db = self::db();
        if ($db === null) {
            return [];
        }
        $rows = $db->table('project_updates')->where('project_id', '=', $projectId)->where('publish_status', '=', 'published')
            ->orderBy('update_date', 'DESC')->orderBy('id', 'DESC')->limit($limit)->get();
        return array_map(static function (object $row): array {
            $photos = function_exists('media_list') ? array_values(array_filter(media_list($row->photos ?? ''), static fn ($i) => $i->isImage())) : [];
            $date = (string) ($row->update_date ?? '');
            return [
                'title' => localized($row->title ?? ''),
                'body' => localized($row->body ?? ''),
                'date' => $date !== '' ? self::month($date) : '',
                'datetime' => substr($date, 0, 10),
                'photos' => $photos,
            ];
        }, $rows);
    }

    /** The construction stages entered in the admin, in order. @return list<array{name: string, status: string, label: string, date: string}> */
    public static function stages(object $row): array
    {
        $out = [];
        foreach (repeater_items($row->stages ?? '') as $stage) {
            $name = localized($stage['name'] ?? '');
            if ($name === '') { continue; }
            $status = (string) ($stage['status'] ?? 'upcoming');
            $status = array_key_exists($status, Catalog::stageStatuses()) ? $status : 'upcoming';
            $out[] = ['name' => $name, 'status' => $status, 'label' => __(Catalog::stageStatuses()[$status]), 'date' => localized($stage['date'] ?? '')];
        }
        return $out;
    }

    /** The key facts repeater as short lines ("Underground parking" or "Parking: 2 spaces"). @return list<string> */
    public static function highlights(object $row): array
    {
        $out = [];
        foreach (repeater_items($row->key_facts ?? '') as $fact) {
            $label = localized($fact['label'] ?? '');
            $value = localized($fact['value'] ?? '');
            if ($label === '' && $value === '') { continue; }
            $out[] = $label !== '' && $value !== '' ? $label . ': ' . $value : ($label !== '' ? $label : $value);
        }
        return $out;
    }

    /** "October 2026" in the visitor's language. */
    public static function month(string $value): string
    {
        $time = strtotime($value);
        if ($time === false) {
            return '';
        }
        if (Site::lang() === 'el') { // a month on its own takes the nominative ("Οκτώβριος 2026")
            $months = ['Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'];
            return $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
        }
        $date = Listings::date($value);
        return $date !== '' ? (string) preg_replace('/^\d{1,2}\s+/u', '', $date) : '';
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
