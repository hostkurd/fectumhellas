<?php
declare(strict_types=1);

namespace App\Components;

use FloCMS\Core\Http\Request;
use FloCMS\Reactive\Attributes\Action;
use FloCMS\Reactive\Attributes\State;
use FloCMS\Reactive\Component;
use FloCMS\Support\Listings;
use FloCMS\Support\Site;

/**
 * Property search (Stage 3 D3, approved Properties mockup), mounted as
 * "search" on /properties: filters, sort, pages and grid/map view without a
 * page reload. State lives in the address (?search_city=3&search_page=2),
 * so a search can be shared; the home quick search sends the same names.
 * Without JavaScript the filter bar is an ordinary GET form.
 */
final class PropertySearch extends Component
{
    public const BUDGETS = [250000, 500000, 750000, 1000000, 2000000, 3000000];
    public const RENTS = [1000, 1500, 2000, 3000, 5000];
    public const AREAS = [60, 80, 120, 160, 200, 300];

    #[State(rules: 'in:all,for_sale,for_rent,off_plan', query: 'status')] public string $status = 'all';
    #[State(rules: 'integer|min:0|max:1000000', query: 'city')] public int $city = 0;
    #[State(rules: 'integer|min:0|max:1000000', query: 'type')] public int $type = 0;
    #[State(rules: 'integer|min:0|max:100000000', query: 'max')] public int $max = 0;
    #[State(rules: 'integer|min:0|max:20', query: 'beds')] public int $beds = 0;
    #[State(rules: 'integer|min:0|max:100000', query: 'area')] public int $area = 0;
    #[State(rules: 'in:,new,construction,resale', query: 'build')] public string $build = '';
    /** Amenity ids, comma separated ("3,7"): flocms-reactive state is scalar only. */
    #[State(rules: 'max:200', query: 'features')] public string $features = '';
    #[State(query: 'closed')] public bool $closed = false;
    #[State(rules: 'in:new,price_asc,price_desc', query: 'sort')] public string $sort = 'new';
    #[State(rules: 'integer|min:1|max:10000', query: 'page')] public int $page = 1;
    #[State(rules: 'in:grid,map', query: 'view')] public string $view = 'grid';
    /** "More filters" panel open (not in the address). */
    #[State] public bool $more = false;
    /** Set by a button before an action (feature id, filter name). */
    #[State(rules: 'max:40')] public string $pick = '';

    public function authorize(Request $request): void {}

    /** A changed filter starts again at page 1; a budget that doesn't fit the tab is dropped. */
    public function updated(string $property): void
    {
        if (!in_array($property, ['page', 'view', 'more', 'pick', 'sort'], true)) {
            $this->page = 1;
        }
        if ($property === 'status') {
            $this->max = 0;
        }
        $this->features = self::cleanIds($this->features);
    }

    /** Toggle one feature (amenity id in $pick). */
    #[Action(validate: false)]
    public function feature(): void
    {
        $ids = self::ids($this->features);
        $id = (int) $this->pick;
        $ids = in_array($id, $ids, true) ? array_values(array_diff($ids, [$id])) : [...$ids, $id];
        $this->features = self::cleanIds(implode(',', $ids));
        $this->page = 1;
    }

    /** Remove one filter (its name in $pick, or "feature:<id>"). */
    #[Action(validate: false)]
    public function clear(): void
    {
        $name = $this->pick;
        if (str_starts_with($name, 'feature:')) {
            $this->pick = substr($name, 8);
            $this->feature();
            return;
        }
        match ($name) {
            'city' => $this->city = 0, 'type' => $this->type = 0, 'max' => $this->max = 0,
            'beds' => $this->beds = 0, 'area' => $this->area = 0, 'build' => $this->build = '',
            'closed' => $this->closed = false, default => null,
        };
        $this->page = 1;
    }

    #[Action(validate: false)]
    public function reset(): void
    {
        $this->city = $this->type = $this->max = $this->beds = $this->area = 0;
        $this->build = $this->features = '';
        $this->closed = false;
        $this->page = 1;
    }

    /** Open or close "More filters"; also used by Show results. */
    #[Action(validate: false)]
    public function toggleMore(): void { $this->more = !$this->more; }

