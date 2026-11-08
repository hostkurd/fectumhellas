<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\BarListWidget;
use FloCMS\Admin\Widgets\WidgetContext;

/** The language visitors wrote in, as a share of the period's inquiries. */
final class VisitorLanguages extends BarListWidget
{
    protected string $title = 'fh.dash.languages';
    protected array $resources = [InquiryResource::class];
    protected bool $percent = true;
    protected int $sort = 100;

    private const NAMES = ['en' => 'English', 'el' => 'Ελληνικά', 'ar' => 'العربية'];
    private const COLORS = ['en' => 'primary', 'el' => 'chart2', 'ar' => 'chart3'];

    public function headerSubtitle(WidgetContext $context): string
    {
        return $context->period()->label();
    }

    protected function items(WidgetContext $context): array
    {
        $counts = $context->query(InquiryResource::class)->where('created_at', '>=', $context->period()->from())->countBy('lang');
        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }
        $items = [];
        foreach (self::NAMES as $lang => $name) {
            $items[] = ['label' => $name, 'value' => (int) round(($counts[$lang] ?? 0) * 100 / $total), 'color' => self::COLORS[$lang]];
        }
        return $items;
    }

    protected function footer(WidgetContext $context): string
    {
        $total = $context->query(InquiryResource::class)->where('created_at', '>=', $context->period()->from())->count();
        return $total > 0 ? Dash::t('fh.dash.languages_footer', ['count' => $total]) : '';
    }
}
