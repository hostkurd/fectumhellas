<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\Repeater;

/** Numbered steps on a line ("How buying works"), up to six. */
final class Steps extends Block
{
    protected static string $type = 'steps';
    protected static string $category = 'site';
    protected static string $icon = 'list';
    protected static ?string $label = 'fh.block.steps';
    protected static ?string $description = 'fh.block.steps_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:400')->columnSpan('full'),
            Repeater::make('items')->label('fh.bf.steps')->schema([
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:240'),
            ])->itemLabel('title')->maxItems(6)->columns(2)->columnSpan('full'),
        ];
    }
}
