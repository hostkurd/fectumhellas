<?php
declare(strict_types=1);

namespace FloCMS\Support;

use FloCMS\Admin\Mail\Mailer;
use FloCMS\Admin\Mail\Message;
use FloCMS\Admin\Translation\Translations;
use FloCMS\Core\App;
use FloCMS\Core\RateLimit\FileRateLimiter;

/**
 * Website inquiries (Stage 3a: the Contact page form; property and development
 * forms reuse it later). Saved first, then emailed to Settings › Notifications,
 * so a failed email never loses an inquiry (design note Stage 2 D10).
 */
final class Inquiries
{
    /** Session key for the form's messages after the redirect. */
    public const SESSION = 'fh_inquiry';

    /** Contact page topics => [label key, message prompt key]. Stored in the "service" column. */
    public const TOPICS = [
        'buying' => ['fh.topic.buying', 'fh.prompt.buying'],
        'renting' => ['fh.topic.renting', 'fh.prompt.renting'],
        'development' => ['fh.topic.development', 'fh.prompt.development'],
        'investment' => ['fh.topic.investment', 'fh.prompt.investment'],
        'other' => ['fh.topic.other', 'fh.prompt.other'],
    ];

    public const METHODS = ['email' => 'fh.contact.email', 'phone' => 'fh.contact.phone', 'whatsapp' => 'fh.contact.whatsapp'];

    /** At most this many messages per visitor (IP) in RATE_WINDOW seconds. */
    private const RATE_MAX = 5;
    private const RATE_WINDOW = 900;

    /**
     * Checks the posted form. Returns [clean values, errors by field].
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $v = static fn (string $key, int $max): string => mb_substr(trim(str_replace("\0", '', (string) ($input[$key] ?? ''))), 0, $max);
        $clean = [
            'topic' => $v('topic', 30),
            'name' => preg_replace('/\s+/u', ' ', $v('name', 190)) ?? '',
            'email' => $v('email', 190),
            'phone' => $v('phone', 40),
            'preferred_contact' => $v('preferred_contact', 20),
            'message' => $v('message', 5000),
            'consent' => ($input['consent'] ?? '') !== '' ? '1' : '',
        ];
        $errors = [];
        if (!array_key_exists($clean['topic'], self::TOPICS)) {
            $clean['topic'] = 'other';
        }
        if (mb_strlen($clean['name']) < 2) {
            $errors['name'] = 'fh.e.name';
        }
        if (filter_var($clean['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'fh.e.email';
        }
        if ($clean['phone'] !== '' && preg_match('/^[0-9+()\s.\-]{6,40}$/', $clean['phone']) !== 1) {
            $errors['phone'] = 'fh.e.phone';
        }
        if (!array_key_exists($clean['preferred_contact'], self::METHODS)) {
            $clean['preferred_contact'] = 'email';
        }
        if (in_array($clean['preferred_contact'], ['phone', 'whatsapp'], true) && $clean['phone'] === '') {
            $errors['phone'] = 'fh.e.phone_needed';
        }
        if (mb_strlen($clean['message']) < 5) {
            $errors['message'] = 'fh.e.message';
        }
        if ($clean['consent'] !== '1') {
            $errors['consent'] = 'fh.e.consent';
        }
        return [$clean, $errors];
    }

    /** False when this visitor sent too many messages recently. */
    public static function allowed(string $ip): bool
    {
        try {
            $limiter = new FileRateLimiter(ROOT . DS . 'storage' . DS . 'cache' . DS . 'inquiry-throttle');
            return $limiter->hit('inquiry|' . $ip, self::RATE_MAX, self::RATE_WINDOW)->allowed;
        } catch (\Throwable) {
            return true; // never block a real visitor because the folder is not writable
        }
    }

    /**
     * Saves a Contact page inquiry and emails the team. Returns its reference (FH-2026-0143).
     *
     * @param array<string, string> $clean from validate()
     */
    public static function saveContact(array $clean, string $lang, string $pageUrl, string $ip, string $userAgent): string
    {
        return self::save('contact', $clean['topic'], $clean, $lang, $pageUrl, $ip, $userAgent);
    }

