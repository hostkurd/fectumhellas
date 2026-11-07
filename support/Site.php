<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Admin\Pages\Pages;

/**
 * Small helpers for the public website templates: addresses in the current
 * language, the language switch, the main navigation, number formats.
 * Everything a visitor reads comes from the admin (settings, Pages, records)
 * or from the fh.* keys in lang/<code>.php.
 */
final class Site
{
    /** Website languages in the order of the switch, with their short and full names. */
    public const LANGUAGES = [
        'en' => ['short' => 'EN', 'name' => 'English'],
        'ar' => ['short' => 'AR', 'name' => 'العربية'],
        'el' => ['short' => 'EL', 'name' => 'Ελληνικά'],
    ];

    /** Main navigation: key => [address, lang key]. Page addresses are looked up in the Pages section. */
    private const NAV = [
        'properties' => ['properties', 'fh.nav.properties'],
        'developments' => ['developments', 'fh.nav.developments'],
        'services' => ['page:services', 'fh.nav.services'],
        'about' => ['page:about', 'fh.nav.about'],
        'insights' => ['insights', 'fh.nav.insights'],
        'contact' => ['page:contact', 'fh.nav.contact'],
    ];

    public static function lang(): string
    {
        return defined('ACTIVE_LANG') ? (string) ACTIVE_LANG : 'en';
    }

    public static function isRtl(?string $lang = null): bool
    {
        return ($lang ?? self::lang()) === 'ar';
    }

    public static function base(): string
    {
        return defined('SITE_URI') ? rtrim((string) SITE_URI, '/') : '';
    }

    /** An address on the website in a language: url('properties') → /en/properties. */
    public static function url(string $path = '', ?string $lang = null): string
    {
        $path = trim($path, '/');
        return self::base() . '/' . ($lang ?? self::lang()) . '/' . ($path === '' ? '' : $path);
    }