    /** Tabs, pages and views are set with data-rx-set before this. */
    #[Action(validate: false)]
    public function go(): void {}

    public function render(): string
    {
        $features = self::ids($this->features);
        $result = Listings::search([
            'status' => $this->status === 'all' ? '' : $this->status, 'city' => $this->city, 'type' => $this->type,
            'max' => $this->max, 'beds' => $this->beds, 'area' => $this->area, 'build' => $this->build,
            'features' => $features, 'closed' => $this->closed, 'sort' => $this->sort, 'page' => $this->page,
        ]);
        $cities = Listings::lookup('cities');
        $types = Listings::lookup('property_types');
        $amenities = Listings::lookup('amenities');
        $budgets = $this->status === 'for_rent' ? self::RENTS : self::BUDGETS;

        // Removable filter chips
        $chips = [];
        if ($this->city > 0 && isset($cities[$this->city])) { $chips[] = ['label' => $cities[$this->city], 'name' => 'city']; }
        if ($this->type > 0 && isset($types[$this->type])) { $chips[] = ['label' => $types[$this->type], 'name' => 'type']; }
        if ($this->max > 0) { $chips[] = ['label' => __('fh.filter.up_to', ['price' => Site::price($this->max)]), 'name' => 'max']; }
        if ($this->beds > 0) { $chips[] = ['label' => __('fh.beds_min', ['count' => (string) $this->beds]), 'name' => 'beds']; }
        if ($this->area > 0) { $chips[] = ['label' => __('fh.area_min', ['area' => (string) $this->area]), 'name' => 'area']; }
        if ($this->build !== '') { $chips[] = ['label' => __('fh.build.' . $this->build), 'name' => 'build']; }
        foreach ($features as $id) {
            if (isset($amenities[$id])) { $chips[] = ['label' => $amenities[$id], 'name' => 'feature:' . $id]; }
        }
        if ($this->closed) { $chips[] = ['label' => __('fh.incl_closed'), 'name' => 'closed']; }
        $moreCount = ($this->area > 0 ? 1 : 0) + ($this->build !== '' ? 1 : 0) + count($features) + ($this->closed ? 1 : 0);

        return $this->view(ROOT . '/views/components/property-search.html', [
            's' => $this, 'result' => $result, 'cities' => $cities, 'types' => $types, 'amenities' => $amenities,
            'budgets' => $budgets, 'areas' => self::AREAS, 'features' => $features, 'chips' => $chips, 'moreCount' => $moreCount,
            'tabs' => ['all' => 'fh.tab.all', 'for_sale' => 'fh.status.for_sale', 'for_rent' => 'fh.status.for_rent', 'off_plan' => 'fh.status.off_plan'],
            'action' => Site::url('properties'),
            'pageUrl' => fn (int $page): string => $this->url(['page' => $page]),
            'tabUrl' => fn (string $status): string => $this->url(['status' => $status, 'page' => 1, 'max' => 0]),
            'mapPoints' => $result['points'],
        ]);
    }

    /** The address of this search with some values changed (links that also work without JavaScript). */
    private function url(array $change): string
    {
        $values = array_merge([
            'status' => $this->status, 'city' => $this->city, 'type' => $this->type, 'max' => $this->max, 'beds' => $this->beds,
            'area' => $this->area, 'build' => $this->build, 'features' => $this->features, 'closed' => $this->closed ? '1' : '',
            'sort' => $this->sort, 'page' => $this->page, 'view' => $this->view,
        ], $change);
        $defaults = ['status' => 'all', 'city' => 0, 'type' => 0, 'max' => 0, 'beds' => 0, 'area' => 0, 'build' => '', 'features' => '', 'closed' => '', 'sort' => 'new', 'page' => 1, 'view' => 'grid'];
        $query = [];
        foreach ($values as $key => $value) {
            if ((string) $value !== (string) $defaults[$key]) { $query['search_' . $key] = (string) $value; }
        }
        return Site::url('properties') . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    /** @return list<int> */
    private static function ids(string $csv): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', $csv)), static fn (int $id): bool => $id > 0)));
    }

    private static function cleanIds(string $csv): string
    {
        return implode(',', array_slice(self::ids($csv), 0, 12));
    }
}
