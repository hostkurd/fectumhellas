<?php
declare(strict_types=1);

namespace FloCMS\Controllers;

use FloCMS\Core\App;
use FloCMS\Core\Controller;
use FloCMS\Core\Http\Response;
use FloCMS\Support\Listings;
use FloCMS\Support\Site;

/**
 * /{lang}/find?q=…: the 404 page's search box (approved 404 mockup, Stage 3d).
 * A Property ID opens that property; an area or city name opens the property
 * search for it; anything else opens the property search.
 */
class FindController extends Controller
{
    public function index(): Response
    {
        $q = is_string($_GET['q'] ?? null) ? trim(mb_substr((string) $_GET['q'], 0, 100)) : '';
        $db = App::db();
        if ($q === '' || $db === null || mb_strlen($q) < 2) {
            return $this->redirect(Site::url('properties'));
        }
        // A Property ID ("GR54-00"; the "SAMPLE-" demo prefix may be left out)
        $ref = strtoupper(preg_replace('/\s+/', '', $q) ?? '');
        if (preg_match('/^[A-Z0-9-]{3,40}$/', $ref) === 1) {
            $rows = $db->query("SELECT `slug` FROM `listings` WHERE `publish_status` = 'published' AND (UPPER(`reference`) = ? OR UPPER(`reference`) = ?) LIMIT 1", [$ref, 'SAMPLE-' . $ref])->fetchAll(\PDO::FETCH_OBJ);
            if (isset($rows[0])) {
                return $this->redirect(Site::url(Listings::path($rows[0]->slug)));
            }
        }
        // An area or city, in any of the site's languages
        $needle = mb_strtolower($q);
        foreach ($db->query('SELECT `id`, `name` FROM `cities` ORDER BY `sort`, `id`')->fetchAll(\PDO::FETCH_OBJ) as $city) {
            $names = json_decode((string) ($city->name ?? ''), true);
            foreach (is_array($names) ? $names : [(string) ($city->name ?? '')] as $name) {
                if (is_string($name) && $name !== '' && mb_strpos(mb_strtolower($name), $needle) !== false) {
                    return $this->redirect(Site::url('properties') . '?search_city=' . (int) $city->id);
                }
            }
        }
        return $this->redirect(Site::url('properties'));
    }

    private function redirect(string $url): Response
    {
        return Response::make('', 303)->header('Location', $url)->header('Cache-Control', 'no-store');
    }
}
