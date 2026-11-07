<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\Select;

/** The featured properties (or the newest ones) as cards, with a link to all properties. */
final class FeaturedProperties extends Block
{
    protected static string $type = 'featured_properties';
    protected static string $category = 'site';
    protected static string $icon = 'home';
    protected static ?string $label = 'fh.block.featured_properties';
    protected static ?string $description = 'fh.block.featured_properties_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:300')->columnSpan('full'),
            Select::make('count')->label('fh.bf.count')->options(['3' => '3', '6' => '6', '9' => '9'])->default('3'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60')->help('fh.bf.default_help'),
        ];
    }

    public function data(array $fields): array
    {
        return ['items' => \FloCMS\Support\Listings::featured(max(1, min(9, (int) ($fields['count'] ?? 3))))];
    }
}