    /** A file under public/: asset('themes/fectum/css/site.css'), with its change time as version. */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = (defined('ROOT') ? ROOT : dirname(__DIR__)) . '/public/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '';
        return self::base() . '/' . $path . ($version !== '' ? '?v=' . $version : '');
    }

    /** The address of a Page by its slug in the default language, in the current language (or the slug as typed). */
    public static function pageUrl(string $slug, ?string $lang = null): string
    {
        $lang ??= self::lang();
        if (class_exists(Pages::class) && Pages::enabled()) {
            foreach (Pages::rows() as $row) {
                if (Pages::slug($row, \FloCMS\Admin\Translation\Languages::default()) === $slug && (int) ($row->parent_id ?? 0) === 0 && Pages::isLive($row)) {
                    return Pages::url($row, $lang);
                }
            }
        }
        return self::url($slug, $lang);
    }

    /** @return list<array{key: string, label: string, url: string, current: bool}> */
    public static function nav(): array
    {
        $first = self::currentSegment();
        $items = [];
        foreach (self::NAV as $key => [$target, $label]) {
            $isPage = str_starts_with($target, 'page:');
            $url = $isPage ? self::pageUrl(substr($target, 5)) : self::url($target);
            $segment = self::segmentOf((string) parse_url($url, PHP_URL_PATH));
            $items[] = ['key' => $key, 'label' => __($label), 'url' => $url, 'current' => $first !== '' && rawurldecode($segment) === $first];
        }
        return $items;
    }

    /** The first path segment after the language, e.g. "properties". */
    public static function currentSegment(): string
    {
        return self::segmentOf((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    }

    /** The first segment after the site folder and the language of a path. */
    private static function segmentOf(string $path): string
    {
        $path = trim($path, '/');
        $base = trim((string) parse_url(self::base(), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = trim(substr($path, strlen($base)), '/');
        }
        $parts = explode('/', $path);
        return rawurldecode($parts[1] ?? '');
    }

    /**
     * The current page in another language: a Page through its own address
     * in that language; any other page by swapping the language segment.
     */
    public static function switchUrl(string $lang): string
    {
        if (function_exists('current_page') && ($page = current_page()) !== null) {
            return $page->isHome() ? self::url('', $lang) : Pages::url($page->row(), $lang);
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $base = rtrim((string) parse_url(self::base(), PHP_URL_PATH), '/');
        $rest = $base !== '' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
        $parts = explode('/', ltrim($rest, '/'), 2);
        $tail = isset(self::LANGUAGES[$parts[0] ?? '']) ? ($parts[1] ?? '') : ltrim($rest, '/');
        return self::url($tail, $lang) . ($query !== '' ? '?' . $query : '');
    }

    public static function isHome(): bool
    {
        if (function_exists('current_page') && ($page = current_page()) !== null) {
            return $page->isHome();
        }
        return self::currentSegment() === '';
    }

    /**
     * Whether the header starts transparent over a photo: the page opens with
     * a slider, or with a page header that has a photo. Anywhere else (and on
     * pages without blocks) it is the plain white header.
     */
    public static function overlayHeader(): bool
    {
        if (!function_exists('current_page') || ($page = current_page()) === null) {
            return false;
        }
        // The same blocks, in the same language fallback and schedule, as the page prints.
        $nodes = \FloCMS\Admin\Blocks\BlockRenderer::nodes($page->row()->blocks ?? '', self::lang());
        foreach (is_array($nodes) ? $nodes : [] as $node) {
            if (!is_array($node) || !\FloCMS\Admin\Blocks\BlockRenderer::visible($node)) {
                continue;
            }
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            return match ($node['type'] ?? '') {
                'slider' => !empty($data['slides']),
                'page_header' => !empty($data['image']),
                default => false,
            };
        }
        return false;
    }

    /** A setting from the admin, trimmed, in the current language. */
    public static function setting(string $key, string $fallback = ''): string
    {
        if (!function_exists('setting')) {
            return $fallback;
        }
        $value = setting($key);
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : $fallback;
    }

    /**
     * A link typed by an editor in a block: http(s), mailto: and tel: links,
     * addresses on the site (/en/properties), or a path in the current
     * language (properties → /en/properties). Anything else gives ''.
     */
    public static function link(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/[\s<>"\']/', $value) === 1) {
            return '';
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $value) === 1 || (str_starts_with($value, '/') && !str_starts_with($value, '//')) || str_starts_with($value, '#')) {
            return $value;
        }
        return preg_match('#^[\pL\pN][\pL\pN/_.-]*$#u', $value) === 1 ? self::url($value) : '';
    }

    /** Digits only (and a leading +) for tel: links. */
    public static function tel(string $phone): string
    {
        return (string) preg_replace('/[^0-9+]/', '', $phone);
    }

    /** A wa.me link for a WhatsApp number. */
    public static function whatsapp(string $number): string
    {
        return 'https://wa.me/' . preg_replace('/\D/', '', $number);
    }

    /**
     * A price in the visitor's language (design note Stage 3 D11): €1,200,000
     * in English, 1.200.000 € in Greek, 1,200,000 € in Arabic (Latin digits).
     */
    public static function price(float|int|string|null $amount, ?string $lang = null): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }
        $lang ??= self::lang();
        $value = (float) $amount;
        return match ($lang) {
            'el' => number_format($value, 0, ',', '.') . ' €',
            'ar' => number_format($value, 0, '.', ',') . ' €',
            default => '€' . number_format($value, 0, '.', ','),
        };
    }

    /** A plain number in the visitor's language (areas): 1,250.5 / 1.250,5. */
    public static function number(float|int|string|null $value, int $decimals = 0, ?string $lang = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = (float) $value;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : $decimals;
        return ($lang ?? self::lang()) === 'el'
            ? number_format($value, $decimals, ',', '.')
            : number_format($value, $decimals, '.', ',');
    }

    /** HTML-escape. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
