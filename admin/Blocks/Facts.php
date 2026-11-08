<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Select;

/** Up to four short facts with icons on one white card ("At a glance"); right after a page header it overlaps the photo. */
final class Facts extends Block
{
    protected static string $type = 'facts';
    protected static string $category = 'site';
    protected static string $icon = 'list';
    protected static ?string $label = 'fh.block.facts';
    protected static ?string $description = 'fh.block.facts_desc';

    /** Icons the facts can use (drawn in views/blocks/facts.html). */
    public const ICONS = ['globe', 'id', 'remote', 'shield', 'home', 'key', 'document', 'chat'];

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        $icons = [];
        foreach (self::ICONS as $icon) {
            $icons[$icon] = 'fh.icon.' . $icon;
        }
        return [
            TextInput::make('heading')->label('fh.bf.heading_hidden')->rules('max:160')->help('fh.bf.heading_hidden_help')->columnSpan('full'),
            Repeater::make('items')->label('fh.bf.facts')->schema([
                Select::make('icon')->label('fh.bf.icon')->options($icons)->default('globe'),
                TextInput::make('title')->label('fh.bf.title')->required()->rules('max:80'),
                Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:240')->columnSpan('full'),
            ])->itemLabel('title')->maxItems(4)->columns(2)->columnSpan('full'),
        ];
    }
}
