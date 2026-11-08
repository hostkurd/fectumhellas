<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\App;

/**
 * Compare and Saved pages (Stage 3d, approved Compare and Saved mockups).
 * The visitor's lists live in the browser (localStorage); the pages get the
 * property ids in the address (?ids=12,7,30) and show the published ones in
 * that order.
 */
final class Shortlist
{
    /** At most three properties side by side (the compare bar's limit). */
    public const MAX_COMPARE = 3;
    /** A saved list longer than this is cut (the admin shows the same number). */
    public const MAX_SAVED = 50;

    /** "12,7,x,12,30" => [12, 7, 30]: positive ids, no repeats, at most $max. @return list<int> */
    public static function ids(mixed $raw, int $max): array
    {
        if (!is_string($raw) || $raw === '' || strlen($raw) > 2000) {
            return [];
        }
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part) && strlen($part) <= 9 && (int) $part > 0 && !in_array((int) $part, $out, true)) {
                $out[] = (int) $part;
                if (count($out) >= $max) {
                    break;
                }
            }
        }
        return $out;
    }

    /** The published properties among $ids, in the order given. @param list<int> $ids @return list<object> */
    public static function listings(array $ids): array
    {
        $db = App::db();
        if ($ids === [] || $db === null) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $rows = $db->query('SELECT * FROM `listings` WHERE `publish_status` = \'published\' AND `id` IN (' . $marks . ')', $ids)->fetchAll(\PDO::FETCH_OBJ);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = $row;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        if (function_exists('media_preload')) {
            media_preload(array_values(array_filter(array_map(static fn (object $row): mixed => $row->cover_id ?? null, $out))));
        }
        return $out;
    }

    /** /compare?ids=… or /saved?ids=… (no ids: the bare address). @param list<int> $ids */
    public static function url(string $page, array $ids): string
    {
        return Site::url($page) . ($ids !== [] ? '?ids=' . implode(',', $ids) : '');
    }

    /**
     * The comparison table: one column per property, rows in groups (price,
     * property, features). Empty rows are left out; a row "differs" when its
     * values are not all the same.
     *
     * @param list<object> $rows
     * @return array{props: list<array<string, mixed>>, groups: list<array{title: string, rows: list<array<string, mixed>>}>}
     */
    public static function compare(array $rows): array
    {
        if ($rows === []) {
            return ['props' => [], 'groups' => []];
        }
        $lang = Site::lang();
        $multi = count($rows) > 1;
        $props = [];
        $area = [];
        $perM2 = [];
        foreach ($rows as $i => $row) {
            $card = Listings::card($row);
            $status = (string) ($row->status ?? '');
            $a = ($row->area_m2 ?? null) !== null ? (float) $row->area_m2 : 0.0;
            $price = ($row->price ?? null) !== null && $row->price !== '' && (int) ($row->price_on_request ?? 0) === 0 ? (float) $row->price : 0.0;
            $area[$i] = $a;
            // Per m² for homes for sale only (a monthly rent per m² compares nothing)
            $perM2[$i] = $price > 0 && $a > 0 && in_array($status, ['for_sale', 'off_plan'], true) ? round($price / $a) : 0.0;
            $props[] = $card + ['id' => (int) $row->id];
        }
        $low = array_filter($perM2, static fn (float $v): bool => $v > 0);
        $minPerM2 = count($low) > 1 ? min($low) : 0.0;
        $big = array_filter($area, static fn (float $v): bool => $v > 0);
        $maxArea = count($big) > 1 ? max($big) : 0.0;

        $text = static function (string $label, array $values, ?callable $best = null): array {
            $cells = [];
            foreach ($values as $i => $v) {
                $cells[] = ['type' => 'text', 'text' => (string) $v, 'best' => $best !== null ? $best($i) : ''];
            }
            return ['label' => $label, 'cells' => $cells];
        };
        $col = static fn (callable $fn): array => array_map($fn, $rows, array_keys($rows));

        $groups = [
            ['title' => __('fh.cmp.price'), 'rows' => [
                $text(__('fh.cmp.price_row'), array_column($props, 'price')),
                $text(__('fh.cmp.per_m2'), array_map(static fn (float $v): string => $v > 0 ? Site::price($v) : '', $perM2),
                    static fn (int $i): string => $minPerM2 > 0 && $perM2[$i] === $minPerM2 ? __('fh.cmp.lowest') : ''),
            ]],
            ['title' => __('fh.cmp.property'), 'rows' => [
                $text(__('fh.fact.type'), array_column($props, 'type')),
                $text(__('fh.fact.status'), array_column($props, 'status')),
                $text(__('fh.fact.area'), array_column($props, 'area'), static fn (int $i): string => $maxArea > 0 && $area[$i] === $maxArea ? __('fh.cmp.largest') : ''),
                $text(__('fh.fact.bedrooms'), $col(static fn (object $r): string => ($r->bedrooms ?? null) !== null ? (string) (int) $r->bedrooms : '')),
                $text(__('fh.fact.bathrooms'), $col(static fn (object $r): string => ($r->bathrooms ?? null) !== null ? (string) (int) $r->bathrooms : '')),
                $text(__('fh.fact.floor'), $col(static fn (object $r): string => localized($r->floor ?? ''))),
                $text(__('fh.fact.year'), $col(static fn (object $r): string => self::build($r))),
                $text(__('fh.fact.energy'), $col(static fn (object $r): string => (string) ($r->energy_class ?? '') !== '' ? Catalog::energyClassLabel((string) $r->energy_class, $lang) : '')),
                $text(__('fh.fact.parking'), $col(static fn (object $r): string => localized($r->parking ?? ''))),
                $text(__('fh.fact.outdoor'), $col(static fn (object $r): string => ($r->outdoor_m2 ?? null) ? Site::number((float) $r->outdoor_m2, 1) . ' m²' : '')),
            ]],
            ['title' => __('fh.cmp.features'), 'rows' => self::features($rows)],
        ];

        foreach ($groups as $g => $group) {
            $kept = [];
            foreach ($group['rows'] as $row) {
                $values = array_map(static fn (array $c): string => $c['type'] === 'text' ? trim($c['text']) : ($c['yes'] ? '1' : '0'), $row['cells']);
                if ($row['cells'][0]['type'] === 'text' && implode('', $values) === '') {
                    continue; // nobody has this detail
                }
                $row['differs'] = $multi && count(array_unique($values)) > 1;
                $kept[] = $row;
            }
            $groups[$g]['rows'] = $kept;
        }
        return ['props' => $props, 'groups' => array_values(array_filter($groups, static fn (array $g): bool => $g['rows'] !== []))];
    }

    /** "New build · 2026", "Under construction · 2027", "2004" or ''. */
    private static function build(object $row): string
    {
        $year = (int) ($row->construction_year ?? 0);
        if ((int) ($row->under_construction ?? 0) === 1) {
            return __('fh.build.construction') . ($year > 0 ? ' · ' . $year : '');
        }
        if ($year > 0 && $year >= (int) date('Y') - Listings::NEW_BUILD_YEARS) {
            return __('fh.build.new') . ' · ' . $year;
        }
        return $year > 0 ? (string) $year : '';
    }

    /** One yes/no row per amenity any of the properties has, in the editors' order. @param list<object> $rows */
    private static function features(array $rows): array
    {
        $db = App::db();
        if ($rows === [] || $db === null) {
            return [];
        }
        $ids = array_map(static fn (object $r): int => (int) $r->id, $rows);
        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $has = [];
        $names = [];
        foreach ($db->query('SELECT la.`listing_id`, a.`id`, a.`name` FROM `listing_amenity` la JOIN `amenities` a ON a.`id` = la.`amenity_id` WHERE la.`listing_id` IN (' . $marks . ') ORDER BY a.`sort`, a.`id`', $ids)->fetchAll(\PDO::FETCH_OBJ) as $r) {
            $name = localized($r->name ?? '');
            if ($name === '') {
                continue;
            }
            $names[(int) $r->id] = $name;
            $has[(int) $r->id][(int) $r->listing_id] = true;
        }
        $out = [];
        foreach ($names as $amenity => $name) {
            $out[] = ['label' => $name, 'cells' => array_map(static fn (int $id): array => ['type' => 'yesno', 'yes' => isset($has[$amenity][$id]), 'best' => ''], $ids)];
        }
        return $out;
    }
}
