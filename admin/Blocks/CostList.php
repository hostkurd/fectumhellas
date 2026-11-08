<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\Repeater;

/** Heading and link beside a numbered list on a white card, with a note under it ("Costs to plan for"). */
final class CostList extends Block
{
    protected static string $type = 'cost_list';
    protected static string $category = 'site';
    protected static string $icon = 'list';
    protected static ?string $label = 'fh.block.cost_list';
    protected static ?string $description = 'fh.block.cost_list_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:400')->columnSpan('full'),
            TextInput::make('link_text')->label('fh.bf.link_text')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
            Repeater::make('items')->label('fh.bf.list_items')->schema([
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:240'),
            ])->itemLabel('title')->maxItems(8)->columns(2)->columnSpan('full'),
            Textarea::make('note')->label('fh.bf.note')->rows(2)->rules('max:240')->columnSpan('full'),
        ];
    }
}
