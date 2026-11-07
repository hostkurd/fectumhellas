<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Toggle;

/** Heading on one side, paragraphs on the other ("Who we are"). */
final class Story extends Block
{
    protected static string $type = 'story';
    protected static string $category = 'site';
    protected static string $icon = 'text';
    protected static ?string $label = 'fh.block.story';
    protected static ?string $description = 'fh.block.story_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.paragraphs')->rows(8)->required()->rules('max:3000')->help('fh.bf.paragraphs_help')->columnSpan('full'),
        ];
    }
}
