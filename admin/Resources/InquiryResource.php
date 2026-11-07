<?php
declare(strict_types=1);

namespace App\Admin\Resources;

use FloCMS\Admin\Form\Fields\BelongsTo;
use FloCMS\Admin\Form\Fields\Placeholder;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Form;
use FloCMS\Admin\Form\Layout\Section;
use FloCMS\Admin\Resource;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\BooleanColumn;
use FloCMS\Admin\Table\Columns\DateColumn;
use FloCMS\Admin\Table\Columns\RelationColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Table\Filters\DateRangeFilter;
use FloCMS\Admin\Table\Filters\SelectFilter;
use FloCMS\Admin\Table\Filters\TabsFilter;
use FloCMS\Admin\Table\Table;
use FloCMS\Core\Database;
use FloCMS\Support\Catalog;

/**
 * Inquiries sent from the website (D10). Visitors create them; staff only set
 * status, owner and an internal note. What the visitor sent is shown, never
 * written.
 */
final class InquiryResource extends Resource
{
    protected static string $table = 'inquiries';
    protected static string $slug = 'inquiries';
    protected static bool $timestamps = true;
    protected static string $label = 'fh.res.inquiry';
    protected static string $pluralLabel = 'fh.res.inquiries';
    protected static ?string $icon = 'mail';
    protected static int $navigationSort = 5;
    protected static bool $importable = false;

    public function canCreate(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('visitor')->label('fh.s.visitor')->columns(2)->schema([
                self::show('reference', 'fh.f.inquiry_reference'),
                Placeholder::make('_type')->label('fh.f.inquiry_type')
                    ->content(static fn (?object $r): string => AdminLang::label(Catalog::inquiryTypes()[$r->type ?? ''] ?? (string) ($r->type ?? ''))),
                self::show('name', 'fh.f.person_name'),
                self::show('email', 'fh.f.email'),
                self::show('phone', 'fh.f.phone'),
                Placeholder::make('_preferred')->label('fh.f.preferred_contact')
                    ->content(static fn (?object $r): string => AdminLang::label(Catalog::contactMethods()[$r->preferred_contact ?? ''] ?? '—')),
                Placeholder::make('_message')->label('fh.f.message')->columnSpan('full')
                    ->content(static fn (?object $r): string => (string) ($r->message ?? '')),
                Placeholder::make('_about')->label('fh.f.about')->columnSpan('full')
                    ->content(static fn (?object $r): string => $r === null ? '' : self::aboutText($r)),
                Placeholder::make('_received')->label('fh.f.received')
                    ->content(static fn (?object $r): string => (string) ($r->created_at ?? '')),
                Placeholder::make('_email_sent')->label('fh.f.email_sent')
                    ->content(static fn (?object $r): string => AdminLang::label((int) ($r->email_sent ?? 0) === 1 ? 'fh.yes' : 'fh.email_not_sent')),
                self::show('lang', 'fh.f.lang'),
                self::show('page_url', 'fh.f.page_url'),
            ]),
        ])->aside([
            Section::make('handling')->label('fh.s.handling')->columns(1)->schema([
                Select::make('status')->label('fh.f.status')->options(Catalog::inquiryStatuses())->segmented()->required(),
                BelongsTo::make('owner_id')->label('fh.f.owner')->table('users', 'full_name'),
                Textarea::make('note')->label('fh.f.note')->rows(6)->rules('max:5000')->help('fh.h.note'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('fh.f.person_name')->searchable()->sortable(),
                TextColumn::make('reference')->label('fh.f.inquiry_reference')->searchable(),
                TextColumn::make('email')->label('fh.f.email')->searchable()->toggleable(),
                TextColumn::make('phone')->label('fh.f.phone')->searchable()->toggleable(hiddenByDefault: true),
                BadgeColumn::make('type')->label('fh.f.inquiry_type')->labels(Catalog::inquiryTypes()),
                RelationColumn::make('listing_id')->label('fh.res.listing')->resource(ListingResource::class)->toggleable(),
                BadgeColumn::make('status')->label('fh.f.status')->labels(Catalog::inquiryStatuses())->colors(Catalog::inquiryStatusColors()),
                RelationColumn::make('owner_id')->label('fh.f.owner')->table('users', 'full_name')->toggleable(),
                BooleanColumn::make('email_sent')->label('fh.f.email_sent')->toggleable(hiddenByDefault: true),
                DateColumn::make('created_at')->label('fh.f.received')->dateFormat('Y-m-d H:i')->sortable(),
            ])
            ->tabs(TabsFilter::make('status')->label('fh.f.status')->options(Catalog::inquiryStatuses()))
            ->filters([
                SelectFilter::make('type')->label('fh.f.inquiry_type')->options(Catalog::inquiryTypes()),
                DateRangeFilter::make('created_at')->label('fh.f.received'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** A read-only line for a column the visitor sent. */
    private static function show(string $column, string $label): Placeholder
    {
        return Placeholder::make('_' . $column)->label($label)
            ->content(static fn (?object $r): string => (string) ($r->{$column} ?? '') !== '' ? (string) $r->{$column} : '—');
    }

    /** What the inquiry is about: the listing, development, service or saved list (references). */
    private static function aboutText(object $r): string
    {
        $db = \FloCMS\Core\App::db();
        $parts = [];
        $title = static fn (?object $row): string => $row === null ? '' : \FloCMS\Admin\Translation\Translations::get($row->title ?? '');
        if (!empty($r->listing_id) && $db instanceof Database) {
            $row = $db->table('listings')->select(['reference', 'title'])->where('id', '=', (int) $r->listing_id)->first();
            $parts[] = $row !== null ? $row->reference . ' · ' . $title($row) : '#' . $r->listing_id;
        }
        if (!empty($r->project_id) && $db instanceof Database) {
            $parts[] = $title($db->table('projects')->select(['title'])->where('id', '=', (int) $r->project_id)->first());
        }
        if (!empty($r->service)) {
            $parts[] = (string) $r->service;
        }
        $saved = json_decode((string) ($r->saved_ids ?? ''), true);
        if (is_array($saved) && $saved !== [] && $db instanceof Database) {
            $ids = array_slice(array_map('intval', $saved), 0, 50);
            $refs = array_map(static fn (object $row): string => (string) $row->reference,
                $db->table('listings')->select(['reference'])->whereIn('id', $ids)->get());
            $parts[] = AdminLang::label('fh.saved_list') . ': ' . implode(', ', $refs);
        }
        return $parts === [] ? '—' : implode("\n", $parts);
    }
}
