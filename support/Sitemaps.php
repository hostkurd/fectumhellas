<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\App;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

/**
 * Search engines (Stage 3d, Stage 3 design note D7):
 * - /sitemap-content.xml: the Properties, Developments and Insights pages and
 *   every published property, development and article, in EN, AR and EL with
 *   the other languages as alternates. flocms-admin's own /sitemap.xml keeps
 *   listing the pages made in the admin (Home, Services, About, Contact…).
 * - /robots.txt: points to both sitemaps and keeps the admin and helper
 *   addresses out; Settings › SEO › "Hide the website from search engines"
 *   closes the whole site (before launch).
 * Answered from config/config.php's fallback chain (no controller can take a
 * name with a dot).
 */
final class Sitemaps
{
    public static function handle(string $path, Request $request): ?Response
    {
        if (!$request->isMethod('GET', 'HEAD')) {
            return null;
        }
        // Language prefix or not (/robots.txt, /en/robots.txt): the same file
        $path = trim($path, '/');
        $parts = explode('/', $path, 2);
        if (count($parts) === 2 && isset(Site::LANGUAGES[$parts[0]])) {
            $path = $parts[1];
        }
        return match ($path) {
            'sitemap-content.xml' => Response::make(self::xml())->header('Content-Type', 'application/xml; charset=UTF-8')->header('X-Robots-Tag', 'noindex'),
            'robots.txt' => Response::make(self::robots())->header('Content-Type', 'text/plain; charset=UTF-8'),
            default => null,
        };
    }

    public static function robots(): string
    {
        $base = Site::base();
        if (Site::setting('seo.hide_site') === '1') {
            return "# Settings › SEO: the website is hidden from search engines.\nUser-agent: *\nDisallow: /\n";
        }
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /flo-admin/', 'Disallow: /flo-preview/'];
        foreach (array_keys(Site::LANGUAGES) as $lang) {
            foreach (['admin', 'find', 'forms', 'flo-reactive', 'flo-preview'] as $p) {
                $lines[] = 'Disallow: /' . $lang . '/' . $p;
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        $lines[] = 'Sitemap: ' . $base . '/sitemap-content.xml';
        return implode("\n", $lines) . "\n";
    }

    public static function xml(): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $entries = [];
        // The list pages (controllers, not admin pages)
        foreach (['properties', 'developments', 'insights'] as $p) {
            $entries[] = [$p, null];
        }
        $db = App::db();
        if ($db !== null) {
            $now = date('Y-m-d H:i:s');
            $sources = [
                ["SELECT `slug`, `updated_at` FROM `listings` WHERE `publish_status` = 'published' ORDER BY `id` DESC", [], 'properties/'],
                ["SELECT `slug`, `updated_at` FROM `projects` WHERE `publish_status` = 'published' ORDER BY `sort`, `id` DESC", [], 'developments/'],
                ["SELECT `slug`, `updated_at` FROM `articles` WHERE `publish_status` = 'published' AND (`published_at` IS NULL OR `published_at` <= ?) ORDER BY `published_at` DESC, `id` DESC", [$now], 'insights/'],
            ];
            foreach ($sources as [$sql, $params, $prefix]) {
                try {
                    foreach ($db->query($sql, $params)->fetchAll(\PDO::FETCH_OBJ) as $row) {
                        $slug = (string) ($row->slug ?? '');
                        if ($slug !== '') {
                            $entries[] = [$prefix . rawurlencode($slug), $row->updated_at ?? null];
                        }
                    }
                } catch (\Throwable $error) {
                    error_log('FECTUM sitemap: ' . $error->getMessage());
                }
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($entries as [$path, $updated]) {
            $time = is_string($updated) && $updated !== '' ? strtotime($updated) : false;
            foreach (array_keys(Site::LANGUAGES) as $lang) {
                $xml .= "  <url>\n    <loc>" . $e(Site::url($path, $lang)) . "</loc>\n";
                if ($time !== false) {
                    $xml .= '    <lastmod>' . gmdate('Y-m-d', $time) . "</lastmod>\n";
                }
                foreach (array_keys(Site::LANGUAGES) as $other) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $other . '" href="' . $e(Site::url($path, $other)) . '"/>' . "\n";
                }
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $e(Site::url($path, 'en')) . '"/>' . "\n";
                $xml .= "  </url>\n";
            }
        }
        return $xml . '</urlset>' . "\n";
    }
}
