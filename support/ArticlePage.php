<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;

/** /{lang}/insights/<slug>: one article (built once its mockup is approved). */
final class ArticlePage
{
    public static function handle(string $path, Request $request): ?Response
    {
        return null;
    }
}
