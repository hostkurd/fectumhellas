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
use FloCMS\Admin\Actions\Action;
use FloCMS\Admin\Actions\ActionContext;
use FloCMS\Admin\Actions\BulkAction;
use FloCMS\Admin\Actions\BulkActionContext;
use FloCMS\Admin\Actions\DeleteAction;
use FloCMS\Admin\Actions\DeleteBulkAction;
use FloCMS\Admin\Actions\EditAction;
use FloCMS\Core\Auth;
use FloCMS\Core\Database;
use FloCMS\Core\Session;
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

    /** Inquiries hold visitors' personal data: only roles with inquiries.delete (Administrators) move them to Trash. */
    public function canDelete(object $record): bool
    {
        return Auth::can('inquiries.delete');
    }

    /** How many inquiries are still New, beside Inquiries in the sidebar (flocms-admin 0.41). */
    public function navigationBadge(): int
    {
        $db = \FloCMS\Core\App::db();
        return $db === null ? 0 : $db->table('inquiries')->where('status', '=', 'new')->count();
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
                    ->html(static fn (?object $r): string => self::block('fh.f.message', (string) ($r->message ?? ''))),
                Placeholder::make('_about')->label('fh.f.about')->columnSpan('full')
                    ->html(static fn (?object $r): string => $r === null ? '' : self::block('fh.f.about', self::aboutText($r))),
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
            ->actions([
                EditAction::make(),
                Action::make('mark_contacted')->label('fh.act.mark_contacted')->icon('check')->color('success')->requiresConfirmation(false)
                    ->visible(static fn (?object $record): bool => ($record->status ?? '') === 'new')
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->action(static fn (ActionContext $context) => $context->update(['status' => 'contacted'])),
                Action::make('assign_to_me')->label('fh.act.assign_to_me')->icon('user')->requiresConfirmation(false)
                    ->visible(static fn (?object $record): bool => self::me() !== null && (int) ($record->owner_id ?? 0) !== self::me())
                    ->authorize(fn (?object $record): bool => $record !== null && self::me() !== null && $this->canEdit($record))
                    ->action(static fn (ActionContext $context) => $context->update(['owner_id' => self::me()])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                $this->statusBulk('mark_contacted', 'contacted', 'fh.act.mark_contacted', 'success'),
                $this->statusBulk('mark_closed', 'closed', 'fh.act.mark_closed', 'default'),
                BulkAction::make('assign_owner')->label('fh.act.assign_owner')->icon('user')
                    ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                    ->form(static fn (Form $form): Form => $form->schema([
                        BelongsTo::make('owner_id')->label('fh.f.owner')->table('users', 'full_name')->required(),
                    ]))
                    ->action(static fn (BulkActionContext $context, array $data) => $context->updateAll(['owner_id' => (int) $data['owner_id']])),
                DeleteBulkAction::make()->permission('inquiries.delete'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** A bulk action that sets one status. */
    private function statusBulk(string $name, string $status, string $label, string $color): BulkAction
    {
        return BulkAction::make($name)->label($label)->color($color)
            ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
            ->action(static fn (BulkActionContext $context) => $context->updateAll(['status' => $status]));
    }

    /** The signed-in staff member's user id. */
    private static function me(): ?int
    {
        $id = (int) Session::get('user_id');
        return $id > 0 ? $id : null;
    }

    /**
     * The CSV export: what the visitor sent plus the follow-up. Anyone with
     * content.inquiries.export (Editors too) can download this personal data.
     */
    public function export(\FloCMS\Admin\ImportExport\Export $export): \FloCMS\Admin\ImportExport\Export
    {
        $label = static fn (string $key): string => AdminLang::label($key);
        foreach (['reference' => 'fh.f.inquiry_reference', 'name' => 'fh.f.person_name', 'email' => 'fh.f.email',
                  'phone' => 'fh.f.phone', 'message' => 'fh.f.message', 'lang' => 'fh.f.lang', 'page_url' => 'fh.f.page_url',
                  'created_at' => 'fh.f.received'] as $column => $key) {
            $export->column($column, static fn (object $r): string => (string) ($r->{$column} ?? ''), $label($key));
        }
        $export->column('type', static fn (object $r): string => AdminLang::label(Catalog::inquiryTypes()[$r->type ?? ''] ?? (string) ($r->type ?? '')), $label('fh.f.inquiry_type'));
        $export->column('preferred_contact', static fn (object $r): string => AdminLang::label(Catalog::contactMethods()[$r->preferred_contact ?? ''] ?? ''), $label('fh.f.preferred_contact'));
        $export->column('about', static fn (object $r): string => self::aboutText($r), $label('fh.f.about'));
        return $export;
    }

    /**
     * A read-only block that keeps line breaks: a label and escaped text in
     * the panel's own markup (an html() placeholder has no label of its own).
     */
    private static function block(string $label, string $text): string
    {
        $e = static fn (string $v): string => \FloCMS\Admin\Support\Str::e($v);
        return '<span class="fa-label">' . $e(AdminLang::label($label)) . '</span>'
            . '<div class="fa-static">' . ($text === '' ? '—' : nl2br($e($text), false)) . '</div>';
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
