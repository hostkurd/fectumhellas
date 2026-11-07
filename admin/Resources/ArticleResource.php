<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\DateTime;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\RichEditor;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\Slug;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Form\Layout\Section;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\DateColumn;
use FloCMS\Admin\Table\Columns\MediaColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Filters\SelectFilter;
use FloCMS\Admin\Table\Table;
use FloCMS\Support\Catalog;

/** Insights & Property Guide articles; public URL /<lang>/insights/<slug>. */
final class ArticleResource extends Resource
{
    protected static string $table = 'articles';
    protected static string $slug = 'articles';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.article';
    protected static string $pluralLabel = 'fh.res.articles';
    protected static ?string $icon = 'newspaper';
    protected static int $navigationSort = 30;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('content')->label('fh.s.content')->schema([
                TextInput::make('title')->label('fh.f.title')->translatable()->rules('required|max:190')->columnSpan('full'),
                Slug::make('slug')->label('fh.f.slug')->from('title')->unique()->rules('required|max:190')->help('fh.h.slug'),
                Textarea::make('excerpt')->label('fh.f.excerpt')->translatable()->rows(3)->rules('max:300')->help('fh.h.excerpt'),
                RichEditor::make('body')->label('fh.f.body')->translatable(),
            ]),
            Section::make('seo')->label('fh.s.seo')->collapsible()->description('fh.h.seo')->schema([
                TextInput::make('meta_title')->label('fh.f.meta_title')->translatable()->rules('max:70')->columnSpan('full'),
                Textarea::make('meta_description')->label('fh.f.meta_description')->translatable()->rows(3)->rules('max:170'),
                MediaPicker::make('social_image_id')->label('fh.f.social_image')->folder('Social')->aspectRatio('16:9'),
            ]),
        ])->aside([
            Section::make('publishing')->label('fh.s.publishing')->columns(1)->schema([
                Select::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses())
                    ->segmented()->default('draft')->required(),
                DateTime::make('published_at')->label('fh.f.published_at')->help('fh.h.published_at'),
                Toggle::make('featured')->label('fh.f.featured'),
            ]),
            Section::make('cover')->label('fh.f.cover')->columns(1)->schema([
                MediaPicker::make('cover_id')->label('fh.f.cover')->folder('Insights')->aspectRatio('16:9'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                MediaColumn::make('cover_id')->label('fh.f.cover')->toggleable(),
                TextColumn::make('title')->label('fh.f.title')->searchable()->sortable()->limit(70),
                BadgeColumn::make('publish_status')->label('fh.f.publish_status')->labels(Catalog::publishStatuses())->colors(Catalog::publishColors()),
                DateColumn::make('published_at')->label('fh.f.published_at')->sortable(),
                TranslationsColumn::make('translations')->fields(['title', 'excerpt', 'body']),
            ])
            ->filters([
                SelectFilter::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses()),
            ])
            ->defaultSort('published_at', 'desc');
    }

    /** A published article without a date gets the current time, and needs its English body. */
    public function beforeSave(array $data, ?object $record): array
    {
        if (($data['publish_status'] ?? '') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    public function validate(array $state, ?object $record): array
    {
        if (($state['publish_status'] ?? 'draft') === 'published' && ListingResource::englishText($state['body'] ?? null) === '') {
            return ['body' => AdminLang::label('fh.e.required_to_publish')];
        }
        return [];
    }
}
