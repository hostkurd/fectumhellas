<?php
declare(strict_types=1);

namespace App\Components;

use FloCMS\Core\Config;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Security\ClientIpResolver;
use FloCMS\Core\Session;
use FloCMS\Reactive\Attributes\Action;
use FloCMS\Reactive\Attributes\State;
use FloCMS\Reactive\Component;
use FloCMS\Support\Inquiries;
use FloCMS\Support\Site;

/**
 * The Contact page form (flocms-reactive, no page reload). Mounted by the
 * contact_panel block. The same form also posts to FormsController when
 * JavaScript is off; its result comes back here through the session.
 */
final class ContactForm extends Component
{
    #[State(rules: 'in:buying,renting,development,investment,other')] public string $topic = 'buying';
    #[State(rules: 'max:190')] public string $name = '';
    #[State(rules: 'max:190')] public string $email = '';
    #[State(rules: 'max:40')] public string $phone = '';
    #[State(rules: 'in:email,phone,whatsapp')] public string $preferred = 'email';
    #[State(maxBytes: 20000)] public string $message = '';
    #[State] public bool $consent = false;
    /** Honeypot: hidden from people, filled in by bots. */
    #[State(rules: 'max:200')] public string $website = '';

    /** Set by the server only. */
    #[State(writable: false)] public string $sent = '';
    #[State(writable: false)] public string $notice = '';
    /** From the block's fields (Admin › Pages). */
    #[State(writable: false)] public string $heading = '';
    #[State(writable: false)] public string $replyNote = '';
    #[State(writable: false)] public string $privacy = '';
    #[State(writable: false)] public string $page = '';

    private ?Request $request = null;

    /** A public form: everyone may use it. Keeps the request for the visitor's address. */
    public function authorize(Request $request): void
    {
        $this->request = $request;
    }

    /** After a no-JavaScript post: show its result (reference or errors) once. */
    public function mount(): void
    {
        $state = Session::get(Inquiries::SESSION);
        if (!is_array($state)) {
            return;
        }
        Session::delete(Inquiries::SESSION);
        $this->sent = (string) ($state['sent'] ?? '');
        $old = is_array($state['old'] ?? null) ? $state['old'] : [];
        foreach (['topic' => 'topic', 'name' => 'name', 'email' => 'email', 'phone' => 'phone', 'preferred_contact' => 'preferred', 'message' => 'message'] as $from => $to) {
            if (isset($old[$from]) && is_string($old[$from])) {
                $this->{$to} = $old[$from];
            }
        }
        $this->consent = ($old['consent'] ?? '') === '1';
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        if (isset($errors['form'])) {
            $this->notice = (string) $errors['form'];
            unset($errors['form']);
        }
        $this->setErrors(array_map(static fn ($key): string => __((string) $key), $errors));
    }

    /** Required checks run on Send only: typing an unfinished form shows no errors. */
    public function validatesOnUpdate(): bool { return false; }

    public function validate(): array
    {
        [, $errors] = Inquiries::validate($this->input());
        return array_map(static fn (string $key): string => __($key), $errors);
    }

    /** Choosing a topic: the server renders the matching message prompt. */
    #[Action(validate: false)]
    public function pick(): void
    {
        $this->notice = '';
    }

    #[Action]
    public function send(): void
    {
        $this->notice = '';
        if (trim($this->website) !== '') {
            $this->sent = 'FH-' . date('Y') . '-0000'; // a bot: pretend it worked, save nothing
            return;
        }
        $ip = $this->request !== null
            ? (new ClientIpResolver(array_values(array_filter(array_map('trim', (array) Config::get('trusted_proxies', []))))))->resolve($this->request)
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!Inquiries::allowed($ip)) {
            $this->notice = 'fh.e.too_many';
            return;
        }
        [$clean] = Inquiries::validate($this->input());
        try {
            $this->sent = Inquiries::saveContact($clean, Site::lang(), $this->page, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $error) {
            error_log('FECTUM inquiry not saved: ' . $error->getMessage());
            $this->notice = 'fh.e.not_saved';
            return;
        }
        $this->name = $this->email = $this->phone = $this->message = '';
        $this->consent = false;
    }

    /** "Send another message". */
    #[Action(validate: false)]
    public function again(): void
    {
        $this->sent = '';
        $this->notice = '';
    }

    public function render(): string
    {
        return $this->view(ROOT . '/views/components/contact-form.html', [
            'topic' => $this->topic, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'preferred' => $this->preferred, 'message' => $this->message, 'consent' => $this->consent,
            'sent' => $this->sent, 'notice' => $this->notice !== '' ? __($this->notice) : '',
            'heading' => $this->heading !== '' ? $this->heading : __('fh.how_help'),
            'replyNote' => $this->replyNote, 'privacy' => Site::link($this->privacy),
            'topics' => Inquiries::TOPICS, 'methods' => Inquiries::METHODS,
            'action' => Site::url('forms/contact'), 'page' => $this->page,
        ]);
    }

    /** The form as Inquiries::validate() expects it. */
    private function input(): array
    {
        return ['topic' => $this->topic, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'preferred_contact' => $this->preferred, 'message' => $this->message, 'consent' => $this->consent ? '1' : ''];
    }
}
