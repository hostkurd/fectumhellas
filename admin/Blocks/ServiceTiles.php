<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;

/** Full-photo service tiles with text over a dark shading. */
final class ServiceTiles extends Block
{
    protected static string $type = 'service_tiles';
    protected static string $category = 'site';
    protected static string $icon = 'layers';
    protected static ?string $label = 'fh.block.service_tiles';
    protected static ?string $description = 'fh.block.service_tiles_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->rules('max:160')->columnSpan('full'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
            Repeater::make('items')->label('fh.bf.tiles')->schema([
                MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:400'),
                TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
                TextInput::make('link')->label('fh.bf.link')->rules('max:255'),
            ])->itemLabel('title')->minItems(1)->maxItems(6)->columns(2)->columnSpan('full'),
        ];
    }
}
