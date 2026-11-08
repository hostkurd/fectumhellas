<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\CityResource;
use App\Admin\Resources\ProjectResource;
use App\Admin\Widgets\Support\Dash;
use FloCMS\Admin\Widgets\ProgressWidget;
use FloCMS\Admin\Widgets\WidgetContext;

/** Each published development: progress, city and expected completion, in the site's order. */
final class DevelopmentsProgress extends ProgressWidget
{
    protected string $title = 'fh.dash.developments';
    protected array $resources = [ProjectResource::class, CityResource::class];
    protected int $sort = 70;

    public function headerLink(WidgetContext $context): ?array
    {
        return ['label' => Dash::t('fh.dash.see_all'), 'url' => $context->resourceUrl(ProjectResource::class)];
    }

    protected function items(WidgetContext $context): array
    {
        $projects = $context->query(ProjectResource::class)->where('publish_status', '=', 'published')
            ->records(['id', 'title', 'city_id', 'progress_percent', 'expected_completion'], 'sort', 'asc', 6);
        $cityIds = array_values(array_unique(array_filter(array_map(static fn (object $p): int => (int) ($p->city_id ?? 0), $projects))));
        $cities = [];
        if ($cityIds !== []) {
            foreach ($context->query(CityResource::class)->whereIn('id', $cityIds)->records(['id', 'name'], 'id', 'asc', 20) as $city) {
                $cities[(int) $city->id] = Dash::text($city->name);
            }
        }
        $items = [];
        foreach ($projects as $project) {
            $item = ['label' => Dash::text($project->title), 'value' => max(0, min(100, (int) $project->progress_percent)),
                'url' => $context->url('projects/edit/' . (int) $project->id)];
            $city = $cities[(int) ($project->city_id ?? 0)] ?? '';
            if ($city !== '') {
                $item['meta'] = $city;
            }
            $expected = Dash::text($project->expected_completion ?? '');
            if ($expected !== '') {
                $item['description'] = Dash::t('fh.dash.expected', ['date' => $expected]);
            }
            $items[] = $item;
        }
        return $items;
    }
}
