<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\ListingResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\BarListWidget;
use FloCMS\Admin\Widgets\WidgetContext;
use FloCMS\Support\Catalog;

/** Published properties by sales status; the total and the drafts underneath. */
final class PropertiesByStatus extends BarListWidget
{
    protected string $title = 'fh.dash.by_status';
    protected array $resources = [ListingResource::class];
    protected int $sort = 60;

    private const COLORS = ['for_sale' => 'primary', 'for_rent' => 'chart2', 'off_plan' => 'chart3', 'sold' => 'neutral', 'rented' => 'neutral'];

    protected function items(WidgetContext $context): array
    {
        $counts = $context->query(ListingResource::class)->where('publish_status', '=', 'published')->countBy('status');
        $items = [];
        foreach (Catalog::listingStatuses() as $status => $label) {
            $items[] = ['label' => $label, 'value' => $counts[$status] ?? 0, 'color' => self::COLORS[$status],
                'url' => Dash::listUrl('listings', ['status' => $status, 'publish_status' => 'published'])];
        }
        return $items;
    }

    protected function footer(WidgetContext $context): string
    {
        $listings = $context->query(ListingResource::class);
        return Dash::t('fh.dash.status_footer', ['total' => $listings->where('publish_status', '=', 'published')->count(), 'drafts' => $listings->where('publish_status', '=', 'draft')->count()]);
    }
}
