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
use FloCMS\Support\Shortlist;
use FloCMS\Support\Site;

/**
 * "Send list to our team" on the Saved page (approved Saved mockup),
 * flocms-reactive: sent without a page reload. Saved in Inquiries (type
 * saved, with the property ids) first, then emailed to the team.
 */
final class SavedInquiry extends Component
{
    #[State(rules: 'max:190')] public string $name = '';
    #[State(rules: 'max:190')] public string $email = '';
    #[State(rules: 'max:40')] public string $phone = '';
    #[State] public bool $consent = false;
    #[State(rules: 'max:200')] public string $website = '';

    #[State(writable: false)] public string $sent = '';
    #[State(writable: false)] public string $notice = '';
    #[State(writable: false)] public string $ids = '';
    #[State(writable: false)] public int $count = 0;
    #[State(writable: false)] public string $page = '';

    private ?Request $request = null;

    public function authorize(Request $request): void { $this->request = $request; }

    public function validatesOnUpdate(): bool { return false; }

    public function validate(): array
    {
        [, $errors] = Inquiries::validate($this->input());
        return array_map(static fn (string $key): string => __($key), $errors);
    }

    #[Action]
    public function send(): void
    {
        $this->notice = '';
        if (trim($this->website) !== '') {
            $this->sent = 'FH-' . date('Y') . '-0000';
            return;
        }
        // Only properties still published (the list was read when the page opened)
        $ids = array_map(static fn (object $r): int => (int) $r->id, Shortlist::listings(Shortlist::ids($this->ids, Shortlist::MAX_SAVED)));
        if ($ids === []) {
            $this->notice = 'fh.sv.gone';
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
            $this->sent = Inquiries::saveSaved($ids, $clean, Site::lang(), $this->page, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $error) {
            error_log('FECTUM saved list not saved: ' . $error->getMessage());
            $this->notice = 'fh.e.not_saved';
        }
    }

    public function render(): string
    {
        return $this->view(ROOT . '/views/components/saved-inquiry.html', [
            'c' => $this, 'notice' => $this->notice !== '' ? __($this->notice) : '',
            'countLabel' => $this->count === 1 ? __('fh.sv.one') : __('fh.sv.many', ['n' => (string) $this->count]),
            'privacy' => Site::pageUrl('privacy-policy') !== Site::url('privacy-policy') ? Site::pageUrl('privacy-policy') : '',
        ]);
    }

    /** The message is written for the visitor: the form only asks who they are. */
    private function input(): array
    {
        return ['topic' => 'buying', 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'preferred_contact' => 'email', 'message' => __('fh.sv.message', ['n' => (string) $this->count]), 'consent' => $this->consent ? '1' : ''];
    }
}
