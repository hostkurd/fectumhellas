<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Toggle;

/** The office address, phone and email beside the map ("Our office"). */
final class OfficeMap extends Block
{
    protected static string $type = 'office_map';
    protected static string $category = 'site';
    protected static string $icon = 'map';
    protected static ?string $label = 'fh.block.office_map';
    protected static ?string $description = 'fh.block.office_map_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            TextInput::make('button')->label('fh.bf.button')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255')->help('fh.bf.link_help'),
        ];
    }

    /** Address, phone and email from Settings › Contact details. */
    public function data(array $fields): array
    {
        $s = static fn (string $key): string => \FloCMS\Support\Site::setting($key);
        return ['address' => $s('contact.address'), 'phone' => $s('contact.phone'), 'email' => $s('contact.email')];
    }
}
