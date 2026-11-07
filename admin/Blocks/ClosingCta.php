<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;

/** A blue closing band with a heading, text and two buttons. */
final class ClosingCta extends Block
{
    protected static string $type = 'closing_cta';
    protected static string $category = 'site';
    protected static string $icon = 'arrow-right';
    protected static ?string $label = 'fh.block.closing_cta';
    protected static ?string $description = 'fh.block.closing_cta_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('heading')->label('fh.bf.heading')->required()->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:400')->columnSpan('full'),
            TextInput::make('button')->label('fh.bf.button')->rules('max:60'),
            TextInput::make('link')->label('fh.bf.link')->rules('max:255'),
            TextInput::make('button2')->label('fh.bf.button2')->rules('max:60'),
            TextInput::make('link2')->label('fh.bf.link2')->rules('max:255'),
        ];
    }

    public function check(array $fields): array
    {
        $errors = [];
        foreach ([['button', 'link'], ['button2', 'link2']] as [$b, $l]) {
            if ((trim((string) ($fields[$b] ?? '')) === '') !== (trim((string) ($fields[$l] ?? '')) === '')) {
                $errors[$l] = \FloCMS\Admin\Support\AdminLang::label('fh.e.button_link');
            }
        }
        return $errors;
    }
}
