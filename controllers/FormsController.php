<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\Config;
use FloCMS\Core\Controller;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Security\ClientIpResolver;
use FloCMS\Core\Session;
use FloCMS\Support\Inquiries;
use FloCMS\Support\Site;

/**
 * POST /{lang}/forms/contact: the Contact page form when JavaScript is off
 * (with JavaScript it is the flocms-reactive component App\Components\ContactForm).
 * Named "forms" because "inquiries" is the admin's Inquiries page. Core checks
 * the CSRF token before this runs. Saves the inquiry, emails the
 * team, then goes back to the page the form was on (post/redirect/get), with
 * the reference or the errors kept in the session for the form to show.
 */
class FormsController extends Controller
{
    public function contact(): Response
    {
        $back = $this->backUrl((string) ($_POST['page'] ?? ''));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $this->redirect($back);
        }

        // Honeypot: people never see this field; bots fill it in. Pretend it worked.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            Session::set(Inquiries::SESSION, ['sent' => 'FH-' . date('Y') . '-0000']);
            return $this->redirect($back . '#fh-contact-form');
        }

        [$clean, $errors] = Inquiries::validate($_POST);
        $ip = (new ClientIpResolver(array_values(array_filter(array_map('trim', (array) Config::get('trusted_proxies', []))))))->resolve($this->request);

        if ($errors === [] && !Inquiries::allowed($ip)) {
            $errors['form'] = 'fh.e.too_many';
        }
        if ($errors !== []) {
            Session::set(Inquiries::SESSION, ['errors' => $errors, 'old' => $clean]);
            return $this->redirect($back . '#fh-contact-form');
        }

        try {
            $reference = Inquiries::saveContact($clean, Site::lang(), $back, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $error) {
            error_log('FECTUM inquiry not saved: ' . $error->getMessage());
            Session::set(Inquiries::SESSION, ['errors' => ['form' => 'fh.e.not_saved'], 'old' => $clean]);
            return $this->redirect($back . '#fh-contact-form');
        }
        Session::set(Inquiries::SESSION, ['sent' => $reference]);
        return $this->redirect($back . '#fh-contact-form');
    }

    /** Only an address on this site; otherwise the Contact page. */
    private function backUrl(string $page): string
    {
        $base = rtrim(Site::base(), '/');
        $path = (string) parse_url($page, PHP_URL_PATH);
        $host = parse_url($page, PHP_URL_HOST);
        $siteHost = parse_url($base, PHP_URL_HOST);
        if ($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//') && ($host === null || $host === $siteHost)) {
            $basePath = (string) parse_url($base, PHP_URL_PATH);
            if ($basePath === '' || str_starts_with($path, $basePath)) {
                return ($siteHost !== null ? preg_replace('~^(https?://[^/]+).*$~', '$1', $base) : '') . $path;
            }
        }
        return Site::pageUrl('contact');
    }

    private function redirect(string $url): Response
    {
        return Response::make('', 303)->header('Location', $url)->header('Cache-Control', 'no-store');
    }
}
