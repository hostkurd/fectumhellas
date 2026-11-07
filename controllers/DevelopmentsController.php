<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Core\HttpException;
use FloCMS\Support\Catalog;
use FloCMS\Support\Projects;
use FloCMS\Support\Site;

/**
 * /{lang}/developments: the developments list (approved Developments
 * mockup). ?status=<progress> picks a tab (a plain link, swapped without a
 * reload). One development: FloCMS\Support\DevelopmentPage (fallback).
 */
class DevelopmentsController extends Controller
{
    public function index(): void
    {
        $status = $_GET['status'] ?? '';
        if (!is_string($status) || ($status !== '' && !array_key_exists($status, Catalog::projectProgress()))) {
            throw new HttpException(404);
        }
        $counts = Projects::counts();
        $all = Projects::all();
        $items = $status === '' ? $all : array_values(array_filter($all, static fn (array $d): bool => $d['progress'] === $status));

        // On "All", the first featured development opens the page (else none).
        $featured = null;
        if ($status === '') {
            foreach ($items as $i => $d) {
                if ($d['featured']) { $featured = $d; unset($items[$i]); break; }
            }
            $items = array_values($items);
        }

        $tabs = [['key' => '', 'label' => __('fh.dev.all_developments'), 'count' => count($all), 'url' => Site::url('developments')]];
        $order = array_flip(['under_construction', 'planned', 'completed']); // the mockup's order
        $labels = Catalog::projectProgress();
        uksort($labels, static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
        foreach ($labels as $key => $label) {
            if (($counts[$key] ?? 0) > 0 || $key === $status) {
                $tabs[] = ['key' => $key, 'label' => __($label), 'count' => $counts[$key] ?? 0, 'url' => Site::url('developments') . '?status=' . $key];
            }
        }
        Site::meta([
            'title' => $status !== '' ? __(Catalog::projectProgress()[$status]) . ' · ' . __('fh.nav.developments') : __('fh.nav.developments'),
            'description' => __('fh.dev.list_lead'),
            'path' => 'developments',
            'query' => $status !== '' ? '?status=' . $status : '',
            'jsonld' => [Site::breadcrumbLd([__('fh.home') => '', __('fh.nav.developments') => 'developments'])],
        ]);
        $this->data['tabs'] = $tabs;
        $this->data['status'] = $status;
        $this->data['featured'] = $featured;
        $this->data['items'] = $items;
        $this->data['total'] = count($all);
        $this->data['available'] = array_sum(array_map(static fn (array $d): int => $d['progress'] === 'planned' ? 0 : $d['available'], $all));
    }
}
