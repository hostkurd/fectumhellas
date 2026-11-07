<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;

/** A search bar under the home slider: location, type, bedrooms and budget, sent to the Properties page. */
final class QuickSearch extends Block
{
    protected static string $type = 'quick_search';
    protected static string $category = 'site';
    protected static string $icon = 'search';
    protected static ?string $label = 'fh.block.quick_search';
    protected static ?string $description = 'fh.block.quick_search_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('heading')->label('fh.bf.heading')->rules('max:80')->help('fh.bf.default_help')->columnSpan('full'),
        ];
    }

    public function data(array $fields): array
    {
        return ['cities' => \FloCMS\Support\Listings::lookup('cities'), 'types' => \FloCMS\Support\Listings::lookup('property_types')];
    }
}
