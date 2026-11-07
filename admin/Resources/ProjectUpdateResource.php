<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\BelongsTo;
use FloCMS\Admin\Form\Fields\Date;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\DateColumn;
use FloCMS\Admin\Table\Columns\MediaColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Table;
use FloCMS\Support\Catalog;

/**
 * Dated construction updates with photos (D6). Managed on the development's
 * edit page, so it is not in the sidebar.
 */
final class ProjectUpdateResource extends Resource
{
    protected static string $table = 'project_updates';
    protected static string $slug = 'project-updates';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.project_update';
    protected static string $pluralLabel = 'fh.res.project_updates';
    protected static ?string $icon = 'camera';
    protected static bool $navigation = false;
    protected static string $formMode = 'modal';

    public function form(Form $form): Form
    {
        return $form->schema([
            BelongsTo::make('project_id')->label('fh.f.project')->resource(ProjectResource::class)->required(),
            Date::make('update_date')->label('fh.f.update_date')->required(),
            Select::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses())
                ->segmented()->default('published')->required(),
            TextInput::make('title')->label('fh.f.title')->translatable()->rules('required|max:190')->columnSpan('full'),
            Textarea::make('body')->label('fh.f.update_text')->translatable()->rows(5)->rules('max:3000'),
            MediaPicker::make('photos')->label('fh.f.photos')->multiple()->max(20)->folder('Developments/Updates')->columnSpan('full'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                DateColumn::make('update_date')->label('fh.f.update_date')->sortable(),
                TextColumn::make('title')->label('fh.f.title')->searchable()->sortable(),
                MediaColumn::make('photos')->label('fh.f.photos')->limit(3),
                BadgeColumn::make('publish_status')->label('fh.f.publish_status')->labels(Catalog::publishStatuses())->colors(Catalog::publishColors()),
                TranslationsColumn::make('translations')->fields(['title', 'body']),
            ])
            ->defaultSort('update_date', 'desc');
    }
}
