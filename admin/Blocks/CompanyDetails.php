<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;

/** Legal name, G.E.MI., licence and VAT numbers and offices, from Settings → Company details. */
final class CompanyDetails extends Block
{
    protected static string $type = 'company_details';
    protected static string $category = 'site';
    protected static string $icon = 'briefcase';
    protected static ?string $label = 'fh.block.company_details';
    protected static ?string $description = 'fh.block.company_details_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:400')->columnSpan('full'),
        ];
    }

    public function data(array $fields): array
    {
        $s = static fn (string $key): string => \FloCMS\Support\Site::setting($key);
        return ['details' => array_filter([
            'fh.set.legal_name' => $s('company.legal_name'),
            'fh.set.gemi' => $s('company.gemi'),
            'fh.set.licence' => $s('company.licence'),
            'fh.set.vat' => $s('company.vat'),
            'fh.set.registered_office' => $s('company.registered_office'),
            'fh.set.office' => $s('contact.address'),
        ], static fn (string $v): bool => $v !== '')];
    }
}
