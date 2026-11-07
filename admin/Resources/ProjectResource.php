<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\BelongsTo;
use FloCMS\Admin\Form\Fields\FileUpload;
use FloCMS\Admin\Form\Fields\KeyValue;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\RichEditor;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\Slug;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Form\Layout\Section;
use FloCMS\Admin\Relations\RelationManager;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\CountColumn;
use FloCMS\Admin\Table\Columns\MediaColumn;
use FloCMS\Admin\Table\Columns\RelationColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Filters\SelectFilter;
use FloCMS\Admin\Table\Table;
use FloCMS\Support\Catalog;

/**
 * Developments (e.g. Residence One). Admin URL /admin/projects (a public
 * DevelopmentsController would also answer /admin/developments); public URL
 * /<lang>/developments/<slug>. Its residences are listings (D1) and its
 * progress updates are records of their own (D6), both managed below the form.
 */
final class ProjectResource extends Resource
{
    protected static string $table = 'projects';
    protected static string $slug = 'projects';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.project';
    protected static string $pluralLabel = 'fh.res.projects';
    protected static ?string $icon = 'building';
    protected static int $navigationSort = 20;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('details')->label('fh.s.details')->schema([
                TextInput::make('title')->label('fh.f.title')->translatable()->rules('required|max:190')->columnSpan('full'),
                Slug::make('slug')->label('fh.f.slug')->from('title')->unique()->rules('required|max:190')->help('fh.h.slug'),
                Textarea::make('summary')->label('fh.f.summary')->translatable()->rows(3)->rules('max:300')->help('fh.h.summary'),
                RichEditor::make('description')->label('fh.f.description')->translatable(),
                KeyValue::make('key_facts')->label('fh.f.key_facts')->keyLabel('fh.f.fact')->valueLabel('fh.f.fact_value')
                    ->translatableValues()->help('fh.h.key_facts')->columnSpan('full'),
            ]),
            Section::make('construction')->label('fh.s.construction')->columns(3)->schema([
                Select::make('progress')->label('fh.f.progress')->options(Catalog::projectProgress())->default('planned')->required(),
                TextInput::make('progress_percent')->label('fh.f.progress_percent')->numeric()->rules('integer|min:0|max:100')->default('0'),
                TextInput::make('expected_completion')->label('fh.f.expected_completion')->translatable()->rules('max:60')->placeholder('Q4 2027'),
                Repeater::make('stages')->label('fh.f.stages')->schema([
                    TextInput::make('name')->label('fh.f.stage')->translatable()->required()->rules('max:80'),
                    Select::make('status')->label('fh.f.status')->options(Catalog::stageStatuses())->required(),
                    TextInput::make('date')->label('fh.f.stage_date')->translatable()->rules('max:40')->placeholder('Mar 2026'),
                ])->itemLabel('name')->addLabel('fh.f.add_stage')->columns(3)->maxItems(20)->columnSpan('full'),
            ]),
            Section::make('location')->label('fh.s.location')->columns(2)->schema([
                BelongsTo::make('city_id')->label('fh.f.city')->resource(CityResource::class),
                TextInput::make('area')->label('fh.f.area')->translatable()->rules('max:120')->help('fh.h.area'),
                TextInput::make('address')->label('fh.f.address')->rules('max:255')->columnSpan('full'),
                TextInput::make('latitude')->label('fh.f.latitude')->numeric()->rules('numeric|min:-90|max:90'),
                TextInput::make('longitude')->label('fh.f.longitude')->numeric()->rules('numeric|min:-180|max:180'),
            ]),
            Section::make('media')->label('fh.s.media')->schema([
                MediaPicker::make('gallery')->label('fh.f.gallery')->multiple()->max(40)->folder('Developments/Galleries')->columnSpan('full'),
                TextInput::make('video_url')->label('fh.f.video_url')->url()->rules('max:255')->help('fh.h.video_url'),
                FileUpload::make('brochure')->label('fh.f.brochure')->public()->acceptedFileTypes(['application/pdf'])
                    ->maxSize(16384)->help('fh.h.brochure'),
            ]),
            Section::make('seo')->label('fh.s.seo')->collapsible()->description('fh.h.seo')->schema([
                TextInput::make('meta_title')->label('fh.f.meta_title')->translatable()->rules('max:70')->columnSpan('full'),
                Textarea::make('meta_description')->label('fh.f.meta_description')->translatable()->rows(3)->rules('max:170'),
                MediaPicker::make('social_image_id')->label('fh.f.social_image')->folder('Social')->aspectRatio('16:9'),
            ]),
        ])->aside([
            Section::make('publishing')->label('fh.s.publishing')->columns(1)->schema([
                Select::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses())
                    ->segmented()->default('draft')->required()->help('fh.h.publish_checks_project'),
                Toggle::make('featured')->label('fh.f.featured')->help('fh.h.featured'),
            ]),
            Section::make('cover')->label('fh.f.cover')->columns(1)->schema([
                MediaPicker::make('cover_id')->label('fh.f.cover')->folder('Developments/Covers')->aspectRatio('4:3'),
            ]),
        ]);
    }

    public function relations(): array
    {
        return [
            RelationManager::make(ListingResource::class, 'project_id')->label('fh.res.residences')
                ->columns(['reference', 'title', 'status', 'price', 'publish_status'])
                ->defaultSort('reference', 'asc')->emptyText('fh.empty.residences'),
            RelationManager::make(ProjectUpdateResource::class, 'project_id')->label('fh.res.project_updates')
                ->defaultSort('update_date', 'desc')->emptyText('fh.empty.project_updates'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                MediaColumn::make('cover_id')->label('fh.f.cover')->toggleable(),
                TextColumn::make('title')->label('fh.f.title')->searchable()->sortable(),
                RelationColumn::make('city_id')->label('fh.f.city')->resource(CityResource::class)->toggleable(),
                BadgeColumn::make('progress')->label('fh.f.progress')->labels(Catalog::projectProgress())->colors(Catalog::projectProgressColors()),
                TextColumn::make('progress_percent')->label('fh.f.progress_percent')->sortable()
                    ->format(static fn ($value): string => (int) $value . '%'),
                CountColumn::make('residences')->label('fh.res.residences')->relation(ListingResource::class, 'project_id'),
                BadgeColumn::make('publish_status')->label('fh.f.publish_status')->labels(Catalog::publishStatuses())->colors(Catalog::publishColors()),
                TranslationsColumn::make('translations')->fields(['title', 'description', 'summary']),
            ])
            ->filters([
                SelectFilter::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses()),
                SelectFilter::make('progress')->label('fh.f.progress')->options(Catalog::projectProgress()),
            ])
            ->reorderable('sort');
    }

    public function validate(array $state, ?object $record): array
    {
        $errors = [];
        $videoUrl = trim((string) ($state['video_url'] ?? ''));
        if ($videoUrl !== '' && !Catalog::isVideoLink($videoUrl)) {
            $errors['video_url'] = AdminLang::label('fh.e.video_url');
        }
        if (($state['latitude'] ?? '') === '' xor ($state['longitude'] ?? '') === '') {
            $errors['longitude'] = AdminLang::label('fh.e.coordinates');
        }
        if (($state['publish_status'] ?? 'draft') === 'published') {
            foreach (['description' => ListingResource::englishText($state['description'] ?? null) === '',
                      'city_id' => (string) ($state['city_id'] ?? '') === '',
                      'cover_id' => (string) ($state['cover_id'] ?? '') === ''] as $field => $missing) {
                if ($missing) {
                    $errors[$field] = AdminLang::label('fh.e.required_to_publish');
                }
            }
        }
        return $errors;
    }
}
