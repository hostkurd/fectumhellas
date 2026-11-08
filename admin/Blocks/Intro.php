<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;

/** Company introduction: photo beside eyebrow, heading, large text, text, up to four points and a link. */
final class Intro extends Block
{
    protected static string $type = 'intro';
    protected static string $category = 'site';
    protected static string $icon = 'text';
    protected static ?string $label = 'fh.block.intro';
    protected static ?string $description = 'fh.block.intro_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('statement')->label('fh.bf.statement')->rows(2)->rules('max:240')->help('fh.bf.statement_help')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(4)->rules('max:800')->columnSpan('full'),
            MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
            Repeater::make('points')->label('fh.bf.points')->schema([
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('statement')->label('fh.bf.statement')->rows(2)->rules('max:240')->help('fh.bf.statement_help')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:300'),
            ])->itemLabel('title')->maxItems(4)->columns(2)->columnSpan('full'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
        ];
    }
}
