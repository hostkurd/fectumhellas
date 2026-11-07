<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;

/** The team from Content → Team, in its order. */
final class TeamMembers extends Block
{
    protected static string $type = 'team_members';
    protected static string $category = 'site';
    protected static string $icon = 'users';
    protected static ?string $label = 'fh.block.team_members';
    protected static ?string $description = 'fh.block.team_members_desc';

    /** Draws its own full-width section and spacing. */
    public function defaultWidth(): string { return 'full'; }

    public function fields(): array
    {
        return [
            TextInput::make('eyebrow')->label('fh.bf.eyebrow')->rules('max:60'),
            TextInput::make('heading')->label('fh.bf.heading')->rules('max:160')->columnSpan('full'),
            Textarea::make('text')->label('fh.bf.text')->rows(2)->rules('max:400')->columnSpan('full'),
            \FloCMS\Admin\Form\Fields\Toggle::make('company')->label('fh.bf.show_company')->default('1'),
        ];
    }

    public function data(array $fields): array
    {
        return ['people' => \FloCMS\Support\Listings::team(), 'details' => ($fields['company'] ?? '1') === '1' ? CompanyDetails::details() : []];
    }
}
