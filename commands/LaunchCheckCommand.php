<?php
declare(strict_types=1);

namespace App\Commands;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;

/**
 * php flo fectum:check https://fectumhellas.gr
 *
 * The FECTUM Hellas launch check (Stage 4): the workflow plan's acceptance
 * list, as far as a script can check it, against the live website, plus the
 * project's own settings. Read-only: it opens pages and reads config, it
 * never writes or sends anything. Run it before launch and after every
 * deploy; it ends with the checks a person still has to do by hand.
 */
final class LaunchCheckCommand extends Command
{
    protected string $name = 'fectum:check';

    protected string $description = 'Check the FECTUM Hellas website against the launch list';

    protected string $help = <<<'TXT'
Opens the website's pages in English, Arabic and Greek and checks the
launch list: pages answer, property pages have their own title,
description, canonical and hreflang and appear in the sitemap, robots.txt
and the 404 page, the admin login and its styles, debug mode off, the
Editor role's permissions. Prints PASS / WARN / FAIL per check and exits
with 1 when something failed.
TXT;

    /** @var array{pass: int, warn: int, fail: int} */
    private array $count = ['pass' => 0, 'warn' => 0, 'fail' => 0];
    private string $base = '';

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('url', Argument::OPTIONAL, 'The website address (default: APP_URL)')
            ->option('admin-host', null, Option::VALUE_REQUIRED, 'Also check that this host redirects to the admin, e.g. https://admin.fectumhellas.gr');
    }

    protected function handle(): int
    {
        $this->project()->boot();
        $url = trim((string) ($this->argument('url') ?? ''));
        if ($url === '') {
            $url = defined('SITE_URI') ? (string) SITE_URI : (string) (getenv('APP_URL') ?: '');
        }
        if (preg_match('~^https?://[^/]+~i', $url) !== 1) {
            $this->invalid('Give the website address, e.g. php flo fectum:check https://fectumhellas.gr');
        }
        $this->base = rtrim($url, '/');
        $langs = ['en' => 'ltr', 'ar' => 'rtl', 'el' => 'ltr'];
        $this->title('FECTUM Hellas launch check · ' . $this->base);

        // Offline mode (Settings › Website status): visitors, and this check, get the offline page (503)
        $offline = $this->get('/en/')['status'] === 503;
        if ($offline) {
            $this->section('Website');
            $this->line('  FAIL  The website is in offline mode: every visitor page answers 503 with the offline page.');
            $this->line('        Switch it off in the admin (Settings › Website status) and run the check again.');
            $this->line('        Only the admin and settings checks run now.');
            $this->count['fail']++;
        } else {
        $this->section('Website pages');
        foreach ($langs as $lang => $dir) {
            foreach (['' => 'Home', 'properties' => 'Properties', 'developments' => 'Developments', 'insights' => 'Insights'] as $path => $label) {
                $r = $this->get('/' . $lang . '/' . $path);
                $ok = $r['status'] === 200 && str_contains($r['body'], 'lang="' . $lang . '"') && str_contains($r['body'], 'dir="' . $dir . '"');
                $this->result($ok, strtoupper($lang) . ' ' . $label . ' answers with the right language and direction', 'HTTP ' . $r['status']);
            }
        }
        $props = $this->get('/en/properties');
        $this->result(substr_count($props['body'], 'class="fh-card"') > 0, 'The property list shows properties', 'no property cards on /en/properties');

        $this->section('Search engines');
        $robots = $this->get('/robots.txt');
        $this->result($robots['status'] === 200 && str_contains($robots['body'], 'Sitemap:'), 'robots.txt answers and lists the sitemaps');
        if (preg_match('~^Disallow:\s*/\s*$~m', $robots['body']) === 1) {
            $this->warnLine('robots.txt hides the whole website (Settings › Search engines). Turn it off at launch.');
        }
        $pagesMap = $this->get('/sitemap.xml');
        $this->result($pagesMap['status'] === 200 && $this->xmlOk($pagesMap['body']), '/sitemap.xml (admin pages) is valid XML');
        $map = $this->get('/sitemap-content.xml');
        $this->result($map['status'] === 200 && $this->xmlOk($map['body']), '/sitemap-content.xml (properties, developments, articles) is valid XML');
        preg_match_all('~<loc>([^<]+/en/properties/[^<]+)</loc>~', $map['body'], $m);
        $propertyUrls = array_slice(array_unique($m[1]), 0, 3);
        $this->result($propertyUrls !== [], 'Property pages appear in the sitemap', 'no /en/properties/<slug> in sitemap-content.xml');
        $titles = [];
        foreach ($propertyUrls as $propertyUrl) {
            $path = (string) parse_url(html_entity_decode($propertyUrl, ENT_XML1), PHP_URL_PATH);
            $page = $this->get($path);
            $title = preg_match('~<title>([^<]*)</title>~', $page['body'], $t) === 1 ? trim($t[1]) : '';
            $titles[] = $title;
            $checks = [
                'answers' => $page['status'] === 200,
                'title' => $title !== '',
                'description' => preg_match('~<meta name="description" content="[^"]{20,}~', $page['body']) === 1,
                'canonical' => str_contains($page['body'], '<link rel="canonical" href="' . $this->base . $path . '"'),
                'hreflang' => substr_count($page['body'], 'rel="alternate" hreflang=') >= 4,
                'Open Graph' => str_contains($page['body'], 'property="og:title"'),
                'structured data' => str_contains($page['body'], 'application/ld+json'),
            ];
            $missing = array_keys(array_filter($checks, static fn (bool $v): bool => !$v));
            $this->result($missing === [], 'Property page ' . basename($path) . ': title, description, canonical, hreflang, Open Graph, structured data', 'missing: ' . implode(', ', $missing));
        }
        if (count($titles) > 1) {
            $this->result(count(array_unique($titles)) === count($titles), 'Property pages have their own titles');
        }
        $home = $this->get('/en/');
        $this->result(str_contains($home['body'], '"RealEstateAgent"'), 'Company details for search engines on every page (checked on Home)');

        $this->section('Errors and private pages');
        $missing = $this->get('/en/this-page-does-not-exist-' . bin2hex(random_bytes(3)));
        $this->result($missing['status'] === 404, 'A missing page answers 404', 'HTTP ' . $missing['status']);
        $this->result(str_contains($missing['body'], 'fh-nf-hero') && str_contains($missing['body'], 'noindex'), 'The designed 404 page is shown (not indexed)', 'debug page or plain error page instead');
        $this->result(!str_contains($missing['body'], 'HttpException') && !str_contains($missing['body'], 'Stack trace'), 'No error details shown to visitors (APP_DEBUG=false)', 'the 404 shows debug details');
        foreach (['compare' => 'Compare', 'saved' => 'Saved'] as $path => $label) {
            $r = $this->get('/en/' . $path);
            $this->result($r['status'] === 200 && str_contains($r['body'], 'noindex'), $label . ' page answers and is kept out of search results');
        }

        }

        $this->section('Admin');
        $login = $this->get('/en/admin/users/login');
        $this->result($login['status'] === 200, 'The admin login page answers', 'HTTP ' . $login['status']);
        if (preg_match('~href="([^"]+/admin/flo-assets/asset/admin-v[^"]+\.css)"~', $login['body'], $css) === 1) {
            $asset = $this->get((string) parse_url(html_entity_decode($css[1]), PHP_URL_PATH));
            $this->result($asset['status'] === 200, 'The admin login page has its styles', 'HTTP ' . $asset['status'] . ' for the admin stylesheet');
        } else {
            $this->result(false, 'The admin login page has its styles', 'no admin stylesheet link (flocms-admin older than 0.40.1?)');
        }
        $adminHost = trim((string) ($this->option('admin-host') ?? ''));
        if ($adminHost !== '') {
            $r = $this->get(rtrim($adminHost, '/') . '/', false);
            $location = $r['headers']['location'] ?? '';
            $this->result(in_array($r['status'], [301, 308], true) && str_contains($location, '/admin'), $adminHost . ' redirects to the admin', 'HTTP ' . $r['status'] . ($location !== '' ? ' to ' . $location : ''));
        }

        $this->section('Server and settings');
        if ($offline) {
            // checked with the website online
        } elseif (str_starts_with($this->base, 'https://')) {
            $plain = $this->get('http://' . substr($this->base, 8) . '/', false);
            $this->result(in_array($plain['status'], [301, 302, 307, 308], true) && str_starts_with($plain['headers']['location'] ?? '', 'https://'), 'http:// redirects to https://', 'HTTP ' . $plain['status']);
        } else {
            $this->warnLine('The address is not https:// (fine for a local copy, not for the live site).');
        }
        $debug = strtolower((string) (getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? 'false')));
        if (in_array($debug, ['1', 'true', 'on', 'yes'], true)) {
            $this->warnLine('APP_DEBUG is on in this copy\'s .env (turn it off on the live server).');
        }
        $roles = (array) \FloCMS\Core\Config::get('permissions', []);
        $editor = array_map('strval', (array) ($roles[1] ?? []));
        $hasContent = in_array('content.*', $editor, true) || in_array('*', $editor, true);
        $reachesAdmin = array_filter($editor, static fn (string $p): bool => $p === '*' || str_starts_with($p, 'users') || str_starts_with($p, 'roles') || str_starts_with($p, 'settings'));
        $this->result($hasContent && $reachesAdmin === [], 'The Editor role manages content but not users, roles or settings', 'Editor permissions: ' . implode(', ', $editor));

        $this->newLine();
        $this->section('Still checked by a person');
        $this->line('  - An Editor creates, translates and publishes a property and a development.');
        $this->line('  - On a phone: filter properties, open one, go back (the filters stay), send an inquiry, in EN, AR and EL.');
        $this->line('  - An inquiry stays in the admin even when its email fails.');
        $this->line('  - A cPanel backup has been restored once on a test copy.');
        $this->newLine();
        $summary = sprintf('%d passed, %d warnings, %d failed', $this->count['pass'], $this->count['warn'], $this->count['fail']);
        if ($this->count['fail'] > 0) {
            $this->error($summary);
            return self::FAILURE;
        }
        $this->success($summary);
        return self::SUCCESS;
    }

    private function result(bool $ok, string $label, string $why = ''): void
    {
        if ($ok) {
            $this->count['pass']++;
            $this->line('  PASS  ' . $label);
            return;
        }
        $this->count['fail']++;
        $this->line('  FAIL  ' . $label . ($why !== '' ? ' (' . $why . ')' : ''));
    }

    private function warnLine(string $text): void
    {
        $this->count['warn']++;
        $this->line('  WARN  ' . $text);
    }

    private function xmlOk(string $xml): bool
    {
        if ($xml === '' || !function_exists('simplexml_load_string')) {
            return $xml !== '';
        }
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $doc !== false;
    }

    /**
     * GET a path on the website (or a full URL).
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function get(string $pathOrUrl, bool $follow = true): array
    {
        $url = preg_match('~^https?://~i', $pathOrUrl) === 1 ? $pathOrUrl : $this->base . $pathOrUrl;
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'ignore_errors' => true, 'follow_location' => $follow ? 1 : 0, 'max_redirects' => 5, 'timeout' => 20,
                'header' => "User-Agent: FECTUM-launch-check\r\nAccept: text/html,application/xml,text/plain\r\n"],
        ]);
        $body = @file_get_contents($url, false, $context);
        $raw = $http_response_header ?? [];
        $status = 0;
        $headers = [];
        foreach ($raw as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $s) === 1) {
                $status = (int) $s[1]; // the last response after redirects
                $headers = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'headers' => $headers];
    }
}
