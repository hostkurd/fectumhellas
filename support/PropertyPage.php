<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Template;
use FloCMS\Core\TemplateEngine;
use FloCMS\Reactive\Reactive;

/**
 * /{lang}/properties/<slug>: one property (Stage 3 D2, approved Property
 * mockup). Core can't take a slug as an action, so this answers through
 * App::fallbackUsing() (config/config.php) and is shown inside the site
 * layout the way flocms-admin shows its Pages. Drafts and unknown slugs: 404.
 */
final class PropertyPage
{
    public static function handle(string $path, Request $request): ?Response
    {
        if (!$request->isMethod('GET', 'HEAD') || preg_match('~^properties/([^/]+)/?$~u', trim($path, '/'), $m) !== 1 || preg_match(Listings::SLUG, $m[1]) !== 1) {
            return null;
        }
        $row = Listings::bySlug($m[1]);
        if ($row === null) {
            return null; // core answers 404
        }
        $content = TemplateEngine::renderFile(VIEWS_PATH . DS . 'properties' . DS . 'show.html', self::data($row));
        $html = TemplateEngine::renderFile(Template::getLayoutPath('default'), ['content' => $content]);
        return Response::html($html);
    }

    /** Everything the property page shows. */
    public static function data(object $row): array
    {
        $card = Listings::card($row);
        $status = (string) $row->status;
        $lang = Site::lang();
        $title = $card['title'];
        $id = (int) $row->id;

        // Photos: the cover first, then the gallery (no duplicates)
        $photos = [];
        foreach ([$row->cover_id ?? null, ...array_map('strval', \FloCMS\Admin\Form\Fields\MediaPicker::ids($row->gallery ?? ''))] as $mid) {
            $item = $mid !== null && $mid !== '' ? media($mid) : null;
            if ($item !== null && $item->isImage() && !isset($photos[$item->id])) {
                $photos[$item->id] = $item;
            }
        }
        $photos = array_values($photos);
        $plans = array_values(array_filter(media_list($row->floor_plans ?? ''), static fn ($i) => $i->isImage()));
        $videos = array_values(array_filter(media_list($row->videos ?? ''), static fn ($i) => $i->isVideo()));
        $embed = self::embed((string) ($row->video_url ?? ''));

        // Key facts (empty ones left out)
        $area = ($row->area_m2 ?? null) !== null ? (float) $row->area_m2 : 0.0;
        $energy = (string) ($row->energy_class ?? '');
        $year = (int) ($row->construction_year ?? 0);
        $facts = array_values(array_filter([
            ['k' => __('fh.fact.reference'), 'v' => (string) $row->reference, 'ltr' => true],
            ['k' => __('fh.fact.type'), 'v' => $card['type']],
            ['k' => __('fh.fact.status'), 'v' => $card['status']],
            ['k' => __('fh.fact.area'), 'v' => $area > 0 ? Site::number($area, 1) . ' m²' : '', 'ltr' => true],
            ['k' => __('fh.fact.outdoor'), 'v' => ($row->outdoor_m2 ?? null) ? Site::number((float) $row->outdoor_m2, 1) . ' m²' : '', 'ltr' => true],
            ['k' => __('fh.fact.bedrooms'), 'v' => ($row->bedrooms ?? null) !== null ? (string) (int) $row->bedrooms : ''],
            ['k' => __('fh.fact.bathrooms'), 'v' => ($row->bathrooms ?? null) !== null ? (string) (int) $row->bathrooms : ''],
            ['k' => __('fh.fact.floor'), 'v' => localized($row->floor ?? '')],
            ['k' => __('fh.fact.parking'), 'v' => localized($row->parking ?? '')],
            ['k' => __('fh.fact.energy'), 'v' => $energy !== '' ? Catalog::energyClassLabel($energy, $lang) : ''],
            ['k' => __('fh.fact.year'), 'v' => $year > 0 ? (string) $year : ''],
        ], static fn (array $f): bool => trim((string) $f['v']) !== ''));

        // Badge next to the status: "Under construction · 2027" / "New build · 2026"
        $build = '';
        if ((int) ($row->under_construction ?? 0) === 1) {
            $build = __('fh.build.construction') . ($year > 0 ? ' · ' . $year : '');
        } elseif ($year >= (int) date('Y') - Listings::NEW_BUILD_YEARS) {
            $build = __('fh.build.new') . ' · ' . $year;
        }

        $price = ($row->price ?? null) !== null && (int) ($row->price_on_request ?? 0) === 0 ? (float) $row->price : 0.0;
        $perM2 = $price > 0 && $area > 0 && in_array($status, ['for_sale', 'off_plan'], true) ? Site::price(round($price / $area)) . ' / m²' : '';

        // Cost of buying (Stage 3 D12): properties for sale, when switched on in Settings
        $costs = null;
        if ($price > 0 && in_array($status, ['for_sale', 'off_plan'], true) && function_exists('setting') && (string) setting('costs.enabled') === '1') {
            $lines = [];
            foreach (repeater_items(setting('costs.lines')) as $line) {
                if ((string) ($line['active'] ?? '1') !== '1') { continue; }
                $rate = (float) str_replace(',', '.', (string) ($line['rate'] ?? '0'));
                $name = localized($line['name'] ?? '');
                if ($name !== '' && $rate > 0) { $lines[] = ['name' => $name, 'rate' => $rate]; }
            }
            if ($lines !== []) {
                $costs = ['lines' => $lines, 'note' => Site::setting('costs.note'), 'price' => $price];
            }
        }

        // Rounded (about 100 m): the page says "approximate location"; the address is shared on request
        $lat = ($row->latitude ?? null) !== null ? round((float) $row->latitude, 3) : null;
        $lng = ($row->longitude ?? null) !== null ? round((float) $row->longitude, 3) : null;
        $brochure = trim((string) ($row->brochure ?? ''));
        $brochureUrl = $brochure !== '' && class_exists(\FloCMS\Admin\Uploads\Uploads::class) ? \FloCMS\Admin\Uploads\Uploads::publicUrl($brochure) : '';

        $description = localized($row->description ?? '');
        $summary = localized($row->summary ?? '');
        $metaDescription = localized($row->meta_description ?? '');
        $share = media($row->social_image_id ?? null) ?? ($photos[0] ?? null);

        Site::meta([
            'title' => localized($row->meta_title ?? '') !== '' ? localized($row->meta_title) : $title . ' · ' . $card['reference'],
            'description' => $metaDescription !== '' ? $metaDescription : ($summary !== '' ? $summary : self::plain($description, 200)),
            'path' => Listings::path($row->slug),
            'image' => $share !== null ? Site::absolute($share->url()) : '',
            'jsonld' => [self::listingLd($row, $card, $photos, $summary !== '' ? $summary : self::plain($description, 500), $price), Site::breadcrumbLd([__('fh.home') => '', __('fh.nav.properties') => 'properties', $card['reference'] => Listings::path($row->slug)])],
        ]);

        return [
            'row' => $row, 'p' => $card, 'status' => $status, 'build' => $build, 'perM2' => $perM2,
            'photos' => $photos, 'plans' => $plans, 'videos' => $videos, 'embed' => $embed,
            'facts' => $facts, 'summary' => $summary, 'description' => $description,
            'amenities' => Listings::amenities($id), 'costs' => $costs,
            'map' => $lat !== null && $lng !== null ? ['lat' => $lat, 'lng' => $lng] : null,
            'brochure' => $brochureUrl, 'similar' => Listings::similar($row),
            'closed' => in_array($status, ['sold', 'rented'], true),
            'form' => in_array($status, ['sold', 'rented'], true) ? '' : Site::reactiveAssets() . Reactive::mount('property', 'inquiry', [
                'listingId' => $id, 'ref' => (string) $row->reference, 'page' => (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
                'message' => __('fh.inquiry_default', ['ref' => (string) $row->reference]),
            ]),
            'whatsapp' => Site::setting('contact.whatsapp'), 'phone' => Site::setting('contact.phone'),
        ];
    }

    /** Rich text as plain text (meta description, JSON-LD). */
    private static function plain(string $html, int $length): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(mb_substr((string) preg_replace('/\s+/u', ' ', $text), 0, $length));
    }

