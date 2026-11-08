<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use App\Admin\Resources\ListingResource;
use App\Admin\Resources\ProjectResource;
use App\Admin\Resources\ProjectUpdateResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\Stat;
use FloCMS\Admin\Widgets\StatsWidget;
use FloCMS\Admin\Widgets\WidgetContext;

/**
 * The four headline cards: New inquiries, Inquiries in the period, Published
 * properties and Developments. People who may not open a section don't see
 * the widget (it needs the view permission of all four resources).
 */
final class Headline extends StatsWidget
{
    protected string $title = 'fh.dash.headline';
    protected array $resources = [InquiryResource::class, ListingResource::class, ProjectResource::class, ProjectUpdateResource::class];
    protected bool $separateCards = true;
    protected int|string $columnSpan = 'full';
    protected int $sort = 10;

    protected function stats(WidgetContext $context): array
    {
        $period = $context->period();
        $previous = $period->previous();
        $inquiries = $context->query(InquiryResource::class);

        // New inquiries, and how many of them wait too long
        $new = $inquiries->where('status', '=', 'new');
        $newCount = $new->count();
        $late = $new->where('created_at', '<', Dash::waitingSince())->count();
        $newStat = Stat::make('fh.dash.new_inquiries', $newCount)->icon('mail')
            ->url(Dash::listUrl('inquiries', ['status' => 'new'], 'created_at'))
            ->description($late > 0 ? Dash::t('fh.dash.waiting_long', ['count' => $late, 'hours' => Dash::WAITING_HOURS]) : Dash::t('fh.dash.none_waiting', ['hours' => Dash::WAITING_HOURS]));
        if ($late > 0) {
            $newStat->descriptionColor('warning');
        }

        // Inquiries received in the period, against the previous one
        $perDay = $inquiries->countByDay('created_at', $period->from(), $period->to());
        $now = array_sum($perDay);
        $before = $inquiries->where('created_at', '>=', $previous->from())->where('created_at', '<=', $previous->to())->count();
        $received = Stat::make('fh.dash.inquiries', $now)->icon('message')->trend($now, $before)
            ->description(Dash::t('fh.dash.vs_previous', ['count' => $before]))
            ->sparkline($period->series($perDay)['values'])
            ->url(Dash::listUrl('inquiries'));

        // Published properties, by what is on the market
        $published = $context->query(ListingResource::class)->where('publish_status', '=', 'published');
        $byStatus = $published->countBy('status');
        $added = $published->where('created_at', '>=', $period->from())->count();
        $properties = Stat::make('fh.dash.published_properties', $published->count())->icon('home')->change($added)
            ->description(Dash::t('fh.dash.on_market', ['sale' => $byStatus['for_sale'] ?? 0, 'rent' => $byStatus['for_rent'] ?? 0, 'plan' => $byStatus['off_plan'] ?? 0]))
            ->url(Dash::listUrl('listings', ['publish_status' => 'published']));

        // Developments: how far they are and how many updates the period had
        $projects = $context->query(ProjectResource::class)->where('publish_status', '=', 'published');
        $count = $projects->count();
        $average = $count > 0 ? (int) round($projects->sum('progress_percent') / $count) : 0;
        $updates = $context->query(ProjectUpdateResource::class)->where('publish_status', '=', 'published')
            ->where('update_date', '>=', substr($period->from(), 0, 10))->count();
        $developments = Stat::make('fh.dash.developments', $count)->icon('building')
            ->description(Dash::t('fh.dash.progress_line', ['percent' => $average, 'count' => $updates]))
            ->url(Dash::listUrl('projects', ['publish_status' => 'published']));

        return [$newStat, $received, $properties, $developments];
    }
}
