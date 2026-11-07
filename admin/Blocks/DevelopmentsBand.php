<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;

/** A blue band with a photo and the published developments. */
final class DevelopmentsBand extends Block
{
    protected static string $type = 'developments_band';
    protected static string $category = 'site';
    protected static string $icon = 'building';
    protected static ?string $label = 'fh.block.developments_band';
    protected static ?string $description = 'fh.block.developments_band_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:500')->columnSpan('full'),
            MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60')->help('fh.bf.default_help'),
        ];
    }

    public function data(array $fields): array
    {
        return ['projects' => \FloCMS\Support\Listings::projects(4)];
    }
}
