<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\ChartWidget;
use FloCMS\Admin\Widgets\WidgetContext;

/** Inquiries per day (per week or month for longer periods), the previous period dashed. */
final class InquiriesPerDay extends ChartWidget
{
    protected string $title = 'fh.dash.inquiries_per_day';
    protected array $resources = [InquiryResource::class];
    protected string $type = 'line';
    protected int|string $columnSpan = 2;
    protected int $sort = 20;

    public function headerSubtitle(WidgetContext $context): string
    {
        return $context->period()->label();
    }

    public function headerLink(WidgetContext $context): ?array
    {
        return ['label' => Dash::t('fh.dash.open_inquiries'), 'url' => Dash::listUrl('inquiries', ['status' => 'new'], 'created_at')];
    }

    protected function data(WidgetContext $context): array
    {
        $inquiries = $context->query(InquiryResource::class);
        $period = $context->period();
        $previous = $period->previous();
        $now = $period->series($inquiries->countByDay('created_at', $period->from(), $period->to()));
        $before = $previous->series($inquiries->countByDay('created_at', $previous->from(), $previous->to()), like: $period);
        return $now + ['previous' => $before['values'], 'label' => 'fh.dash.this_period', 'previousLabel' => 'fh.dash.previous_period'];
    }
}
