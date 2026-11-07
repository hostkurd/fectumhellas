<?php
declare(strict_types=1);

namespace App\Components;

use FloCMS\Core\Config;
use FloCMS\Core\Http\Request;
use FloCMS\Core\Security\ClientIpResolver;
use FloCMS\Reactive\Attributes\Action;
use FloCMS\Reactive\Attributes\State;
use FloCMS\Reactive\Component;
use FloCMS\Support\Inquiries;
use FloCMS\Support\Site;

/**
 * "Ask a question" / "Request a viewing" on a property page (approved Property
 * mockup), flocms-reactive: sent without a page reload. Saved in Inquiries
 * (type listing) first, then emailed to the team.
 */
final class PropertyInquiry extends Component
{
    #[State(rules: 'in:question,viewing')] public string $mode = 'question';
    #[State(rules: 'max:190')] public string $name = '';
    #[State(rules: 'max:190')] public string $email = '';
    #[State(rules: 'max:40')] public string $phone = '';
    #[State(rules: 'max:10')] public string $date = '';
    #[State(maxBytes: 20000)] public string $message = '';
    #[State] public bool $consent = false;
    #[State(rules: 'max:200')] public string $website = '';

    #[State(writable: false)] public string $sent = '';
    #[State(writable: false)] public string $notice = '';
    #[State(writable: false)] public int $listingId = 0;
    #[State(writable: false)] public string $ref = '';
    #[State(writable: false)] public string $page = '';

    private ?Request $request = null;

    public function authorize(Request $request): void { $this->request = $request; }

    public function validatesOnUpdate(): bool { return false; }

    public function validate(): array
    {
        [, $errors] = Inquiries::validate($this->input());
        if ($this->mode === 'viewing' && $this->date !== '') {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->date);
            if ($day === false || $day->format('Y-m-d') !== $this->date || $day < new \DateTimeImmutable('today')) {
                $errors['date'] = 'fh.e.date';
            }
        }
        return array_map(static fn (string $key): string => __($key), $errors);
    }

    #[Action(validate: false)]
    public function pick(): void { $this->notice = ''; }

    #[Action]
    public function send(): void
    {
        $this->notice = '';
        if (trim($this->website) !== '') {
            $this->sent = 'FH-' . date('Y') . '-0000';
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
            $this->sent = Inquiries::saveListing($this->listingId, $this->mode, $this->date, $clean, Site::lang(), $this->page, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $error) {
            error_log('FECTUM property inquiry not saved: ' . $error->getMessage());
            $this->notice = 'fh.e.not_saved';
        }
    }

    #[Action(validate: false)]
    public function again(): void
    {
        $this->sent = $this->notice = '';
        $this->message = __('fh.inquiry_default', ['ref' => $this->ref]);
    }

    public function render(): string
    {
        return $this->view(ROOT . '/views/components/property-inquiry.html', [
            'c' => $this, 'notice' => $this->notice !== '' ? __($this->notice) : '',
            'today' => date('Y-m-d'), 'privacy' => Site::pageUrl('privacy-policy') !== Site::url('privacy-policy') ? Site::pageUrl('privacy-policy') : '',
        ]);
    }

    private function input(): array
    {
        return ['topic' => 'other', 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'preferred_contact' => 'email', 'message' => $this->message, 'consent' => $this->consent ? '1' : ''];
    }
}
