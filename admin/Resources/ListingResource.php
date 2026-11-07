<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Actions\BulkAction;
use FloCMS\Admin\Actions\BulkActionContext;
use FloCMS\Admin\Actions\DeleteBulkAction;
use FloCMS\Admin\Form\Fields\BelongsTo;
use FloCMS\Admin\Form\Fields\BelongsToMany;
use FloCMS\Admin\Form\Fields\FileUpload;
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
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\BooleanColumn;
use FloCMS\Admin\Table\Columns\DateColumn;
use FloCMS\Admin\Table\Columns\MediaColumn;
use FloCMS\Admin\Table\Columns\RelationColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Columns\TranslationsColumn;
use FloCMS\Admin\Table\Filters\RelationFilter;
use FloCMS\Admin\Table\Filters\SelectFilter;
use FloCMS\Admin\Table\Filters\TabsFilter;
use FloCMS\Admin\Table\Filters\ToggleFilter;
use FloCMS\Admin\Table\Table;
use FloCMS\Support\Catalog;

/**
 * Properties. Admin URL /admin/listings (a public PropertiesController would
 * also answer /admin/properties); public URL /<lang>/properties/<slug>.
 * A listing in a development is one of its residences (design note D1).
 */
final class ListingResource extends Resource
{
    protected static string $table = 'listings';
    protected static string $slug = 'listings';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.listing';
    protected static string $pluralLabel = 'fh.res.listings';
    protected static ?string $icon = 'home';
    protected static int $navigationSort = 10;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('details')->label('fh.s.details')->schema([
                TextInput::make('title')->label('fh.f.title')->translatable()->rules('required|max:190')->columnSpan('full'),
                Slug::make('slug')->label('fh.f.slug')->from('title')->unique()->rules('required|max:190')
                    ->help('fh.h.slug'),
                TextInput::make('reference')->label('fh.f.reference')->unique()->rules('required|max:30')
                    ->placeholder('GR54-00')->help('fh.h.reference'),
                Textarea::make('summary')->label('fh.f.summary')->translatable()->rows(3)->rules('max:300')
                    ->help('fh.h.summary'),
                RichEditor::make('description')->label('fh.f.description')->translatable(),
            ]),
            Section::make('facts')->label('fh.s.facts')->columns(3)->schema([
                BelongsTo::make('property_type_id')->label('fh.f.property_type')->resource(PropertyTypeResource::class),
                TextInput::make('bedrooms')->label('fh.f.bedrooms')->numeric()->rules('integer|min:0|max:50'),
                TextInput::make('bathrooms')->label('fh.f.bathrooms')->numeric()->rules('integer|min:0|max:50'),
                TextInput::make('area_m2')->label('fh.f.area_m2')->numeric()->rules('numeric|min:0|max:1000000'),
                TextInput::make('outdoor_m2')->label('fh.f.outdoor_m2')->numeric()->rules('numeric|min:0|max:1000000')
                    ->help('fh.h.outdoor'),
                TextInput::make('floor')->label('fh.f.floor')->translatable()->rules('max:40')->placeholder('3rd'),
                TextInput::make('parking')->label('fh.f.parking')->numeric()->rules('integer|min:0|max:50'),
                Select::make('energy_class')->label('fh.f.energy_class')->options(Catalog::energyClasses()),
                TextInput::make('construction_year')->label('fh.f.construction_year')->numeric()->rules('integer|min:1800|max:2100'),
                Toggle::make('under_construction')->label('fh.f.under_construction'),
            ]),
            Section::make('location')->label('fh.s.location')->columns(2)->schema([
                BelongsTo::make('city_id')->label('fh.f.city')->resource(CityResource::class),
                TextInput::make('area')->label('fh.f.area')->translatable()->rules('max:120')->help('fh.h.area'),
                TextInput::make('address')->label('fh.f.address')->rules('max:255')->columnSpan('full')->help('fh.h.address'),
                TextInput::make('latitude')->label('fh.f.latitude')->numeric()->rules('numeric|min:-90|max:90')->placeholder('37.9838'),
                TextInput::make('longitude')->label('fh.f.longitude')->numeric()->rules('numeric|min:-180|max:180')->placeholder('23.7275'),
            ]),
            Section::make('amenities')->label('fh.s.amenities')->schema([
                BelongsToMany::make('amenities')->label('fh.f.amenities')->resource(AmenityResource::class)
                    ->pivot('listing_amenity', 'listing_id', 'amenity_id')->orderColumn('sort')->max(40)->columnSpan('full'),
            ]),
            Section::make('media')->label('fh.s.media')->schema([
                MediaPicker::make('gallery')->label('fh.f.gallery')->multiple()->max(40)->folder('Listings/Galleries')->columnSpan('full'),
                MediaPicker::make('floor_plans')->label('fh.f.floor_plans')->multiple()->max(20)->folder('Listings/Floor plans')->columnSpan('full'),
                MediaPicker::make('videos')->label('fh.f.videos')->videos()->multiple()->max(6)->folder('Listings/Videos')->columnSpan('full'),
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
                    ->segmented()->default('draft')->required()->help('fh.h.publish_checks'),
                Toggle::make('featured')->label('fh.f.featured')->help('fh.h.featured'),
            ]),
            Section::make('listing')->label('fh.s.listing')->columns(1)->schema([
                Select::make('status')->label('fh.f.status')->options(Catalog::listingStatuses())->default('for_sale')->required(),
                Toggle::make('price_on_request')->label('fh.f.price_on_request'),
                TextInput::make('price')->label('fh.f.price')->numeric()->rules('numeric|min:0|max:9999999999')
                    ->visibleWhen('price_on_request', '0')->help('fh.h.price'),
                BelongsTo::make('project_id')->label('fh.f.project')->resource(ProjectResource::class)->help('fh.h.project'),
            ]),
            Section::make('cover')->label('fh.f.cover')->columns(1)->schema([
                MediaPicker::make('cover_id')->label('fh.f.cover')->folder('Listings/Covers')->aspectRatio('4:3'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                MediaColumn::make('cover_id')->label('fh.f.cover')->toggleable(),
                TextColumn::make('title')->label('fh.f.title')->searchable()->sortable()->limit(60),
                TextColumn::make('reference')->label('fh.f.reference')->searchable()->sortable(),
                RelationColumn::make('city_id')->label('fh.f.city')->resource(CityResource::class)->toggleable(),
                RelationColumn::make('property_type_id')->label('fh.f.property_type')->resource(PropertyTypeResource::class)->toggleable(hiddenByDefault: true),
                BadgeColumn::make('status')->label('fh.f.status')->labels(Catalog::listingStatuses())->colors(Catalog::listingStatusColors()),
                TextColumn::make('price')->label('fh.f.price')->sortable()
                    ->format(static fn ($value, $record): string => self::priceText($value, $record)),
                BadgeColumn::make('publish_status')->label('fh.f.publish_status')->labels(Catalog::publishStatuses())->colors(Catalog::publishColors()),
                BooleanColumn::make('featured')->label('fh.f.featured')->toggleable(hiddenByDefault: true),
                RelationColumn::make('project_id')->label('fh.f.project')->resource(ProjectResource::class)->toggleable(hiddenByDefault: true),
                TranslationsColumn::make('translations')->fields(['title', 'description', 'summary'])->toggleable(hiddenByDefault: true),
                DateColumn::make('updated_at')->label('fh.f.updated_at')->sortable()->toggleable(hiddenByDefault: true),
            ])
            ->tabs(TabsFilter::make('status')->label('fh.f.status')->options(Catalog::listingStatuses()))
            ->filters([
                SelectFilter::make('publish_status')->label('fh.f.publish_status')->options(Catalog::publishStatuses()),
                RelationFilter::make('city_id')->label('fh.f.city')->resource(CityResource::class),
                RelationFilter::make('property_type_id')->label('fh.f.property_type')->resource(PropertyTypeResource::class),
                RelationFilter::make('project_id')->label('fh.f.project')->resource(ProjectResource::class),
                ToggleFilter::make('featured')->label('fh.f.featured'),
            ])
            ->bulkActions([
                BulkAction::make('publish')->label('fh.bulk.publish')->color('success')
                    ->permission(static::permission('update'))
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->action(function (BulkActionContext $context): void {
                        $this->assertPublishable($context->records);
                        $context->updateAll(['publish_status' => 'published']);
                    }),
                BulkAction::make('unpublish')->label('fh.bulk.unpublish')
                    ->permission(static::permission('update'))
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->action(static fn (BulkActionContext $context) => $context->updateAll(['publish_status' => 'draft'])),
                BulkAction::make('feature')->label('fh.bulk.feature')
                    ->permission(static::permission('update'))
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->action(static fn (BulkActionContext $context) => $context->updateAll(['featured' => 1])),
                BulkAction::make('unfeature')->label('fh.bulk.unfeature')
                    ->permission(static::permission('update'))
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->action(static fn (BulkActionContext $context) => $context->updateAll(['featured' => 0])),
                DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * Bulk Publish skips the edit form, so the same publication checks run here.
     * One listing that isn't ready stops the whole selection (nothing is written).
     *
     * @param iterable<object> $records
     */
    private function assertPublishable(iterable $records): void
    {
        $blocked = [];
        foreach ($records as $record) {
            $state = [];
            foreach (get_object_vars($record) as $key => $value) {
                if (is_string($value) && $value !== '' && $value[0] === '{') {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : $value;
                }
                $state[$key] = $value === null ? '' : (is_scalar($value) ? (string) $value : $value);
            }
            $state['publish_status'] = 'published';
            $errors = $this->validate($state, $record);
            unset($errors['video_url'], $errors['longitude']);
            if ($errors !== []) {
                $blocked[] = (string) (($record->reference ?? '') !== '' ? $record->reference : '#' . $record->id);
            }
        }
        if ($blocked !== []) {
            throw new \FloCMS\Core\ValidationException(['_form' => str_replace(':refs', implode(', ', $blocked), \FloCMS\Admin\Support\AdminLang::label('fh.bulk.not_ready'))]);
        }
    }

    public function normalizeState(array $state, ?object $record): array
    {
        if (isset($state['reference']) && is_string($state['reference'])) {
            $state['reference'] = strtoupper(trim($state['reference']));
        }
        return $state;
    }

    /** Publication checks (D3) and video link check. */
    public function validate(array $state, ?object $record): array
    {
        $errors = [];
        $videoUrl = trim((string) ($state['video_url'] ?? ''));
        if ($videoUrl !== '' && !Catalog::isVideoLink($videoUrl)) {
            $errors['video_url'] = \FloCMS\Admin\Support\AdminLang::label('fh.e.video_url');
        }
        if (($state['latitude'] ?? '') === '' xor ($state['longitude'] ?? '') === '') {
            $errors['longitude'] = \FloCMS\Admin\Support\AdminLang::label('fh.e.coordinates');
        }
        if (($state['publish_status'] ?? 'draft') !== 'published') {
            return $errors;
        }
        $required = [
            'description' => self::englishText($state['description'] ?? null) === '',
            'property_type_id' => (string) ($state['property_type_id'] ?? '') === '',
            'city_id' => (string) ($state['city_id'] ?? '') === '',
            'cover_id' => (string) ($state['cover_id'] ?? '') === '',
        ];
        foreach ($required as $field => $missing) {
            if ($missing && !isset($errors[$field])) {
                $errors[$field] = \FloCMS\Admin\Support\AdminLang::label('fh.e.required_to_publish');
            }
        }
        if (($state['price_on_request'] ?? '0') !== '1' && (string) ($state['price'] ?? '') === '') {
            $errors['price'] = \FloCMS\Admin\Support\AdminLang::label('fh.e.price_to_publish');
        }
        return $errors;
    }

    /** "€ 1,200,000", "€ 1,500 / month", or "On request" (admin list). */
    public static function priceText(mixed $value, ?object $record): string
    {
        if ($record !== null && (int) ($record->price_on_request ?? 0) === 1) {
            return \FloCMS\Admin\Support\AdminLang::label('fh.price_on_request');
        }
        if ($value === null || $value === '') {
            return '—';
        }
        $text = '€ ' . number_format((float) $value, 0, '.', ',');
        if ($record !== null && ($record->status ?? '') === 'for_rent') {
            $text .= ' ' . \FloCMS\Admin\Support\AdminLang::label('fh.per_month');
        }
        return $text;
    }

    /** The default-language text of a translatable value, tags removed. */
    public static function englishText(mixed $value): string
    {
        $default = \FloCMS\Admin\Translation\Languages::default();
        $text = is_array($value) ? (string) ($value[$default] ?? '') : (string) $value;
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
