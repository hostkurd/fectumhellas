<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Support\Articles;
use FloCMS\Support\Catalog;
use FloCMS\Support\Site;

/**
 * /{lang}/insights: Insights & Property Guide (approved Insights mockup).
 * ?category=<key> picks a tab and ?page=N a page; both are plain links, so
 * the site's navigation swaps them without a reload. Articles themselves
 * (/insights/<slug>) are answered by FloCMS\Support\ArticlePage (fallback).
 */
class InsightsController extends Controller
{
    public function index(): void
    {
        $category = is_string($_GET['category'] ?? null) && array_key_exists($_GET['category'], Catalog::articleCategories()) ? $_GET['category'] : '';
        $page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $counts = Articles::counts();

        // The featured article opens the first page; it is left out of the grid below it.
        $featured = $page === 1 ? Articles::featured($category) : null;
        $list = Articles::page($category, $page, $featured !== null ? (int) $featured['id'] : 0);
        if ($featured === null && $page === 1 && $category === '' && $list['items'] !== []) {
            $featured = array_shift($list['items']); // no featured article chosen: the newest one
        }

        $query = static fn (string $cat, int $p): string => ($q = http_build_query(array_filter(['category' => $cat, 'page' => $p > 1 ? $p : null]))) !== '' ? '?' . $q : '';
        $tabs = [['key' => '', 'label' => __('fh.all_articles'), 'count' => array_sum($counts), 'url' => Site::url('insights')]];
        foreach (Catalog::articleCategories() as $key => $label) {
            if (($counts[$key] ?? 0) > 0 || $key === $category) {
                $tabs[] = ['key' => $key, 'label' => __($label), 'count' => $counts[$key] ?? 0, 'url' => Site::url('insights') . $query($key, 1)];
            }
        }
        $title = $category !== '' ? __(Catalog::articleCategories()[$category]) . ' · ' . __('fh.insights_title') : __('fh.insights_title');
        Site::meta([
            'title' => $list['page'] > 1 ? $title . ' · ' . __('fh.page_n', ['n' => (string) $list['page']]) : $title,
            'description' => __('fh.insights_lead'),
            'path' => 'insights',
            'query' => $query($category, $list['page']),
            'jsonld' => [Site::breadcrumbLd([__('fh.home') => '', __('fh.insights_title') => 'insights'])],
        ]);
        $this->data['tabs'] = $tabs;
        $this->data['category'] = $category;
        $this->data['featured'] = $featured;
        $this->data['list'] = $list;
        $this->data['pageUrl'] = static fn (int $p): string => Site::url('insights') . $query($category, $p);
    }
}
