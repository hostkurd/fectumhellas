<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Controller;
use FloCMS\Support\Shortlist;
use FloCMS\Support\Site;

/**
 * /{lang}/compare?ids=12,7,30: up to three properties side by side (approved
 * Compare mockup). The ids come from the compare bar (localStorage); a shared
 * link opens the same comparison. Search engines don't index it.
 */
class CompareController extends Controller
{
    public function index(): void
    {
        $requested = Shortlist::ids($_GET['ids'] ?? '', Shortlist::MAX_COMPARE);
        $rows = Shortlist::listings($requested);
        $shown = array_map(static fn (object $r): int => (int) $r->id, $rows);
        Site::meta([
            'title' => __('fh.cmp.title'),
            'description' => __('fh.cmp.lead'),
            'path' => 'compare',
            'robots' => 'noindex, follow',
            'jsonld' => [Site::breadcrumbLd([__('fh.home') => '', __('fh.nav.properties') => 'properties', __('fh.cmp.crumb') => 'compare'])],
        ]);
        $this->data['table'] = Shortlist::compare($rows);
        $this->data['requested'] = $requested;
        $this->data['shown'] = $shown;
        $this->data['without'] = static fn (int $id): string => Shortlist::url('compare', array_values(array_diff($shown, [$id])));
    }
}
