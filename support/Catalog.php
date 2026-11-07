<?php
declare(strict_types=1);

namespace FloCMS\Support;

/**
 * Fixed option lists of FECTUM Hellas content, shared by the admin resources
 * and the website templates. Values are stored in the database; labels are
 * keys of lang/<code>.php, so the admin and the website show them in the
 * visitor's language.
 */
final class Catalog
{
    /** Sales status of a listing (design note D3). */
    public static function listingStatuses(): array
    {
        return [
            'for_sale' => 'fh.status.for_sale',
            'for_rent' => 'fh.status.for_rent',
            'off_plan' => 'fh.status.off_plan',
            'sold' => 'fh.status.sold',
            'rented' => 'fh.status.rented',
        ];
    }

    /** Statuses still on the market: shown in default search results; "Available" in a residences table. */
    public static function availableStatuses(): array
    {
        return ['for_sale', 'for_rent', 'off_plan'];
    }

    public static function listingStatusColors(): array
    {
        return ['for_sale' => 'green', 'for_rent' => 'blue', 'off_plan' => 'purple', 'sold' => 'gray', 'rented' => 'gray'];
    }

    /** Draft or published, for every content type. */
    public static function publishStatuses(): array
    {
        return ['draft' => 'fh.publish.draft', 'published' => 'fh.publish.published'];
    }

    public static function publishColors(): array
    {
        return ['draft' => 'yellow', 'published' => 'green'];
    }

    /** Greek energy performance classes (PEA); the Greek site shows Α+, Α, Β+, Β, Γ… */
    public static function energyClasses(): array
    {
        return ['A+' => 'A+', 'A' => 'A', 'B+' => 'B+', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F', 'G' => 'G'];
    }

    /** The letter shown on the website for an energy class in a language. */
    public static function energyClassLabel(string $class, string $lang): string
    {
        if ($lang !== 'el') {
            return $class;
        }
        return strtr($class, ['A' => 'Α', 'B' => 'Β', 'C' => 'Γ', 'D' => 'Δ', 'E' => 'Ε', 'F' => 'Ζ', 'G' => 'Η']);
    }

    /** Construction progress of a development (D6). */
    /** Insights categories (Stage 3c), in tab order. */
    public static function articleCategories(): array
    {
        return [
            'buying_guide' => 'fh.cat.buying_guide',
            'market_insights' => 'fh.cat.market_insights',
            'golden_visa' => 'fh.cat.golden_visa',
            'company_news' => 'fh.cat.company_news',
        ];
    }

    public static function projectProgress(): array
    {
        return [
            'planned' => 'fh.progress.planned',
            'under_construction' => 'fh.progress.under_construction',
            'completed' => 'fh.progress.completed',
        ];
    }

    public static function projectProgressColors(): array
    {
        return ['planned' => 'gray', 'under_construction' => 'yellow', 'completed' => 'green'];
    }

    /** Status of one construction stage. */
    public static function stageStatuses(): array
    {
        return [
            'completed' => 'fh.stage.completed',
            'in_progress' => 'fh.stage.in_progress',
            'upcoming' => 'fh.stage.upcoming',
        ];
    }

    /** Inquiry pipeline (D10). */
    public static function inquiryStatuses(): array
    {
        return ['new' => 'fh.inquiry.new', 'contacted' => 'fh.inquiry.contacted', 'closed' => 'fh.inquiry.closed'];
    }

    public static function inquiryStatusColors(): array
    {
        return ['new' => 'red', 'contacted' => 'yellow', 'closed' => 'gray'];
    }

    /** Where an inquiry was sent from. */
    public static function inquiryTypes(): array
    {
        return [
            'listing' => 'fh.inquiry_type.listing',
            'project' => 'fh.inquiry_type.project',
            'service' => 'fh.inquiry_type.service',
            'saved' => 'fh.inquiry_type.saved',
            'contact' => 'fh.inquiry_type.contact',
        ];
    }

    public static function contactMethods(): array
    {
        return ['email' => 'fh.contact.email', 'phone' => 'fh.contact.phone', 'whatsapp' => 'fh.contact.whatsapp'];
    }

    /** True for a YouTube or Vimeo address (the only video links we embed). */
    public static function isVideoLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        return in_array($host, ['youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', 'youtube-nocookie.com'], true)
            && str_starts_with(strtolower($url), 'https://');
    }
}
