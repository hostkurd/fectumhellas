<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Template;
use FloCMS\Core\TemplateEngine;

/**
 * /{lang}/insights/<slug>: one article (approved Insights article mockup),
 * answered through App::fallbackUsing() like PropertyPage. Drafts, articles
 * dated in the future and unknown slugs: 404.
 */
final class ArticlePage
{
    /** Categories that end with the "general information, not advice" note. */
    private const NOTE = ['buying_guide', 'golden_visa', 'market_insights'];

    public static function handle(string $path, Request $request): ?Response
    {
        if (!$request->isMethod('GET', 'HEAD') || preg_match('~^insights/([^/]+)/?$~u', trim($path, '/'), $m) !== 1) {
            return null;
        }
        $row = Articles::bySlug($m[1]);
        if ($row === null) {
            return null; // core answers 404
        }
        $content = TemplateEngine::renderFile(VIEWS_PATH . DS . 'insights' . DS . 'show.html', self::data($row));
        $html = TemplateEngine::renderFile(Template::getLayoutPath('default'), ['content' => $content]);
        return Response::html($html);
    }

    public static function data(object $row): array
    {
        $a = Articles::card($row);
        $body = localized($row->body ?? '');
        $path = Articles::path((string) $row->slug);
        $url = Site::url($path);
        $metaDescription = localized($row->meta_description ?? '');
        $share = media($row->social_image_id ?? null) ?? $a['cover'];

        $crumbs = [__('fh.home') => '', __('fh.insights_title') => 'insights'];
        if ($a['category_label'] !== '') {
            $crumbs[$a['category_label']] = 'insights?category=' . $a['category'];
        }
        $crumbs[$a['title']] = $path;
        Site::meta([
            'title' => localized($row->meta_title ?? '') !== '' ? localized($row->meta_title) : $a['title'],
            'description' => $metaDescription !== '' ? $metaDescription : ($a['excerpt'] !== '' ? $a['excerpt'] : mb_substr(trim(strip_tags($body)), 0, 200)),
            'path' => $path,
            'image' => $share !== null ? Site::absolute($share->url()) : '',
            'type' => 'article',
            'jsonld' => [self::articleLd($row, $a, $share), Site::breadcrumbLd($crumbs)],
        ]);

        return [
            'a' => $a, 'body' => $body,
            'categoryUrl' => $a['category'] !== '' ? Site::url('insights') . '?category=' . $a['category'] : Site::url('insights'),
            'note' => in_array($a['category'], self::NOTE, true),
            'related' => Articles::related($row),
            'share' => [
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
                'whatsapp' => 'https://wa.me/?text=' . rawurlencode($a['title'] . ' ' . $url),
            ],
        ];
    }

    /** JSON-LD Article (Stage 3 D6). */
    private static function articleLd(object $row, array $a, ?object $image): array
    {
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => mb_substr($a['title'], 0, 110),
            'url' => Site::url(Articles::path((string) $row->slug)),
            'inLanguage' => Site::lang(),
            'publisher' => ['@type' => 'Organization', 'name' => 'FECTUM Hellas'],
        ];
        if ($a['datetime'] !== '') {
            $ld['datePublished'] = date(DATE_ATOM, (int) strtotime((string) $row->published_at));
        }
        if (($row->updated_at ?? null) !== null) {
            $ld['dateModified'] = date(DATE_ATOM, (int) strtotime((string) $row->updated_at));
        }
        if ($image !== null) {
            $ld['image'] = Site::absolute($image->url());
        }
        if ($a['excerpt'] !== '') {
            $ld['description'] = $a['excerpt'];
        }
        return $ld;
    }
}
