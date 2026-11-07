<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Admin\Actions\BulkAction;
use FloCMS\Admin\Actions\BulkActionContext;
use FloCMS\Admin\Support\AdminLang;

/**
 * Publish / Unpublish bulk actions for the admin lists (Properties,
 * Developments, Insights). Bulk Publish skips the edit form, so the
 * resource's own validate() runs on every selected record first; one record
 * that isn't ready stops the whole selection and nothing is written.
 */
trait PublishesInBulk
{
    /**
     * @param string $notReady language key with :refs (the records that aren't ready)
     * @param (\Closure(object): array<string, mixed>)|null $extra more columns to write per record on Publish
     * @return list<BulkAction>
     */
    protected function publishBulkActions(string $notReady, ?\Closure $extra = null): array
    {
        return [
            BulkAction::make('publish')->label('fh.bulk.publish')->color('success')
                ->permission(static::permission('update'))
                ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                ->action(function (BulkActionContext $context) use ($notReady, $extra): void {
                    $this->assertPublishable($context->records, $notReady);
                    if ($extra === null) {
                        $context->updateAll(['publish_status' => 'published']);
                        return;
                    }
                    $context->each(static fn (\FloCMS\Admin\Actions\ActionContext $one) => $one->update(['publish_status' => 'published'] + $extra($one->record)));
                }),
            BulkAction::make('unpublish')->label('fh.bulk.unpublish')
                ->permission(static::permission('update'))
                ->authorize(fn (?object $record): bool => $record !== null && $this->canEdit($record))
                ->action(static fn (BulkActionContext $context) => $context->updateAll(['publish_status' => 'draft'])),
        ];
    }

    /** @param iterable<object> $records */
    private function assertPublishable(iterable $records, string $notReady): void
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
            unset($errors['video_url'], $errors['longitude']); // not publication checks
            if ($errors !== []) {
                $blocked[] = self::bulkName($record);
            }
        }
        if ($blocked !== []) {
            throw new \FloCMS\Core\ValidationException(['_form' => str_replace(':refs', implode(', ', $blocked), AdminLang::label($notReady))]);
        }
    }

    /** How a record is named in the "not ready" message: its reference, else its English title, else #id. */
    private static function bulkName(object $record): string
    {
        if (($record->reference ?? '') !== '') {
            return (string) $record->reference;
        }
        $title = json_decode((string) ($record->title ?? ''), true);
        $default = \FloCMS\Admin\Translation\Languages::default();
        $text = is_array($title) ? (string) ($title[$default] ?? '') : (string) ($record->title ?? '');
        return $text !== '' ? '“' . mb_strimwidth($text, 0, 60, '…') . '”' : '#' . $record->id;
    }
}
