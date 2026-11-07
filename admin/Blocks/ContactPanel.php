<?php
declare(strict_types=1);

namespace App\Admin\Blocks;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Reactive\Reactive;
use FloCMS\Support\Site;

/**
 * Contact page (approved mockup): the office details and map beside the
 * contact form. Details come from Settings › Contact details; the form saves
 * to Inquiries and emails the team: App\Components\ContactForm (flocms-reactive),
 * with controllers/FormsController.php when JavaScript is off.
 */
final class ContactPanel extends Block
{
    protected static string $type = 'contact_panel';
    protected static string $category = 'site';
    protected static string $icon = 'mail';
    protected static ?string $label = 'fh.block.contact_panel';
    protected static ?string $description = 'fh.block.contact_panel_desc';

    public function defaultWidth(): string { return 'full'; }

    /** The form is a live component with the visitor's own state: never cached. */
    public function cacheable(): bool { return false; }

    public function fields(): array
    {
        return [
            TextInput::make('heading')->label('fh.bf.form_heading')->rules('max:120')->help('fh.bf.default_help'),
            TextInput::make('reply_note')->label('fh.bf.reply_note')->rules('max:160'),
            TextInput::make('privacy_link')->label('fh.bf.privacy_link')->rules('max:255')->help('fh.bf.link_help'),
            Toggle::make('map')->label('fh.bf.show_map')->default('1'),
        ];
    }

    public function data(array $fields): array
    {
        $whatsapp = Site::setting('contact.whatsapp');
        $phone = Site::setting('contact.phone');
        $email = Site::setting('contact.email');
        return [
            'details' => array_values(array_filter([
                ['icon' => 'pin', 'label' => __('fh.visit'), 'text' => Site::setting('contact.address'), 'href' => ''],
                ['icon' => 'phone', 'label' => __('fh.call'), 'text' => $phone, 'href' => $phone !== '' ? 'tel:' . Site::tel($phone) : ''],
                ['icon' => 'chat', 'label' => 'WhatsApp', 'text' => $whatsapp, 'href' => $whatsapp !== '' ? Site::whatsapp($whatsapp) : ''],
                ['icon' => 'mail', 'label' => __('fh.f_email'), 'text' => $email, 'href' => $email !== '' ? 'mailto:' . $email : ''],
                ['icon' => 'clock', 'label' => __('fh.office_hours'), 'text' => Site::setting('contact.hours'), 'href' => ''],
            ], static fn (array $row): bool => $row['text'] !== '')),
            // The form: a flocms-reactive component (App\Components\ContactForm), sent without a page reload.
            'form' => Site::reactiveAssets() . Reactive::mount('contact', 'contact', [
                'heading' => (string) ($fields['heading'] ?? ''),
                'replyNote' => (string) ($fields['reply_note'] ?? ''),
                'privacy' => (string) ($fields['privacy_link'] ?? ''),
                'page' => (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
            ]),
        ];
    }
}
