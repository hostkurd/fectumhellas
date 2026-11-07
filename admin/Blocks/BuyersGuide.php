<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;

/** International buyers: a photo band with questions that link to the guide. */
final class BuyersGuide extends Block
{
    protected static string $type = 'buyers_guide';
    protected static string $category = 'site';
    protected static string $icon = 'book';
    protected static ?string $label = 'fh.block.buyers_guide';
    protected static ?string $description = 'fh.block.buyers_guide_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:500')->columnSpan('full'),
            MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
            Repeater::make('questions')->label('fh.bf.questions')->schema([
                TextInput::make('question')->label('fh.bf.question')->required()->rules('max:160'),
                TextInput::make('link')->label('fh.bf.link')->rules('max:255'),
            ])->itemLabel('question')->maxItems(8)->columns(2)->columnSpan('full'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
        ];
    }
}
