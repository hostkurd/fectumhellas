<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Reactive\Reactive;
use FloCMS\Support\Listings;
use FloCMS\Support\Shortlist;
use FloCMS\Support\Site;

/**
 * /{lang}/saved?ids=…: the visitor's saved properties (approved Saved mockup).
 * The list lives in the browser (localStorage); site.js keeps the address in
 * step with it. "Send list to our team" is the flocms-reactive component
 * App\Components\SavedInquiry. Search engines don't index it.
 */
class SavedController extends Controller
{
    public function index(): void
    {
        $requested = Shortlist::ids($_GET['ids'] ?? '', Shortlist::MAX_SAVED);
        $rows = Shortlist::listings($requested);
        $shown = array_map(static fn (object $r): int => (int) $r->id, $rows);
        Site::meta([
            'title' => __('fh.sv.title'),
            'description' => __('fh.sv.lead'),
            'path' => 'saved',
            'robots' => 'noindex, follow',
            'jsonld' => [Site::breadcrumbLd([__('fh.home') => '', __('fh.sv.title') => 'saved'])],
        ]);
        $this->data['cards'] = array_map([Listings::class, 'card'], $rows);
        $this->data['requested'] = $requested;
        $this->data['shown'] = $shown;
        $this->data['form'] = $rows !== [] ? Site::reactiveAssets() . Reactive::mount('saved', 'saved-inquiry', [
            'ids' => implode(',', $shown), 'count' => count($shown),
            'page' => (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
        ]) : '';
    }
}
