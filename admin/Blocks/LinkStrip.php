<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Toggle;

/** White strip with one sentence and a link (end of the Contact page). */
final class LinkStrip extends Block
{
    protected static string $type = 'link_strip';
    protected static string $category = 'site';
    protected static string $icon = 'link';
    protected static ?string $label = 'fh.block.link_strip';
    protected static ?string $description = 'fh.block.link_strip_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('text')->label('fh.bf.text')->required()->rules('max:160')->columnSpan('full'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
        ];
    }
}
