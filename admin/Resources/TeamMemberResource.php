<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Table\Columns\BooleanColumn;
use FloCMS\Admin\Table\Columns\MediaColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Table;

/** The team on the About page, in editors' own order. */
final class TeamMemberResource extends Resource
{
    protected static string $table = 'team_members';
    protected static string $slug = 'team';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.team_member';
    protected static string $pluralLabel = 'fh.res.team';
    protected static ?string $icon = 'users';
    protected static int $navigationSort = 40;
    protected static string $formMode = 'slide-over';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('fh.f.person_name')->rules('required|max:120'),
            TextInput::make('role')->label('fh.f.role')->translatable()->rules('required|max:120'),
            Textarea::make('bio')->label('fh.f.bio')->translatable()->rows(4)->rules('max:800')->columnSpan('full'),
            MediaPicker::make('photo_id')->label('fh.f.photo')->folder('Team')->aspectRatio('1:1'),
            TextInput::make('email')->label('fh.f.email')->email()->rules('max:190'),
            TextInput::make('linkedin')->label('fh.f.linkedin')->url()->rules('max:255'),
            Toggle::make('visible')->label('fh.f.visible')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                MediaColumn::make('photo_id')->label('fh.f.photo'),
                TextColumn::make('name')->label('fh.f.person_name')->searchable()->sortable(),
                TextColumn::make('role')->label('fh.f.role')->translatable(),
                BooleanColumn::make('visible')->label('fh.f.visible'),
                TranslationsColumn::make('translations')->fields(['role', 'bio']),
            ])
            ->reorderable('sort');
    }
}
