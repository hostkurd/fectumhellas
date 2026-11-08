<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Resources\InquiryResource;
use FloCMS\Admin\Support\Dates;
use FloCMS\Admin\Table\Columns\BadgeColumn;
use FloCMS\Admin\Table\Columns\DateColumn;
use FloCMS\Admin\Table\Columns\RelationColumn;
use FloCMS\Admin\Table\Columns\TextColumn;
use FloCMS\Admin\Widgets\RecordsWidget;
use FloCMS\Support\Catalog;

/** The five newest inquiries: name, reference, sent from, status, owner, received. */
final class LatestInquiries extends RecordsWidget
{
    protected string $title = 'fh.dash.latest_inquiries';
    protected string $resource = InquiryResource::class;
    protected string $sortColumn = 'created_at';
    protected int|string $columnSpan = 2;
    protected int $sort = 40;

    protected function columns(): array
    {
        return [
            TextColumn::make('name')->label('fh.f.person_name'),
            TextColumn::make('reference')->label('fh.f.inquiry_reference'),
            BadgeColumn::make('type')->label('fh.dash.sent_from')->labels(Catalog::inquiryTypes()),
            BadgeColumn::make('status')->label('fh.f.status')->labels(Catalog::inquiryStatuses())->colors(Catalog::inquiryStatusColors()),
            RelationColumn::make('owner_id')->label('fh.f.owner')->table('users', 'full_name'),
            DateColumn::make('created_at')->label('fh.f.received')
                ->format(static fn (mixed $value): string => ($time = strtotime((string) $value)) !== false ? Dates::relative($time) : ''),
        ];
    }
}
