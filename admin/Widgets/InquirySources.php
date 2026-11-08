<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use FloCMS\Admin\Widgets\ChartWidget;
use FloCMS\Admin\Widgets\WidgetContext;
use FloCMS\Support\Catalog;

/** Where the period's inquiries were sent from: property, development, contact page, saved list, service. */
final class InquirySources extends ChartWidget
{
    protected string $title = 'fh.dash.sources';
    protected array $resources = [InquiryResource::class];
    protected string $type = 'donut';
    protected int $sort = 30;

    public function headerSubtitle(WidgetContext $context): string
    {
        return $context->period()->label();
    }

    protected function data(WidgetContext $context): array
    {
        $counts = $context->query(InquiryResource::class)->where('created_at', '>=', $context->period()->from())->countBy('type');
        $labels = [];
        $values = [];
        foreach (Catalog::inquiryTypes() as $type => $label) {
            if (($counts[$type] ?? 0) > 0) {
                $labels[] = $label;
                $values[] = $counts[$type];
            }
        }
        return ['labels' => $labels, 'values' => $values, 'total' => 'fh.dash.inquiries_word'];
    }
}
