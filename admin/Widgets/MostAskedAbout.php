<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\CityResource;
use App\Admin\Resources\InquiryResource;
use App\Admin\Resources\ListingResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Admin\Widgets\ListWidget;
use FloCMS\Admin\Widgets\WidgetContext;
use FloCMS\Support\Catalog;
use FloCMS\Support\Site;

/** The five properties with the most inquiries in the period: reference, city, status and price. */
final class MostAskedAbout extends ListWidget
{
    protected string $title = 'fh.dash.most_asked';
    protected array $resources = [InquiryResource::class, ListingResource::class, CityResource::class];
    protected string $empty = 'fh.dash.most_asked_empty';
    protected int|string $columnSpan = 2;
    protected int $sort = 90;

    public function headerSubtitle(WidgetContext $context): string
    {
        return $context->period()->label();
    }

    protected function items(WidgetContext $context): array
    {
        $counts = $context->query(InquiryResource::class)->where('created_at', '>=', $context->period()->from())
            ->whereNotNull('listing_id')->countBy('listing_id', 5);
        if ($counts === []) {
            return [];
        }
        $listings = [];
        foreach ($context->query(ListingResource::class)->whereIn('id', array_map('intval', array_keys($counts)))
            ->records(['id', 'reference', 'title', 'city_id', 'status', 'price', 'price_on_request'], 'id', 'desc', 5) as $listing) {
            $listings[(int) $listing->id] = $listing;
        }
        $cityIds = array_values(array_unique(array_filter(array_map(static fn (object $l): int => (int) ($l->city_id ?? 0), $listings))));
        $cities = [];
        if ($cityIds !== []) {
            foreach ($context->query(CityResource::class)->whereIn('id', $cityIds)->records(['id', 'name'], 'id', 'asc', 20) as $city) {
                $cities[(int) $city->id] = Dash::text($city->name);
            }
        }
        $statuses = Catalog::listingStatuses();
        $items = [];
        foreach ($counts as $id => $count) {
            $listing = $listings[(int) $id] ?? null;
            if ($listing === null) {
                continue;
            }
            $price = (int) $listing->price_on_request === 1 || $listing->price === null ? AdminLang::label('fh.price_on_request')
                : Site::price($listing->price, AdminLang::language()) . ($listing->status === 'for_rent' ? ' ' . AdminLang::label('fh.per_month') : '');
            if (AdminLang::language() === 'ar') {
                $price = "\u{2066}" . $price . "\u{2069}"; // keeps "1,200,000 €" together in right-to-left text
            }
            $parts = array_filter([(string) $listing->reference, $cities[(int) ($listing->city_id ?? 0)] ?? '', AdminLang::label($statuses[$listing->status] ?? (string) $listing->status), $price]);
            $items[] = ['icon' => 'home', 'title' => Dash::text($listing->title), 'description' => implode(' · ', $parts),
                'value' => $count, 'url' => $context->url('listings/edit/' . (int) $listing->id)];
        }
        return $items;
    }
}
