<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;

/** The top of an inner page: eyebrow, H1 heading, text and an optional photo. */
final class PageHeader extends Block
{
    protected static string $type = 'page_header';
    protected static string $category = 'site';
    protected static string $icon = 'heading';
    protected static ?string $label = 'fh.block.page_header';
    protected static ?string $description = 'fh.block.page_header_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(3)->rules('max:500')->columnSpan('full'),
            MediaPicker::make('image')->label('fh.bf.image')->folder('Pages'),
            TextInput::make('button')->label('fh.bf.button')->rules('max:40'),
            TextInput::make('button_link')->label('fh.bf.button_link')->rules('max:255')->help('fh.bf.link_anchor_help'),
            TextInput::make('button2')->label('fh.bf.button2')->rules('max:40'),
            TextInput::make('link2')->label('fh.bf.link2')->rules('max:255')->help('fh.bf.link_anchor_help'),
        ];
    }

    /** The breadcrumb (Home / this page), shown when the small label is empty. */
    public function data(array $fields): array
    {
        $page = function_exists('current_page') ? current_page() : null;
        return [
            'home_url' => \FloCMS\Support\Site::url(''),
            'page_title' => $page !== null && !$page->isHome() ? (string) $page->title() : '',
        ];
    }
}
