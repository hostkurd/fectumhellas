<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Core\HttpException;
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
        $raw = $_GET['category'] ?? '';
        if (!is_string($raw) || ($raw !== '' && !array_key_exists($raw, Catalog::articleCategories()))) {
            throw new HttpException(404);
        }
        $category = $raw;
        $rawPage = $_GET['page'] ?? '1';
        if (!is_string($rawPage) || !ctype_digit($rawPage) || (int) $rawPage < 1) {
            throw new HttpException(404);
        }
        $page = (int) $rawPage;
        $counts = Articles::counts();

        // The featured article opens the first page and is left out of the grid on every page,
        // so the pages never shift. No featured article chosen ("All" only): the newest one.
        $featured = Articles::featured($category);
        if ($featured === null && $category === '') {
            $newest = Articles::page('', 1, 0, 1)['items'];
            $featured = $newest[0] ?? null;
        }
        $list = Articles::page($category, $page, $featured !== null ? (int) $featured['id'] : 0);
        if ($page > $list['pages']) {
            throw new HttpException(404);
        }
        if ($page > 1) {
            $featured = null;
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
