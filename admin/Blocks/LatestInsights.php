<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Select;

/** The newest published articles of Insights. */
final class LatestInsights extends Block
{
    protected static string $type = 'latest_insights';
    protected static string $category = 'site';
    protected static string $icon = 'newspaper';
    protected static ?string $label = 'fh.block.latest_insights';
    protected static ?string $description = 'fh.block.latest_insights_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Select::make('count')->label('fh.bf.count')->options(['3' => '3', '6' => '6'])->default('3'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60')->help('fh.bf.default_help'),
        ];
    }

    public function data(array $fields): array
    {
        return ['posts' => \FloCMS\Support\Listings::articles(max(1, min(6, (int) ($fields['count'] ?? 3))))];
    }
}
