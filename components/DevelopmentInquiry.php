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
use FloCMS\Support\Projects;
use FloCMS\Support\Site;

/**
 * "Register interest" on a development page (approved Development mockup),
 * flocms-reactive: sent without a page reload. Saved in Inquiries (type
 * project, with the residence asked about) first, then emailed to the team.
 */
final class DevelopmentInquiry extends Component
{
    #[State(rules: 'max:190')] public string $name = '';
    #[State(rules: 'max:190')] public string $email = '';
    #[State(rules: 'max:40')] public string $phone = '';
    #[State(rules: 'max:30')] public string $unit = '';
    #[State] public bool $consent = false;
    #[State(rules: 'max:200')] public string $website = '';

    #[State(writable: false)] public string $sent = '';
    #[State(writable: false)] public string $notice = '';
    #[State(writable: false)] public int $projectId = 0;
    #[State(writable: false)] public string $project = '';
    #[State(writable: false)] public string $page = '';

    private ?Request $request = null;

    public function authorize(Request $request): void { $this->request = $request; }

    public function validatesOnUpdate(): bool { return false; }

    public function validate(): array
    {
        [, $errors] = Inquiries::validate($this->input());
        if ($this->unit !== '' && !in_array($this->unit, array_column($this->options(), 'ref'), true)) {
            $errors['unit'] = 'fh.e.unit';
        }
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
        $ip = $this->request !== null
            ? (new ClientIpResolver(array_values(array_filter(array_map('trim', (array) Config::get('trusted_proxies', []))))))->resolve($this->request)
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!Inquiries::allowed($ip)) {
            $this->notice = 'fh.e.too_many';
            return;
        }
        [$clean] = Inquiries::validate($this->input());
        try {
            $this->sent = Inquiries::saveProject($this->projectId, $this->unit, $clean, Site::lang(), $this->page, $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        } catch (\Throwable $error) {
            error_log('FECTUM development inquiry not saved: ' . $error->getMessage());
            $this->notice = 'fh.e.not_saved';
        }
    }

    #[Action(validate: false)]
    public function again(): void
    {
        $this->sent = $this->notice = '';
        $this->unit = '';
    }

    public function render(): string
    {
        return $this->view(ROOT . '/views/components/development-inquiry.html', [
            'c' => $this, 'notice' => $this->notice !== '' ? __($this->notice) : '', 'options' => $this->options(),
            'privacy' => Site::pageUrl('privacy-policy') !== Site::url('privacy-policy') ? Site::pageUrl('privacy-policy') : '',
        ]);
    }

    /** The residences still for sale, for the select. @return list<array{ref: string, label: string}> */
    private function options(): array
    {
        $out = [];
        foreach (Projects::units($this->projectId) as $u) {
            if ($u['open']) {
                $out[] = ['ref' => $u['ref'], 'label' => $u['ref'] . ($u['type'] !== '' ? ' · ' . $u['type'] : '')];
            }
        }
        return $out;
    }

    /** The message is written for the visitor: the form only asks who they are and which residence. */
    private function input(): array
    {
        $message = __('fh.interest_message', ['project' => $this->project]) . ($this->unit !== '' ? ' (' . $this->unit . ')' : '');
        return ['topic' => 'development', 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'preferred_contact' => 'email', 'message' => $message, 'consent' => $this->consent ? '1' : ''];
    }
}
