<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Reactive\Reactive;
use FloCMS\Support\Site;

/**
 * /{lang}/properties: the property search (Stage 3 D3). The search itself is
 * the flocms-reactive component App\Components\PropertySearch; property pages
 * (/properties/<slug>) are answered by FloCMS\Support\PropertyPage (fallback).
 */
class PropertiesController extends Controller
{
    public function index(): void
    {
        // Without JavaScript the features come as search_feature[]=3&search_feature[]=7
        if (isset($_GET['search_feature']) && is_array($_GET['search_feature'])) {
            $_GET['search_features'] = implode(',', array_map('intval', $_GET['search_feature']));
            unset($_GET['search_feature']);
        }
        self::cleanQuery();
        $filtered = false;
        foreach ($_GET as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'search_') && !in_array($key, ['search_page', 'search_sort', 'search_view'], true)
                && !in_array((string) (is_array($value) ? '' : $value), ['', '0', 'all'], true)) {
                $filtered = true;
            }
        }
        $page = max(1, (int) ($_GET['search_page'] ?? 1));
        Site::meta([
            'title' => __('fh.properties_title'),
            'description' => __('fh.properties_lead'),
            'path' => 'properties',
            'query' => !$filtered && $page > 1 ? '?search_page=' . $page : '',
            // Filtered combinations stay out of search results; the list and its pages are indexed.
            'robots' => $filtered ? 'noindex, follow' : '',
            'jsonld' => [Site::breadcrumbLd([__('fh.home') => '', __('fh.nav.properties') => 'properties'])],
        ]);
        $this->data['search'] = Site::reactiveAssets() . Reactive::mount('search', 'search');
    }

    /**
     * flocms-reactive restores the address's search_* values all or nothing:
     * one value its rules refuse would drop every filter. Values that don't
     * fit are removed here (a hand-edited or old link still shows the rest).
     */
    private static function cleanQuery(): void
    {
        $int = static fn (int $max): \Closure => static fn (string $v): ?string => ctype_digit($v) && (int) $v <= $max ? (string) (int) $v : null;
        $in = static fn (array $list): \Closure => static fn (string $v): ?string => in_array($v, $list, true) ? $v : null;
        $rules = [
            'status' => $in(['all', 'for_sale', 'for_rent', 'off_plan']), 'city' => $int(1000000), 'type' => $int(1000000),
            'max' => $int(100000000), 'beds' => $int(20), 'area' => $int(100000), 'build' => $in(['', 'new', 'construction', 'resale']),
            'features' => static fn (string $v): ?string => preg_match('/^[0-9,]{0,200}$/', $v) === 1 ? $v : null,
            'closed' => static fn (string $v): ?string => in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true) ? '1' : '0',
            'sort' => $in(['new', 'price_asc', 'price_desc']),
            'page' => static fn (string $v): ?string => ctype_digit($v) ? (string) min(10000, max(1, (int) $v)) : null,
            'view' => $in(['grid', 'map']),
        ];
        foreach ($rules as $name => $check) {
            $key = 'search_' . $name;
            if (!array_key_exists($key, $_GET)) { continue; }
            $value = is_string($_GET[$key]) ? trim($_GET[$key]) : null;
            $clean = $value === null ? null : $check($value);
            if ($clean === null) { unset($_GET[$key]); } else { $_GET[$key] = $clean; }
        }
    }
}
