<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Template;
use FloCMS\Core\TemplateEngine;
use FloCMS\Reactive\Reactive;

/**
 * /{lang}/developments/<slug>: one development (approved Development
 * mockup), answered through App::fallbackUsing() like PropertyPage.
 * Drafts and unknown slugs: 404.
 */
final class DevelopmentPage
{
    public static function handle(string $path, Request $request): ?Response
    {
        if (!$request->isMethod('GET', 'HEAD') || preg_match('~^developments/([^/]+)/?$~u', trim($path, '/'), $m) !== 1) {
            return null;
        }
        $row = Projects::bySlug($m[1]);
        if ($row === null) {
            return null; // core answers 404
        }
        $content = TemplateEngine::renderFile(VIEWS_PATH . DS . 'developments' . DS . 'show.html', self::data($row));
        $html = TemplateEngine::renderFile(Template::getLayoutPath('default'), ['content' => $content]);
        return Response::html($html);
    }

    /** Everything the development page shows. */
    public static function data(object $row): array
    {
        $id = (int) $row->id;
        $units = Projects::units($id);
        $stats = Projects::stats([$id])[$id] ?? ['total' => 0, 'available' => 0, 'from' => 0.0];
        $card = Projects::card($row, $stats);
        $title = $card['title'];

        // Photos: the cover for the hero, the gallery for the overview (three) and the lightbox
        $cover = $card['cover'];
        $gallery = function_exists('media_list') ? array_values(array_filter(media_list($row->gallery ?? ''), static fn ($i) => $i->isImage())) : [];
        $hero = $cover ?? ($gallery[0] ?? null);

        $brochure = trim((string) ($row->brochure ?? ''));
        $brochureUrl = $brochure !== '' && class_exists(\FloCMS\Admin\Uploads\Uploads::class) ? \FloCMS\Admin\Uploads\Uploads::publicUrl($brochure) : '';
        $lat = ($row->latitude ?? null) !== null ? round((float) $row->latitude, 3) : null;
        $lng = ($row->longitude ?? null) !== null ? round((float) $row->longitude, 3) : null;

        $description = localized($row->description ?? '');
        $summary = localized($row->summary ?? '');
        $metaDescription = localized($row->meta_description ?? '');
        $share = media($row->social_image_id ?? null) ?? $hero;
        $path = Projects::path((string) $row->slug);

        $stages = Projects::stages($row);
        $updates = Projects::updates($id);
        $open = array_values(array_filter($units, static fn (array $u): bool => $u['open']));

        Site::meta([
            'title' => localized($row->meta_title ?? '') !== '' ? localized($row->meta_title) : $title . ' · ' . __('fh.nav.developments'),
            'description' => $metaDescription !== '' ? $metaDescription : ($summary !== '' ? $summary : self::plain($description, 200)),
            'path' => $path,
            'image' => $share !== null ? Site::absolute($share->url()) : '',
            'jsonld' => [self::residenceLd($row, $card, $hero, $summary !== '' ? $summary : self::plain($description, 500)), Site::breadcrumbLd([__('fh.home') => '', __('fh.nav.developments') => 'developments', $title => $path])],
        ]);

        return [
            'row' => $row, 'd' => $card, 'hero' => $hero, 'gallery' => $gallery,
            'summary' => $summary, 'description' => $description, 'highlights' => Projects::highlights($row),
            'units' => $units, 'stages' => $stages, 'updates' => $updates,
            'counts' => ['all' => count($units), 'available' => count($open), 'sold' => count($units) - count($open)],
            'brochure' => $brochureUrl, 'embed' => PropertyPage::embed((string) ($row->video_url ?? '')),
            'map' => $lat !== null && $lng !== null ? ['lat' => $lat, 'lng' => $lng] : null,
            'form' => Site::reactiveAssets() . Reactive::mount('development', 'interest', [
                'projectId' => $id, 'project' => $title, 'page' => (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
            ]),
        ];
    }

    private static function plain(string $html, int $length): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(mb_substr((string) preg_replace('/\s+/u', ' ', $text), 0, $length));
    }

    /** JSON-LD Residence (Stage 3 D6). */
    private static function residenceLd(object $row, array $card, ?object $hero, string $description): array
    {
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Residence',
            'name' => $card['title'],
            'url' => Site::url(Projects::path((string) $row->slug)),
            'description' => trim(mb_substr($description, 0, 500)),
        ];
        if ($hero !== null) {
            $ld['image'] = Site::absolute($hero->url());
        }
        $city = Listings::name('cities', (int) ($row->city_id ?? 0));
        if ($city !== '') {
            $ld['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $city, 'addressCountry' => 'GR'];
        }
        if (($row->latitude ?? null) !== null && ($row->longitude ?? null) !== null) {
            $ld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => round((float) $row->latitude, 3), 'longitude' => round((float) $row->longitude, 3)];
        }
        return $ld;
    }
}
