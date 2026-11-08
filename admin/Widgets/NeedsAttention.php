<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use App\Admin\Resources\ListingResource;
use App\Admin\Resources\ProjectResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\ListWidget;
use FloCMS\Admin\Widgets\WidgetContext;

/**
 * What staff should look at: New inquiries without an owner or waiting more
 * than 48 hours, draft properties, developments without a progress update
 * for 30 days, properties with empty Greek or Arabic text. Rows with nothing
 * to do are left out; with none it says "Nothing needs your attention".
 */
final class NeedsAttention extends ListWidget
{
    protected string $title = 'fh.dash.attention';
    protected array $resources = [InquiryResource::class, ListingResource::class, ProjectResource::class];
    protected int $sort = 50;

    protected function items(WidgetContext $context): array
    {
        $new = $context->query(InquiryResource::class)->where('status', '=', 'new');
        $newList = Dash::listUrl('inquiries', ['status' => 'new'], 'created_at');
        $items = [
            ['icon' => 'user-x', 'color' => 'danger', 'title' => 'fh.dash.no_owner', 'description' => 'fh.dash.no_owner_help',
                'value' => $new->whereNull('owner_id')->count(), 'url' => $newList],
            ['icon' => 'hourglass', 'color' => 'warning', 'title' => Dash::t('fh.dash.waiting', ['hours' => Dash::WAITING_HOURS]), 'description' => 'fh.dash.waiting_help',
                'value' => $new->where('created_at', '<', Dash::waitingSince())->count(), 'url' => $newList],
            ['icon' => 'home', 'color' => 'primary', 'title' => 'fh.dash.drafts', 'description' => 'fh.dash.drafts_help',
                'value' => $context->query(ListingResource::class)->where('publish_status', '=', 'draft')->count(), 'url' => Dash::listUrl('listings', ['publish_status' => 'draft'])],
        ];
        foreach (array_slice(Dash::staleProjects(), 0, 3) as $project) {
            $items[] = ['icon' => 'camera', 'color' => 'chart2', 'title' => Dash::t('fh.dash.no_update', ['name' => $project['title']]),
                'description' => $project['days'] === null ? 'fh.dash.no_update_yet' : Dash::t('fh.dash.last_update', ['days' => $project['days']]),
                'value' => $project['days'] ?? Dash::UPDATE_DAYS, 'url' => $context->url('projects/edit/' . $project['id'])];
        }
        foreach (Dash::missingText(['el', 'ar']) as $lang => $count) {
            $items[] = ['icon' => 'globe', 'color' => 'primary', 'title' => 'fh.dash.missing_' . $lang, 'description' => 'fh.dash.missing_help',
                'value' => $count, 'url' => Dash::listUrl('listings', ['missing_in' => $lang])];
        }
        return $items;
    }
}
