<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Table\Columns\CountColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Table;

/** Lookup (design note D4): one name in English, Arabic and Greek, in editors' own order. */
final class CityResource extends Resource
{
    protected static string $table = 'cities';
    protected static string $slug = 'cities';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.city';
    protected static string $pluralLabel = 'fh.res.cities';
    protected static ?string $icon = 'map-pin';
    protected static int $navigationSort = 60;
    protected static string $formMode = 'modal';
    protected static string $translationMode = 'field';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('fh.f.name')->translatable()->rules('required|max:120')->columnSpan('full'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('fh.f.name')->searchable()->sortable(),
                CountColumn::make('listings_count')->label('fh.res.listings')->relation(ListingResource::class, 'city_id'),
                TranslationsColumn::make('translations'),
            ])
            ->reorderable('sort');
    }
}
