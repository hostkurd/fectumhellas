<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Toggle;

/** Numbered columns on white ("What we do"). */
final class NumberedColumns extends Block
{
    protected static string $type = 'numbered_columns';
    protected static string $category = 'site';
    protected static string $icon = 'grid';
    protected static ?string $label = 'fh.block.numbered_columns';
    protected static ?string $description = 'fh.block.numbered_columns_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Repeater::make('items')->label('fh.bf.items')->schema([
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:300'),
                TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
            ])->itemLabel('title')->minItems(1)->maxItems(4)->columns(2)->columnSpan('full'),
        ];
    }
}