    /**
     * Saves a question or viewing request about a property ($mode: question / viewing)
     * and emails the team. A preferred viewing date goes at the top of the message.
     */
    public static function saveListing(int $listingId, string $mode, string $date, array $clean, string $lang, string $pageUrl, string $ip, string $userAgent): string
    {
        if ($mode === 'viewing' && $date !== '') {
            $clean['message'] = self::english('fh.preferred_date') . ': ' . $date . "\n\n" . $clean['message'];
        }
        return self::save('listing', $mode === 'viewing' ? 'viewing' : 'question', $clean, $lang, $pageUrl, $ip, $userAgent, ['listing_id' => $listingId]);
    }

    /** Saves first, then emails; returns the reference. */
    private static function save(string $type, string $service, array $clean, string $lang, string $pageUrl, string $ip, string $userAgent, array $extra = []): string
    {
        $db = App::db();
        $now = date('Y-m-d H:i:s');
        $id = (int) $db->table('inquiries')->insert($extra + [
            'reference' => 'TMP-' . bin2hex(random_bytes(6)),
            'type' => $type,
            'service' => $service,
            'name' => $clean['name'],
            'email' => $clean['email'],
            'phone' => $clean['phone'] !== '' ? $clean['phone'] : null,
            'preferred_contact' => $clean['preferred_contact'],
            'message' => $clean['message'],
            'consent_at' => $now,
            'lang' => $lang,
            'page_url' => mb_substr($pageUrl, 0, 500),
            'ip' => mb_substr($ip, 0, 45),
            'user_agent' => mb_substr($userAgent, 0, 255),
            'status' => 'new',
            'email_sent' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reference = sprintf('FH-%s-%04d', date('Y'), $id);
        $db->table('inquiries')->where('id', '=', $id)->update(['reference' => $reference]);

        $about = '';
        if (isset($extra['listing_id'])) {
            $rows = $db->table('listings')->select(['reference', 'title'])->where('id', '=', (int) $extra['listing_id'])->limit(1)->get();
            if (isset($rows[0])) {
                $about = (string) $rows[0]->reference . ' · ' . Translations::get($rows[0]->title ?? '', 'en');
            }
        }
        if (self::email($reference, $type, $service, $clean, $lang, $pageUrl, $about)) {
            $db->table('inquiries')->where('id', '=', $id)->update(['email_sent' => 1]);
        }
        return $reference;
    }

    /** Emails the team (in English, the admin language); true when sent. */
    private static function email(string $reference, string $type, string $service, array $clean, string $lang, string $pageUrl, string $about = ''): bool
    {
        $to = Site::setting('notifications.inquiry_email');
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $topic = $type === 'listing'
            ? self::english($service === 'viewing' ? 'fh.viewing_request' : 'fh.property_question')
            : self::english(self::TOPICS[$service][0] ?? 'fh.topic.other');
        $method = self::english(self::METHODS[$clean['preferred_contact']] ?? 'fh.contact.email');
        $lines = [
            'New inquiry from the website: ' . $reference,
            '',
            'Topic: ' . $topic,
            ...($about !== '' ? ['Property: ' . $about] : []),
            'Name: ' . $clean['name'],
            'Email: ' . $clean['email'],
            'Phone: ' . ($clean['phone'] !== '' ? $clean['phone'] : '-'),
            'Preferred contact: ' . $method,
            'Language: ' . strtoupper($lang),
            'Page: ' . $pageUrl,
            '',
            $clean['message'],
            '',
            'Open it in the admin: Inquiries.',
        ];
        try {
            Mailer::send(new Message(
                to: $to,
                subject: $reference . ' · ' . $topic . ($about !== '' ? ' · ' . strtok($about, ' ') : '') . ' · ' . $clean['name'],
                text: implode("\n", $lines),
                headers: ['Reply-To' => $clean['email']],
            ));
            return true;
        } catch (\Throwable $error) {
            error_log('FECTUM inquiry ' . $reference . ' saved, email not sent: ' . $error->getMessage());
            return false;
        }
    }

    /** A label in English for the team's email. */
    private static function english(string $key): string
    {
        static $en = null;
        $en ??= is_file(ROOT . '/lang/en.php') ? (array) require ROOT . '/lang/en.php' : [];
        return (string) ($en[$key] ?? $key);
    }
}
