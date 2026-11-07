<?php
/*
 * Website settings pages at /admin/settings (design note D11). Read on the
 * website with setting('contact.phone'), settings('company'), ...
 * Pages need settings.manage: Administrators and Super Admins, not Editors.
 */
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Placeholder;
use FloCMS\Admin\Support\AdminLang;
use FloCMS\Support\OfflineMode;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Admin\Form\Layout\Section;
use FloCMS\Admin\Settings\SettingsPage;

return [
    // Website offline switch (flocms-core's offline mode, see support/OfflineMode.php)
    SettingsPage::make('website')
        ->label('fh.set.website')
        ->icon('shield')
        ->description('fh.set.website_desc')
        ->columns(1)
        ->schema([
            Placeholder::make('status_now')->label('fh.set.status_now')
                ->content(static fn (): string => AdminLang::label(OfflineMode::isOn() ? 'fh.set.status_offline' : 'fh.set.status_online')),
            Toggle::make('offline')->label('fh.set.offline')->default(false)->help('fh.set.offline_help'),
            TextInput::make('title')->label('fh.set.offline_title')->translatable()->rules('max:120')
                ->default([
                    'en' => "We'll be back soon",
                    'ar' => 'سنعود قريباً',
                    'el' => 'Επιστρέφουμε σύντομα',
                ]),
            Textarea::make('message')->label('fh.set.offline_message')->translatable()->rows(3)->rules('max:500')
                ->default([
                    'en' => 'Our new website is on its way. In the meantime, our team is happy to help by phone, WhatsApp or email.',
                    'ar' => 'موقعنا الجديد قيد الإعداد. وحتى ذلك الحين، يسعد فريقنا بمساعدتكم عبر الهاتف أو واتساب أو البريد الإلكتروني.',
                    'el' => 'Ο νέος μας ιστότοπος έρχεται σύντομα. Στο μεταξύ, η ομάδα μας είναι στη διάθεσή σας τηλεφωνικά, μέσω WhatsApp ή email.',
                ]),
            Toggle::make('show_contact')->label('fh.set.offline_contact')->default(true)->help('fh.set.offline_contact_help'),
        ])
        ->afterSave(static function (array $values): void {
            OfflineMode::set(in_array($values['offline'] ?? false, [true, 1, '1'], true));
        }),

    SettingsPage::make('contact')
        ->label('fh.set.contact')
        ->icon('phone')
        ->description('fh.set.contact_desc')
        ->schema([
            Section::make('channels')->label('fh.set.channels')->schema([
                TextInput::make('phone')->label('fh.f.phone')->tel()->rules('max:40')->default('+30 697 320 7477'),
                TextInput::make('whatsapp')->label('fh.set.whatsapp')->tel()->rules('max:40')->default('+30 697 320 7477')
                    ->help('fh.set.whatsapp_help'),
                TextInput::make('email')->label('fh.f.email')->email()->rules('max:190')->default('info@fectumhellas.gr'),
                TextInput::make('location_line')->label('fh.set.location_line')->translatable()->rules('max:120')
                    ->default('Athens, Greece'),
            ]),
            Section::make('office')->label('fh.set.office')->schema([
                Textarea::make('address')->label('fh.f.address')->translatable()->rows(3)->rules('max:300')
                    ->default('42 Adrianeiou, 11525 Athens, Greece')->columnSpan('full'),
                TextInput::make('latitude')->label('fh.f.latitude')->numeric()->rules('numeric|min:-90|max:90'),
                TextInput::make('longitude')->label('fh.f.longitude')->numeric()->rules('numeric|min:-180|max:180'),
                TextInput::make('hours')->label('fh.set.hours')->translatable()->rules('max:120')->columnSpan('full'),
            ]),
        ]),

    SettingsPage::make('company')
        ->label('fh.set.company')
        ->icon('briefcase')
        ->description('fh.set.company_desc')
        ->schema([
            TextInput::make('legal_name')->label('fh.set.legal_name')->rules('max:190')->columnSpan('full'),
            TextInput::make('gemi')->label('fh.set.gemi')->rules('max:40'),
            TextInput::make('licence')->label('fh.set.licence')->rules('max:60'),
            TextInput::make('vat')->label('fh.set.vat')->rules('max:40'),
            Textarea::make('registered_office')->label('fh.set.registered_office')->translatable()->rows(2)->rules('max:300')->columnSpan('full'),
            TextInput::make('tagline')->label('fh.set.tagline')->translatable()->rules('max:120')
                ->default('Building value. Across Greece.')->columnSpan('full'),
        ]),

    SettingsPage::make('social')
        ->label('fh.set.social')
        ->icon('link')
        ->schema([
            TextInput::make('facebook')->label('Facebook')->url()->rules('max:255'),
            TextInput::make('instagram')->label('Instagram')->url()->rules('max:255'),
            TextInput::make('linkedin')->label('LinkedIn')->url()->rules('max:255'),
            TextInput::make('youtube')->label('YouTube')->url()->rules('max:255'),
        ]),

    SettingsPage::make('notifications')
        ->label('fh.set.notifications')
        ->icon('bell')
        ->description('fh.set.notifications_desc')
        ->columns(1)
        ->schema([
            TextInput::make('inquiry_email')->label('fh.set.inquiry_email')->email()->rules('required|max:190')
                ->default('info@fectumhellas.gr')->help('fh.set.inquiry_email_help'),
        ]),

    SettingsPage::make('seo')
        ->label('fh.set.seo')
        ->icon('globe')
        ->description('fh.set.seo_desc')
        ->columns(1)
        ->schema([
            Textarea::make('description')->label('fh.f.meta_description')->translatable()->rows(3)->rules('max:170')
                ->default('Real estate development and investment in Greece.'),
            MediaPicker::make('share_image')->label('fh.set.share_image')->folder('Social')->aspectRatio('16:9'),
        ]),

    SettingsPage::make('costs')
        ->label('fh.set.costs')
        ->icon('chart')
        ->description('fh.set.costs_desc')
        ->columns(1)
        ->schema([
            Toggle::make('enabled')->label('fh.set.costs_enabled')->default(false),
            Repeater::make('lines')->label('fh.set.cost_lines')->schema([
                TextInput::make('name')->label('fh.f.name')->translatable()->required()->rules('max:80'),
                TextInput::make('rate')->label('fh.set.rate')->numeric()->required()->rules('numeric|min:0|max:100'),
                Toggle::make('active')->label('fh.set.line_active')->default(true),
            ])->itemLabel('name')->addLabel('fh.set.add_cost_line')->columns(3)->maxItems(12)->table(),
            Textarea::make('note')->label('fh.set.costs_note')->translatable()->rows(3)->rules('max:500'),
        ]),
];