    /** A YouTube or Vimeo address as a privacy-friendly embed address, or ''. */
    public static function embed(string $url): string
    {
        if ($url === '' || !Catalog::isVideoLink($url)) {
            return '';
        }
        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~', $url, $m) === 1) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1';
        }
        return '';
    }

    /** JSON-LD RealEstateListing with an Offer (Stage 3 D6). */
    private static function listingLd(object $row, array $card, array $photos, string $description, float $price): array
    {
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $card['title'],
            'url' => Site::url(Listings::path($row->slug)),
            'identifier' => (string) $row->reference,
            'description' => trim(mb_substr($description, 0, 500)),
            'image' => array_map(static fn ($p): string => Site::absolute($p->url()), array_slice($photos, 0, 6)),
        ];
        if (($row->created_at ?? null) !== null) {
            $ld['datePosted'] = substr((string) $row->created_at, 0, 10);
        }
        $status = (string) $row->status;
        if ($price > 0 && !in_array($status, ['sold', 'rented'], true)) {
            $ld['offers'] = [
                '@type' => 'Offer',
                'price' => (string) round($price),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'businessFunction' => $status === 'for_rent' ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
            ];
            if ($status === 'for_rent') {
                $ld['offers']['priceSpecification'] = ['@type' => 'UnitPriceSpecification', 'price' => (string) round($price), 'priceCurrency' => 'EUR', 'unitCode' => 'MON', 'unitText' => 'month'];
            }
        }
        $city = Listings::name('cities', (int) ($row->city_id ?? 0));
        if ($city !== '') {
            $ld['contentLocation'] = ['@type' => 'Place', 'name' => $card['location'], 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $city, 'addressCountry' => 'GR']];
        }
        return $ld;
    }
}
