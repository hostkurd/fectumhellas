<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

/**
 * The website's starting Pages (Stage 3a): Home, Services, About,
 * International Buyers and Contact, built from the site blocks, in English,
 * Arabic and Greek. Everything is then edited in Admin › Pages.
 *
 *     php flo db:seed --class=PagesSeeder
 *
 * Safe to run again: a page whose English slug already exists keeps what
 * editors made of it; one nobody has saved in the admin since it was seeded
 * gets the newest starting content. Home is skipped when another home page
 * is set. (A page whose English slug an editor changed would be added again.) The sample photos
 * (database/seeders/samples) are copied into the media library once, with
 * "SAMPLE" in their title: replace them with the company's own photos.
 * Texts about buying, tax and residency are starting copy for the legal
 * partners to review before the site goes live.
 */
final class PagesSeeder extends Seeder
{
    private const LANGS = ['en', 'ar', 'el'];

    /** @var array<string, string> sample name => media id */
    private array $media = [];

    private int $seq = 0;

    public function run(Database $db): void
    {
        $this->media = $this->samplePhotos($db);
        $existing = $this->existingSlugs($db);
        $hasHome = $db->table('admin_pages')->where('is_home', '=', 1)->count() > 0;
        $now = date('Y-m-d H:i:s');
        $sort = 0;
        foreach ($this->pages() as $slug => $page) {
            $sort++;
            $blocks = [];
            foreach (self::LANGS as $lang) {
                $blocks[$lang] = $page['blocks']($lang);
            }
            $row = $existing[$slug] ?? null;
            if ($row !== null) {
                // Never saved in the admin since it was seeded: bring it up to the newest starting content.
                if ((string) $row->created_at === (string) $row->updated_at) {
                    $db->table('admin_pages')->where('id', '=', (int) $row->id)->update([
                        'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'meta_description' => json_encode($page['meta_description'], JSON_UNESCAPED_UNICODE),
                    ]);
                }
                continue;
            }
            if ($slug === 'home' && $hasHome) {
                continue;
            }
            $db->table('admin_pages')->insert([
                'title' => json_encode($page['title'], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(array_fill_keys(self::LANGS, $slug)),
                'type' => 'builder',
                'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'menu' => 0,
                'sort' => $sort,
                'meta_title' => json_encode($page['meta_title'] ?? $page['title'], JSON_UNESCAPED_UNICODE),
                'meta_description' => json_encode($page['meta_description'], JSON_UNESCAPED_UNICODE),
                'noindex' => 0,
                'is_home' => $slug === 'home' ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        // The printed blocks are cached: start fresh.
        foreach (glob(dirname(__DIR__, 2) . '/storage/cache/blocks/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pages(): array
    {
        return [
            'home' => [
                'title' => ['en' => 'Home', 'ar' => 'الرئيسية', 'el' => 'Αρχική'],
                'meta_title' => ['en' => 'FECTUM Hellas · Real estate development and investment in Greece', 'ar' => 'فيكتوم هيلاس · التطوير والاستثمار العقاري في اليونان', 'el' => 'FECTUM Hellas · Ανάπτυξη και επένδυση ακινήτων στην Ελλάδα'],
                'meta_description' => [
                    'en' => 'FECTUM Hellas develops and sources residential properties in Greece for homeowners and investors, with end-to-end property support.',
                    'ar' => 'تطوّر فيكتوم هيلاس العقارات السكنية في اليونان وتوفّرها لأصحاب المنازل والمستثمرين، مع دعم عقاري متكامل.',
                    'el' => 'Η FECTUM Hellas αναπτύσσει και εντοπίζει κατοικίες στην Ελλάδα για ιδιοκτήτες και επενδυτές, με ολοκληρωμένη υποστήριξη.',
                ],
                'blocks' => fn (string $l): array => $this->home($l),
            ],
            'services' => [
                'title' => ['en' => 'Services', 'ar' => 'الخدمات', 'el' => 'Υπηρεσίες'],
                'meta_description' => [
                    'en' => 'Property development and construction, sales and acquisition, and investment and residency support in Greece.',
                    'ar' => 'تطوير العقارات وبناؤها، والبيع والشراء، ودعم الاستثمار والإقامة في اليونان.',
                    'el' => 'Ανάπτυξη και κατασκευή ακινήτων, πωλήσεις και αγορές, υποστήριξη επένδυσης και διαμονής στην Ελλάδα.',
                ],
                'blocks' => fn (string $l): array => $this->services($l),
            ],
            'about' => [
                'title' => ['en' => 'About', 'ar' => 'من نحن', 'el' => 'Η εταιρεία'],
                'meta_description' => [
                    'en' => 'FECTUM Hellas is a real estate development and investment company in Greece.',
                    'ar' => 'فيكتوم هيلاس شركة تطوير واستثمار عقاري في اليونان.',
                    'el' => 'Η FECTUM Hellas είναι εταιρεία ανάπτυξης και επένδυσης ακινήτων στην Ελλάδα.',
                ],
                'blocks' => fn (string $l): array => $this->about($l),
            ],
            'international-buyers' => [
                'title' => ['en' => 'International buyers', 'ar' => 'المشترون الدوليون', 'el' => 'Διεθνείς αγοραστές'],
                'meta_description' => [
                    'en' => 'Buying property in Greece as an international buyer: ownership, process, costs and tax numbers.',
                    'ar' => 'شراء عقار في اليونان كمشترٍ دولي: الملكية والإجراءات والتكاليف والرقم الضريبي.',
                    'el' => 'Αγορά ακινήτου στην Ελλάδα ως διεθνής αγοραστής: ιδιοκτησία, διαδικασία, κόστη και ΑΦΜ.',
                ],
                'blocks' => fn (string $l): array => $this->buyers($l),
            ],
            'contact' => [
                'title' => ['en' => 'Contact', 'ar' => 'اتصل بنا', 'el' => 'Επικοινωνία'],
                'meta_description' => [
                    'en' => 'Visit, call or message FECTUM Hellas in Athens.',
                    'ar' => 'زوروا فيكتوم هيلاس في أثينا أو اتصلوا بنا أو راسلونا.',
                    'el' => 'Επισκεφθείτε, καλέστε ή στείλτε μήνυμα στη FECTUM Hellas στην Αθήνα.',
                ],
                'blocks' => fn (string $l): array => $this->contact($l),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function home(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            $this->block('slider', ['name' => 'Home', 'height' => 'screen', 'effect' => 'fade', 'autoplay' => '1', 'interval' => '8', 'arrows' => '1', 'dots' => '1', 'fit' => 'cover', 'overlay' => '0',
                'slides' => array_values(array_filter([
                    $this->slide('coast', $t(['en' => 'Building value. Across Greece.', 'ar' => 'نبني القيمة في أنحاء اليونان.', 'el' => 'Χτίζουμε αξία σε όλη την Ελλάδα.']),
                        $t(['en' => 'FECTUM Hellas develops and sources residential properties in Greece for homeowners and investors, combining local market knowledge, development expertise and end-to-end property support.',
                            'ar' => 'تطوّر فيكتوم هيلاس العقارات السكنية في اليونان وتوفّرها لأصحاب المنازل والمستثمرين، جامعةً بين معرفة السوق المحلية وخبرة التطوير والدعم العقاري المتكامل.',
                            'el' => 'Η FECTUM Hellas αναπτύσσει και εντοπίζει κατοικίες στην Ελλάδα για ιδιοκτήτες και επενδυτές, συνδυάζοντας γνώση της τοπικής αγοράς, εμπειρία στην ανάπτυξη και ολοκληρωμένη υποστήριξη.']),
                        $t(['en' => 'Explore properties', 'ar' => 'تصفّح العقارات', 'el' => 'Δείτε τα ακίνητα']), "/$l/properties"),
                    $this->slide('town', $t(['en' => 'From development to delivery.', 'ar' => 'من التطوير حتى التسليم.', 'el' => 'Από την ανάπτυξη στην παράδοση.']),
                        $t(['en' => 'We develop and construct private homes and residential buildings, combining contemporary architecture, quality materials and practical living spaces.',
                            'ar' => 'نطوّر ونبني المنازل الخاصة والمباني السكنية، بعمارة معاصرة ومواد عالية الجودة ومساحات معيشة عملية.',
                            'el' => 'Αναπτύσσουμε και κατασκευάζουμε κατοικίες και πολυκατοικίες, με σύγχρονη αρχιτεκτονική, ποιοτικά υλικά και λειτουργικούς χώρους.']),
                        $t(['en' => 'Explore developments', 'ar' => 'تصفّح المشاريع', 'el' => 'Δείτε τα έργα']), "/$l/developments"),
                    $this->slide('sea', $t(['en' => 'Invest in Greece with confidence.', 'ar' => 'استثمر في اليونان بثقة.', 'el' => 'Επενδύστε στην Ελλάδα με σιγουριά.']),
                        $t(['en' => 'For international investors, we identify suitable opportunities and, where applicable, coordinate Golden Visa-related acquisitions with qualified legal and professional partners.',
                            'ar' => 'نحدّد للمستثمرين الدوليين الفرص المناسبة، وننسّق عند الحاجة عمليات الشراء المتعلقة بالتأشيرة الذهبية مع شركاء قانونيين ومهنيين مؤهلين.',
                            'el' => 'Για διεθνείς επενδυτές εντοπίζουμε κατάλληλες ευκαιρίες και, όπου χρειάζεται, συντονίζουμε αγορές για τη Χρυσή Βίζα με εξειδικευμένους νομικούς και επαγγελματίες συνεργάτες.']),
                        $t(['en' => 'International buyers', 'ar' => 'المشترون الدوليون', 'el' => 'Διεθνείς αγοραστές']), "/$l/international-buyers"),
                ]))]),
            $this->block('quick_search', ['heading' => $t(['en' => 'Find a property', 'ar' => 'ابحث عن عقار', 'el' => 'Βρείτε ακίνητο'])]),
            $this->block('intro', [
                'eyebrow' => $t(['en' => 'About FECTUM Hellas', 'ar' => 'عن فيكتوم هيلاس', 'el' => 'Η FECTUM Hellas']),
                'heading' => $t(['en' => 'Property in Greece, built around your future.', 'ar' => 'عقارات في اليونان، مصمّمة حول مستقبلك.', 'el' => 'Ακίνητα στην Ελλάδα, γύρω από το μέλλον σας.']),
                'statement' => $t(['en' => 'We develop and source homes in Greece, and stand beside you from the first viewing to completion.', 'ar' => 'نطوّر العقارات السكنية في اليونان ونوفّرها، ونرافقك من أول معاينة حتى إتمام الشراء.', 'el' => 'Αναπτύσσουμε και εντοπίζουμε κατοικίες στην Ελλάδα και είμαστε δίπλα σας από την πρώτη επίσκεψη έως την ολοκλήρωση της αγοράς.']),
                'text' => $t(['en' => 'FECTUM Hellas is a real estate development and investment company in Greece. We develop residential properties and assist local and international clients with property sales, acquisitions and real estate investment opportunities.',
                    'ar' => 'فيكتوم هيلاس شركة تطوير واستثمار عقاري في اليونان. نطوّر العقارات السكنية ونساعد العملاء المحليين والدوليين في بيع العقارات وشرائها وفي فرص الاستثمار العقاري.',
                    'el' => 'Η FECTUM Hellas είναι εταιρεία ανάπτυξης και επένδυσης ακινήτων στην Ελλάδα. Αναπτύσσουμε κατοικίες και υποστηρίζουμε πελάτες από την Ελλάδα και το εξωτερικό σε πωλήσεις, αγορές και επενδυτικές ευκαιρίες.']),
                'image' => $this->photo('bay'),
                'points' => [
                    $this->item(['title' => $t(['en' => 'Development expertise', 'ar' => 'خبرة في التطوير', 'el' => 'Εμπειρία στην ανάπτυξη']),
                        'text' => $t(['en' => 'We understand property from the ground up, from planning and construction to sale and acquisition.', 'ar' => 'نفهم العقار من أساسه، من التخطيط والبناء حتى البيع والشراء.', 'el' => 'Γνωρίζουμε το ακίνητο από τα θεμέλια: από τον σχεδιασμό και την κατασκευή έως την πώληση και την αγορά.'])]),
                    $this->item(['title' => $t(['en' => 'Local market knowledge', 'ar' => 'معرفة بالسوق المحلية', 'el' => 'Γνώση της τοπικής αγοράς']),
                        'text' => $t(['en' => 'Our understanding of the Greek market helps clients judge opportunities by location, quality and long-term potential.', 'ar' => 'تساعد معرفتنا بالسوق اليونانية العملاء على تقييم الفرص حسب الموقع والجودة والإمكانات على المدى البعيد.', 'el' => 'Η γνώση μας για την ελληνική αγορά βοηθά τους πελάτες να αξιολογούν ευκαιρίες με βάση την τοποθεσία, την ποιότητα και τη μακροπρόθεσμη αξία.'])]),
                ],
                'link_text' => $t(['en' => 'Discover FECTUM Hellas', 'ar' => 'تعرّف على فيكتوم هيلاس', 'el' => 'Γνωρίστε τη FECTUM Hellas']),
                'link' => 'about',
            ]),
            $this->block('featured_properties', [
                'eyebrow' => $t(['en' => 'Properties', 'ar' => 'العقارات', 'el' => 'Ακίνητα']),
                'heading' => $t(['en' => 'Selected for living and investment', 'ar' => 'مختارة للسكن والاستثمار', 'el' => 'Επιλεγμένα για κατοικία και επένδυση']),
                'text' => $t(['en' => 'Residential properties in Greece selected for location, quality and long-term value.', 'ar' => 'عقارات سكنية في اليونان مختارة لموقعها وجودتها وقيمتها على المدى البعيد.', 'el' => 'Κατοικίες στην Ελλάδα επιλεγμένες για την τοποθεσία, την ποιότητα και τη μακροπρόθεσμη αξία τους.']),
                'count' => '6',
                'link_text' => $t(['en' => 'View all properties', 'ar' => 'عرض كل العقارات', 'el' => 'Όλα τα ακίνητα']),
            ]),
            $this->block('developments_band', [
                'eyebrow' => $t(['en' => 'Developments', 'ar' => 'المشاريع', 'el' => 'Έργα']),
                'heading' => $t(['en' => 'From development to delivery.', 'ar' => 'من التطوير حتى التسليم.', 'el' => 'Από την ανάπτυξη στην παράδοση.']),
                'text' => $t(['en' => 'FECTUM Hellas develops and constructs private homes and residential buildings in Greece, combining contemporary architecture, quality materials and practical living spaces.',
                    'ar' => 'تطوّر فيكتوم هيلاس وتبني المنازل الخاصة والمباني السكنية في اليونان، بعمارة معاصرة ومواد عالية الجودة ومساحات معيشة عملية.',
                    'el' => 'Η FECTUM Hellas αναπτύσσει και κατασκευάζει κατοικίες και πολυκατοικίες στην Ελλάδα, με σύγχρονη αρχιτεκτονική, ποιοτικά υλικά και λειτουργικούς χώρους.']),
                'image' => $this->photo('hillside'),
                'link_text' => $t(['en' => 'Explore developments', 'ar' => 'تصفّح المشاريع', 'el' => 'Δείτε τα έργα']),
            ]),
            $this->block('service_tiles', $this->tiles($l, true)),
            $this->block('buyers_guide', $this->guide($l)),
            $this->block('latest_insights', [
                'eyebrow' => $t(['en' => 'Insights & property guide', 'ar' => 'مقالات ودليل العقارات', 'el' => 'Άρθρα & οδηγός ακινήτων']),
                'heading' => $t(['en' => 'Latest news and guides', 'ar' => 'أحدث الأخبار والأدلة', 'el' => 'Νέα και οδηγοί']),
                'count' => '3',
                'link_text' => $t(['en' => 'All articles', 'ar' => 'كل المقالات', 'el' => 'Όλα τα άρθρα']),
            ]),
            $this->block('faq', [
                'heading' => $t(['en' => 'Questions about FECTUM Hellas', 'ar' => 'أسئلة عن فيكتوم هيلاس', 'el' => 'Ερωτήσεις για τη FECTUM Hellas']),
                'intro' => $t(['en' => "Can't find your answer? Contact our team.", 'ar' => 'لم تجد إجابتك؟ تواصل مع فريقنا.', 'el' => 'Δεν βρήκατε την απάντηση; Επικοινωνήστε με την ομάδα μας.']),
                'open_first' => '1', 'one_open' => '1', 'schema' => '1',
                'items' => $this->faqItems($l, [
                    [['en' => 'What is FECTUM Hellas?', 'ar' => 'ما هي فيكتوم هيلاس؟', 'el' => 'Τι είναι η FECTUM Hellas;'],
                     ['en' => 'FECTUM Hellas is a real estate development and investment company in Greece specialising in residential development, construction, sales and acquisitions, with support for international property investors.',
                      'ar' => 'فيكتوم هيلاس شركة تطوير واستثمار عقاري في اليونان، متخصصة في تطوير العقارات السكنية وبنائها وبيعها وشرائها، مع دعم للمستثمرين العقاريين الدوليين.',
                      'el' => 'Η FECTUM Hellas είναι εταιρεία ανάπτυξης και επένδυσης ακινήτων στην Ελλάδα, με εξειδίκευση στην ανάπτυξη, κατασκευή, πώληση και αγορά κατοικιών και υποστήριξη για διεθνείς επενδυτές.']],
                    [['en' => 'Does FECTUM Hellas work with international buyers?', 'ar' => 'هل تتعامل فيكتوم هيلاس مع المشترين الدوليين؟', 'el' => 'Συνεργάζεται η FECTUM Hellas με διεθνείς αγοραστές;'],
                     ['en' => 'Yes. We assist international clients who want to buy or invest in property in Greece and coordinate the purchase with the relevant professional partners.',
                      'ar' => 'نعم. نساعد العملاء الدوليين الراغبين في شراء عقار في اليونان أو الاستثمار فيه، وننسّق عملية الشراء مع الشركاء المهنيين المعنيين.',
                      'el' => 'Ναι. Υποστηρίζουμε πελάτες από το εξωτερικό που θέλουν να αγοράσουν ή να επενδύσουν σε ακίνητο στην Ελλάδα και συντονίζουμε την αγορά με τους αρμόδιους επαγγελματίες.']],
                    [['en' => 'Does FECTUM Hellas develop its own properties?', 'ar' => 'هل تطوّر فيكتوم هيلاس عقاراتها الخاصة؟', 'el' => 'Αναπτύσσει η FECTUM Hellas δικά της ακίνητα;'],
                     ['en' => 'Yes. Property development and construction are core parts of our work.', 'ar' => 'نعم. تطوير العقارات وبناؤها من صميم عملنا.', 'el' => 'Ναι. Η ανάπτυξη και η κατασκευή ακινήτων είναι βασικό μέρος της δουλειάς μας.']],
                    [['en' => 'Can FECTUM Hellas assist with Golden Visa properties?', 'ar' => 'هل تساعد فيكتوم هيلاس في عقارات التأشيرة الذهبية؟', 'el' => 'Βοηθά η FECTUM Hellas σε ακίνητα για Χρυσή Βίζα;'],
                     ['en' => 'We help investors find suitable properties and coordinate the purchase with qualified legal professionals where residency is involved.',
                      'ar' => 'نساعد المستثمرين في إيجاد العقارات المناسبة، وننسّق الشراء مع مختصين قانونيين مؤهلين عندما يتعلق الأمر بالإقامة.',
                      'el' => 'Βοηθάμε τους επενδυτές να βρουν κατάλληλα ακίνητα και συντονίζουμε την αγορά με εξειδικευμένους νομικούς όταν αφορά άδεια διαμονής.']],
                ]),
            ], ['width' => 'boxed', 'space_above' => 'large', 'space_below' => 'large']),
            $this->block('closing_cta', $this->cta($l)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function services(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            $this->block('page_header', [
                'eyebrow' => $t(['en' => 'Services', 'ar' => 'الخدمات', 'el' => 'Υπηρεσίες']),
                'heading' => $t(['en' => 'Real estate and investment services', 'ar' => 'خدمات العقارات والاستثمار', 'el' => 'Υπηρεσίες ακινήτων και επενδύσεων']),
                'text' => $t(['en' => 'From the first plan to the keys in your hand: development, sales and acquisition, and support for international investors.', 'ar' => 'من المخطط الأول حتى استلام المفاتيح: التطوير والبيع والشراء ودعم المستثمرين الدوليين.', 'el' => 'Από το πρώτο σχέδιο μέχρι την παράδοση των κλειδιών: ανάπτυξη, πωλήσεις και αγορές, υποστήριξη διεθνών επενδυτών.']),
                'image' => $this->photo('harbour'),
            ]),
            $this->block('service_tiles', $this->tiles($l, false)),
            $this->block('intro', [
                'eyebrow' => $t(['en' => 'How we work', 'ar' => 'كيف نعمل', 'el' => 'Πώς δουλεύουμε']),
                'heading' => $t(['en' => 'One team from search to completion.', 'ar' => 'فريق واحد من البحث حتى إتمام الصفقة.', 'el' => 'Μία ομάδα από την αναζήτηση έως την ολοκλήρωση.']),
                'text' => $t(['en' => 'We listen to what you need, shortlist the right properties, and coordinate every step with our legal, technical and financial partners.', 'ar' => 'نستمع إلى احتياجاتك، ونختار العقارات المناسبة، وننسّق كل خطوة مع شركائنا القانونيين والفنيين والماليين.', 'el' => 'Ακούμε τι χρειάζεστε, επιλέγουμε τα κατάλληλα ακίνητα και συντονίζουμε κάθε βήμα με τους νομικούς, τεχνικούς και οικονομικούς συνεργάτες μας.']),
                'points' => [
                    $this->item(['title' => $t(['en' => 'Clear advice', 'ar' => 'نصيحة واضحة', 'el' => 'Σαφής καθοδήγηση']), 'text' => $t(['en' => 'Honest guidance on location, price and long-term value.', 'ar' => 'إرشاد صادق حول الموقع والسعر والقيمة على المدى البعيد.', 'el' => 'Ειλικρινής καθοδήγηση για τοποθεσία, τιμή και μακροπρόθεσμη αξία.'])]),
                    $this->item(['title' => $t(['en' => 'Trusted partners', 'ar' => 'شركاء موثوقون', 'el' => 'Αξιόπιστοι συνεργάτες']), 'text' => $t(['en' => 'Lawyers, engineers and notaries we work with every day.', 'ar' => 'محامون ومهندسون وكتّاب عدل نعمل معهم يوميًا.', 'el' => 'Δικηγόροι, μηχανικοί και συμβολαιογράφοι με τους οποίους συνεργαζόμαστε καθημερινά.'])]),
                ],
                'link_text' => $t(['en' => 'Talk to our team', 'ar' => 'تحدّث مع فريقنا', 'el' => 'Μιλήστε με την ομάδα μας']),
                'link' => 'contact',
            ]),
            $this->block('closing_cta', $this->cta($l)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function about(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            $this->block('page_header', [
                'eyebrow' => $t(['en' => 'About', 'ar' => 'من نحن', 'el' => 'Η εταιρεία']),
                'heading' => $t(['en' => 'Property, built around your future', 'ar' => 'عقارات مصمّمة حول مستقبلك', 'el' => 'Ακίνητα, γύρω από το μέλλον σας']),
                'text' => $t(['en' => 'A real estate development and investment company in Greece, developing residential properties and assisting local and international clients with sales, acquisitions and investment.',
                    'ar' => 'شركة تطوير واستثمار عقاري في اليونان، تطوّر العقارات السكنية وتساعد العملاء المحليين والدوليين في البيع والشراء والاستثمار.',
                    'el' => 'Εταιρεία ανάπτυξης και επένδυσης ακινήτων στην Ελλάδα, που αναπτύσσει κατοικίες και υποστηρίζει πελάτες από την Ελλάδα και το εξωτερικό σε πωλήσεις, αγορές και επενδύσεις.']),
                'image' => $this->photo('town'),
            ]),
            $this->block('story', [
                'eyebrow' => $t(['en' => 'Who we are', 'ar' => 'من نحن', 'el' => 'Ποιοι είμαστε']),
                'heading' => $t(['en' => 'Property, from the ground up.', 'ar' => 'العقار من أساسه.', 'el' => 'Το ακίνητο, από τα θεμέλια.']),
                'text' => $t([
                    'en' => "We develop and construct private homes and residential buildings, and we help clients buy, sell and invest in residential property across Greece. Because we build, we evaluate every property the way a developer does: location, quality and long-term value.\n\nFor international buyers we coordinate each acquisition with qualified legal and professional partners, so clients can buy with confidence even from abroad.",
                    'ar' => "نطوّر ونبني المنازل الخاصة والمباني السكنية، ونساعد العملاء في شراء العقارات السكنية وبيعها والاستثمار فيها في أنحاء اليونان. ولأننا نبني، نقيّم كل عقار كما يفعل المطوّر: الموقع والجودة والقيمة على المدى البعيد.\n\nوللمشترين الدوليين ننسّق كل عملية شراء مع شركاء قانونيين ومهنيين مؤهلين، ليشتري العملاء بثقة حتى من الخارج.",
                    'el' => "Αναπτύσσουμε και κατασκευάζουμε κατοικίες και πολυκατοικίες, και βοηθάμε πελάτες να αγοράσουν, να πουλήσουν και να επενδύσουν σε κατοικίες σε όλη την Ελλάδα. Επειδή χτίζουμε, αξιολογούμε κάθε ακίνητο όπως ένας κατασκευαστής: τοποθεσία, ποιότητα και μακροπρόθεσμη αξία.\n\nΓια διεθνείς αγοραστές συντονίζουμε κάθε αγορά με εξειδικευμένους νομικούς και επαγγελματίες συνεργάτες, ώστε να αγοράζουν με σιγουριά ακόμη και από το εξωτερικό.",
                ]),
            ]),
            $this->block('numbered_columns', [
                'eyebrow' => $t(['en' => 'What we do', 'ar' => 'ما نقدّمه', 'el' => 'Τι κάνουμε']),
                'heading' => $t(['en' => 'Development, sales and investment, in one team.', 'ar' => 'التطوير والبيع والاستثمار في فريق واحد.', 'el' => 'Ανάπτυξη, πωλήσεις και επενδύσεις, σε μία ομάδα.']),
                'items' => [
                    $this->item(['title' => $t(['en' => 'Property development & construction', 'ar' => 'تطوير العقارات وبناؤها', 'el' => 'Ανάπτυξη & κατασκευή ακινήτων']), 'text' => $t(['en' => 'Private homes and residential buildings with contemporary architecture and quality materials.', 'ar' => 'منازل خاصة ومبانٍ سكنية بعمارة معاصرة ومواد عالية الجودة.', 'el' => 'Κατοικίες και πολυκατοικίες με σύγχρονη αρχιτεκτονική και ποιοτικά υλικά.']), 'link' => 'services']),
                    $this->item(['title' => $t(['en' => 'Property sales & acquisition', 'ar' => 'بيع العقارات وشراؤها', 'el' => 'Πωλήσεις & αγορές ακινήτων']), 'text' => $t(['en' => 'From property selection and negotiation through to acquisition and completion.', 'ar' => 'من اختيار العقار والتفاوض حتى الشراء وإتمام الصفقة.', 'el' => 'Από την επιλογή και τη διαπραγμάτευση έως την αγορά και την ολοκλήρωση.']), 'link' => 'services']),
                    $this->item(['title' => $t(['en' => 'Investment & residency support', 'ar' => 'دعم الاستثمار والإقامة', 'el' => 'Υποστήριξη επένδυσης & διαμονής']), 'text' => $t(['en' => 'Opportunities for international investors, with Golden Visa coordination through qualified partners.', 'ar' => 'فرص للمستثمرين الدوليين، مع تنسيق التأشيرة الذهبية عبر شركاء مؤهلين.', 'el' => 'Ευκαιρίες για διεθνείς επενδυτές, με συντονισμό για τη Χρυσή Βίζα μέσω εξειδικευμένων συνεργατών.']), 'link' => 'international-buyers']),
                ],
            ]),
            $this->block('feature_band', [
                'eyebrow' => $t(['en' => 'Why work with us', 'ar' => 'لماذا تعمل معنا', 'el' => 'Γιατί να συνεργαστείτε μαζί μας']),
                'heading' => $t(['en' => 'Why work with FECTUM Hellas?', 'ar' => 'لماذا فيكتوم هيلاس؟', 'el' => 'Γιατί FECTUM Hellas;']),
                'image' => $this->photo('hillside'),
                'points' => [
                    $this->item(['title' => $t(['en' => 'Development expertise', 'ar' => 'خبرة في التطوير', 'el' => 'Εμπειρία στην ανάπτυξη']), 'text' => $t(['en' => 'We understand property from the ground up, from planning and construction to sale and acquisition.', 'ar' => 'نفهم العقار من أساسه، من التخطيط والبناء حتى البيع والشراء.', 'el' => 'Γνωρίζουμε το ακίνητο από τα θεμέλια: από τον σχεδιασμό και την κατασκευή έως την πώληση και την αγορά.'])]),
                    $this->item(['title' => $t(['en' => 'Local market knowledge', 'ar' => 'معرفة بالسوق المحلية', 'el' => 'Γνώση της τοπικής αγοράς']), 'text' => $t(['en' => 'Our understanding of the Greek property market helps clients evaluate opportunities by location, quality and long-term potential.', 'ar' => 'تساعد معرفتنا بسوق العقارات اليونانية العملاء على تقييم الفرص حسب الموقع والجودة والإمكانات على المدى البعيد.', 'el' => 'Η γνώση μας για την ελληνική αγορά ακινήτων βοηθά τους πελάτες να αξιολογούν ευκαιρίες με βάση την τοποθεσία, την ποιότητα και τη μακροπρόθεσμη αξία.'])]),
                ],
            ]),
            $this->block('team_members', [
                'eyebrow' => $t(['en' => 'Our team', 'ar' => 'فريقنا', 'el' => 'Η ομάδα μας']),
                'heading' => $t(['en' => 'The people behind FECTUM Hellas.', 'ar' => 'الفريق وراء فيكتوم هيلاس.', 'el' => 'Οι άνθρωποι πίσω από τη FECTUM Hellas.']),
                'text' => $t(['en' => 'Developers, sales advisers and client managers who work with you from the first viewing to completion.', 'ar' => 'مطوّرون ومستشارو مبيعات ومديرو علاقات يعملون معك من أول معاينة حتى إتمام الصفقة.', 'el' => 'Κατασκευαστές, σύμβουλοι πωλήσεων και υπεύθυνοι πελατών που είναι δίπλα σας από την πρώτη επίσκεψη έως την ολοκλήρωση.']),
                'company' => '1',
            ]),
            $this->block('office_map', [
                'eyebrow' => $t(['en' => 'Our office', 'ar' => 'مكتبنا', 'el' => 'Το γραφείο μας']),
                'heading' => $t(['en' => 'Based in Athens.', 'ar' => 'مقرّنا في أثينا.', 'el' => 'Με έδρα την Αθήνα.']),
                'button' => $t(['en' => 'Contact us', 'ar' => 'تواصل معنا', 'el' => 'Επικοινωνία']),
                'link' => 'contact',
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buyers(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        $items = fn (array $rows): array => array_map(fn (array $r): array => $this->item(array_map(fn ($v) => is_array($v) ? $v[$l] : $v, $r)), $rows);
        return [
            $this->block('page_header', [
                'eyebrow' => $t(['en' => 'International buyers', 'ar' => 'المشترون الدوليون', 'el' => 'Διεθνείς αγοραστές']),
                'heading' => $t(['en' => 'Buying property in Greece as an international buyer', 'ar' => 'شراء عقار في اليونان كمشترٍ دولي', 'el' => 'Αγορά ακινήτου στην Ελλάδα από το εξωτερικό']),
                'text' => $t(['en' => 'Ownership, process, costs and tax numbers, coordinated with our qualified legal and professional partners.', 'ar' => 'الملكية والإجراءات والتكاليف والرقم الضريبي، بالتنسيق مع شركائنا القانونيين والمهنيين المؤهلين.', 'el' => 'Ιδιοκτησία, διαδικασία, κόστη και ΑΦΜ, σε συντονισμό με τους εξειδικευμένους νομικούς και επαγγελματίες συνεργάτες μας.']),
                'image' => $this->photo('sea'),
                'button' => $t(['en' => 'See the steps', 'ar' => 'تعرّف على الخطوات', 'el' => 'Δείτε τα βήματα']),
                'button_link' => '#steps',
                'button2' => $t(['en' => 'Talk to our team', 'ar' => 'تحدّث مع فريقنا', 'el' => 'Μιλήστε με την ομάδα μας']),
                'link2' => 'contact',
            ]),
            $this->block('facts', [
                'heading' => $t(['en' => 'At a glance', 'ar' => 'لمحة سريعة', 'el' => 'Με μια ματιά']),
                'items' => $items([
                    ['icon' => 'globe', 'title' => ['en' => 'Open to international buyers', 'ar' => 'متاح للمشترين الدوليين', 'el' => 'Ανοιχτό σε διεθνείς αγοραστές'],
                     'text' => ['en' => 'Buyers from the EU and from outside the EU can buy in most areas of Greece.', 'ar' => 'يمكن للمشترين من داخل الاتحاد الأوروبي وخارجه الشراء في معظم مناطق اليونان.', 'el' => 'Αγοραστές από την ΕΕ και εκτός ΕΕ μπορούν να αγοράσουν στις περισσότερες περιοχές της Ελλάδας.']],
                    ['icon' => 'id', 'title' => ['en' => 'A Greek tax number', 'ar' => 'رقم ضريبي يوناني', 'el' => 'Ελληνικό ΑΦΜ'],
                     'text' => ['en' => 'Every buyer needs a tax number (AFM). Your lawyer can arrange it for you.', 'ar' => 'يحتاج كل مشترٍ إلى رقم ضريبي (AFM)، ويمكن لمحاميك إصداره لك.', 'el' => 'Κάθε αγοραστής χρειάζεται ΑΦΜ. Ο δικηγόρος σας μπορεί να τον εκδώσει για εσάς.']],
                    ['icon' => 'remote', 'title' => ['en' => 'Possible from abroad', 'ar' => 'ممكن من الخارج', 'el' => 'Εφικτό από το εξωτερικό'],
                     'text' => ['en' => 'With a power of attorney your lawyer signs for you; viewings can be by video.', 'ar' => 'بموجب توكيل يوقّع محاميك نيابة عنك، ويمكن إجراء المعاينات بالفيديو.', 'el' => 'Με πληρεξούσιο ο δικηγόρος σας υπογράφει για εσάς· οι προβολές μπορούν να γίνουν με βίντεο.']],
                    ['icon' => 'shield', 'title' => ['en' => 'Checked by a lawyer', 'ar' => 'بمراجعة محامٍ', 'el' => 'Ελεγμένο από δικηγόρο'],
                     'text' => ['en' => 'The title and permits of every property are checked before you sign.', 'ar' => 'يُراجَع سند الملكية وتراخيص كل عقار قبل التوقيع.', 'el' => 'Οι τίτλοι και οι άδειες κάθε ακινήτου ελέγχονται πριν υπογράψετε.']],
                ]),
            ]),
            $this->block('steps', [
                'eyebrow' => $t(['en' => 'The process', 'ar' => 'الإجراءات', 'el' => 'Η διαδικασία']),
                'heading' => $t(['en' => 'How buying works', 'ar' => 'كيف يتم الشراء', 'el' => 'Πώς γίνεται η αγορά']),
                'text' => $t(['en' => 'Five steps from the first viewing to the keys. We coordinate each one with our legal and professional partners.', 'ar' => 'خمس خطوات من أول معاينة حتى استلام المفاتيح، ننسّق كلًّا منها مع شركائنا القانونيين والمهنيين.', 'el' => 'Πέντε βήματα από την πρώτη επίσκεψη έως τα κλειδιά. Συντονίζουμε το καθένα με τους νομικούς και επαγγελματίες συνεργάτες μας.']),
                'items' => $items([
                    ['title' => ['en' => 'Choose a property', 'ar' => 'اختيار العقار', 'el' => 'Επιλογή ακινήτου'],
                     'text' => ['en' => 'We shortlist properties that fit your needs and arrange viewings in person or by video.', 'ar' => 'نختار لك العقارات المناسبة ونرتّب المعاينات حضوريًا أو بالفيديو.', 'el' => 'Επιλέγουμε ακίνητα που σας ταιριάζουν και οργανώνουμε προβολές από κοντά ή με βίντεο.']],
                    ['title' => ['en' => 'Tax number and bank account', 'ar' => 'الرقم الضريبي والحساب المصرفي', 'el' => 'ΑΦΜ και τραπεζικός λογαριασμός'],
                     'text' => ['en' => 'You get a Greek tax number (AFM) and, usually, a Greek bank account.', 'ar' => 'تحصل على رقم ضريبي يوناني (AFM)، وعادةً على حساب مصرفي يوناني.', 'el' => 'Εκδίδετε ελληνικό ΑΦΜ και, συνήθως, ανοίγετε ελληνικό τραπεζικό λογαριασμό.']],
                    ['title' => ['en' => 'Legal checks', 'ar' => 'المراجعة القانونية', 'el' => 'Νομικός έλεγχος'],
                     'text' => ['en' => 'Your lawyer checks the title, the permits and any area restrictions.', 'ar' => 'يتحقق محاميك من سند الملكية والتراخيص وأي قيود على المنطقة.', 'el' => 'Ο δικηγόρος σας ελέγχει τους τίτλους, τις άδειες και τυχόν περιορισμούς της περιοχής.']],
                    ['title' => ['en' => 'Sign at the notary', 'ar' => 'التوقيع لدى كاتب العدل', 'el' => 'Υπογραφή στον συμβολαιογράφο'],
                     'text' => ['en' => 'The contract is signed before a notary and the transfer tax or VAT is paid.', 'ar' => 'يُوقَّع العقد أمام كاتب العدل وتُدفع ضريبة نقل الملكية أو ضريبة القيمة المضافة.', 'el' => 'Το συμβόλαιο υπογράφεται ενώπιον συμβολαιογράφου και καταβάλλεται ο φόρος μεταβίβασης ή ο ΦΠΑ.']],
                    ['title' => ['en' => 'Registration and keys', 'ar' => 'التسجيل والمفاتيح', 'el' => 'Μεταγραφή και κλειδιά'],
                     'text' => ['en' => 'The deed is registered at the land registry and the property is yours.', 'ar' => 'يُسجَّل العقد في السجل العقاري ويصبح العقار ملكك.', 'el' => 'Το συμβόλαιο μεταγράφεται στο κτηματολόγιο και το ακίνητο είναι δικό σας.']],
                ]),
            ], ['anchor' => 'steps']),
            $this->block('cost_list', [
                'eyebrow' => $t(['en' => 'Costs', 'ar' => 'التكاليف', 'el' => 'Κόστη']),
                'heading' => $t(['en' => 'Costs to plan for', 'ar' => 'تكاليف يجب أخذها في الحسبان', 'el' => 'Κόστη που πρέπει να υπολογίσετε']),
                'text' => $t(['en' => 'Besides the price, budget for these costs. The calculator on every property page shows an estimate for that property.', 'ar' => 'إضافة إلى السعر، خصّص ميزانية لهذه التكاليف. تعرض الحاسبة في صفحة كل عقار تقديرًا خاصًا به.', 'el' => 'Πέρα από την τιμή, υπολογίστε αυτά τα κόστη. Ο υπολογιστής σε κάθε ακίνητο δείχνει μια εκτίμηση για το συγκεκριμένο ακίνητο.']),
                'link_text' => $t(['en' => 'Browse properties', 'ar' => 'تصفّح العقارات', 'el' => 'Δείτε τα ακίνητα']),
                'link' => 'properties',
                'items' => $items([
                    ['title' => ['en' => 'Transfer tax or VAT', 'ar' => 'ضريبة نقل الملكية أو القيمة المضافة', 'el' => 'Φόρος μεταβίβασης ή ΦΠΑ'],
                     'text' => ['en' => 'Depends on the property: resale homes pay transfer tax, some new builds VAT.', 'ar' => 'حسب العقار: تُدفع ضريبة نقل الملكية على العقارات المستعملة، وضريبة القيمة المضافة على بعض العقارات الجديدة.', 'el' => 'Εξαρτάται από το ακίνητο: τα μεταπωλούμενα πληρώνουν φόρο μεταβίβασης, ορισμένα νεόδμητα ΦΠΑ.']],
                    ['title' => ['en' => 'Notary', 'ar' => 'كاتب العدل', 'el' => 'Συμβολαιογράφος'],
                     'text' => ['en' => 'For drawing up and signing the contract.', 'ar' => 'لتحرير العقد وتوقيعه.', 'el' => 'Για τη σύνταξη και την υπογραφή του συμβολαίου.']],
                    ['title' => ['en' => 'Land registry', 'ar' => 'السجل العقاري', 'el' => 'Κτηματολόγιο'],
                     'text' => ['en' => 'For registering the deed in your name.', 'ar' => 'لتسجيل العقد باسمك.', 'el' => 'Για τη μεταγραφή του συμβολαίου στο όνομά σας.']],
                    ['title' => ['en' => 'Lawyer', 'ar' => 'المحامي', 'el' => 'Δικηγόρος'],
                     'text' => ['en' => 'For the legal checks and, if needed, signing for you.', 'ar' => 'للمراجعة القانونية، وللتوقيع نيابة عنك عند الحاجة.', 'el' => 'Για τον νομικό έλεγχο και, αν χρειαστεί, την υπογραφή για εσάς.']],
                    ['title' => ['en' => 'Agency fee', 'ar' => 'أتعاب الوكالة', 'el' => 'Αμοιβή μεσίτη'],
                     'text' => ['en' => 'Where an agency is involved in the sale.', 'ar' => 'عند مشاركة وكالة في عملية البيع.', 'el' => 'Όταν στην πώληση συμμετέχει μεσιτικό γραφείο.']],
                ]),
                'note' => $t(['en' => 'Rates change and depend on the property. Your lawyer confirms the exact amounts.', 'ar' => 'تتغيّر النسب وتختلف حسب العقار، ويؤكد محاميك المبالغ الدقيقة.', 'el' => 'Οι συντελεστές αλλάζουν και εξαρτώνται από το ακίνητο. Ο δικηγόρος σας επιβεβαιώνει τα ακριβή ποσά.']),
            ]),
            $this->block('faq', [
                'heading' => $t(['en' => 'Questions international buyers ask', 'ar' => 'أسئلة يطرحها المشترون الدوليون', 'el' => 'Ερωτήσεις διεθνών αγοραστών']),
                'intro' => $t(['en' => 'General information only. Every purchase is checked by a lawyer before you sign.', 'ar' => 'معلومات عامة فقط. يراجع محامٍ كل عملية شراء قبل التوقيع.', 'el' => 'Γενικές πληροφορίες μόνο. Κάθε αγορά ελέγχεται από δικηγόρο πριν την υπογραφή.']),
                'open_first' => '1', 'one_open' => '1', 'schema' => '1',
                'items' => $this->faqItems($l, [
                    [['en' => 'Can foreigners buy property in Greece?', 'ar' => 'هل يمكن للأجانب شراء عقار في اليونان؟', 'el' => 'Μπορούν αλλοδαποί να αγοράσουν ακίνητο στην Ελλάδα;'],
                     ['en' => 'In most areas, yes. Some border regions need an extra permit for buyers from outside the EU; your lawyer checks this for the property you choose.',
                      'ar' => 'نعم في معظم المناطق. تحتاج بعض المناطق الحدودية إلى تصريح إضافي للمشترين من خارج الاتحاد الأوروبي، ويتحقق محاميك من ذلك للعقار الذي تختاره.',
                      'el' => 'Στις περισσότερες περιοχές, ναι. Ορισμένες παραμεθόριες περιοχές απαιτούν επιπλέον άδεια για αγοραστές εκτός ΕΕ· ο δικηγόρος σας το ελέγχει για το ακίνητο που επιλέγετε.']],
                    [['en' => 'What is the process for buying property in Greece?', 'ar' => 'ما هي إجراءات شراء عقار في اليونان؟', 'el' => 'Ποια είναι η διαδικασία αγοράς ακινήτου;'],
                     ['en' => "You choose a property, get a Greek tax number and bank account, your lawyer checks the title and permits, and the contract is signed before a notary and registered.",
                      'ar' => 'تختار العقار، وتحصل على رقم ضريبي يوناني وحساب مصرفي، ويتحقق محاميك من سند الملكية والتراخيص، ثم يُوقَّع العقد أمام كاتب العدل ويُسجَّل.',
                      'el' => 'Επιλέγετε ακίνητο, εκδίδετε ΑΦΜ και ανοίγετε τραπεζικό λογαριασμό, ο δικηγόρος σας ελέγχει τίτλους και άδειες, και το συμβόλαιο υπογράφεται ενώπιον συμβολαιογράφου και μεταγράφεται.']],
                    [['en' => 'What costs are involved when buying Greek property?', 'ar' => 'ما التكاليف المترتبة على شراء عقار يوناني؟', 'el' => 'Ποια κόστη έχει η αγορά ακινήτου;'],
                     ['en' => 'Besides the price: transfer tax or VAT, notary, land registry, lawyer and, where used, agency fees. The calculator on each property shows an estimate.',
                      'ar' => 'إضافة إلى السعر: ضريبة نقل الملكية أو ضريبة القيمة المضافة، ورسوم كاتب العدل والسجل العقاري والمحامي، وأتعاب الوكالة إن وُجدت. تعرض الحاسبة في صفحة كل عقار تقديرًا لها.',
                      'el' => 'Πέρα από την τιμή: φόρος μεταβίβασης ή ΦΠΑ, συμβολαιογράφος, κτηματολόγιο, δικηγόρος και, όπου υπάρχει, αμοιβή μεσίτη. Ο υπολογιστής σε κάθε ακίνητο δείχνει μια εκτίμηση.']],
                    [['en' => 'Do I need a Greek tax number?', 'ar' => 'هل أحتاج إلى رقم ضريبي يوناني؟', 'el' => 'Χρειάζομαι ελληνικό ΑΦΜ;'],
                     ['en' => 'Yes. Every buyer needs a Greek tax number (AFM). It can be issued through your lawyer with a power of attorney.',
                      'ar' => 'نعم. يحتاج كل مشترٍ إلى رقم ضريبي يوناني (AFM)، ويمكن إصداره عن طريق محاميك بموجب توكيل.',
                      'el' => 'Ναι. Κάθε αγοραστής χρειάζεται ΑΦΜ, ο οποίος μπορεί να εκδοθεί μέσω του δικηγόρου σας με πληρεξούσιο.']],
                    [['en' => 'Can I buy property remotely?', 'ar' => 'هل يمكنني الشراء عن بُعد؟', 'el' => 'Μπορώ να αγοράσω εξ αποστάσεως;'],
                     ['en' => 'Yes. With a power of attorney, your lawyer can sign on your behalf, and we can arrange video viewings.',
                      'ar' => 'نعم. يمكن لمحاميك التوقيع نيابة عنك بموجب توكيل، ويمكننا ترتيب معاينات بالفيديو.',
                      'el' => 'Ναι. Με πληρεξούσιο ο δικηγόρος σας μπορεί να υπογράψει για εσάς, και μπορούμε να οργανώσουμε προβολές μέσω βίντεο.']],
                ]),
            ], ['width' => 'boxed', 'space_above' => 'large', 'space_below' => 'large']),
            $this->block('feature_band', [
                'look' => 'light',
                'eyebrow' => $t(['en' => 'Investment & residency', 'ar' => 'الاستثمار والإقامة', 'el' => 'Επένδυση & διαμονή']),
                'heading' => $t(['en' => 'Investing with residency in mind?', 'ar' => 'تستثمر بهدف الإقامة؟', 'el' => 'Επενδύετε με στόχο την άδεια διαμονής;']),
                'text' => $t(['en' => 'Where residency is involved, we help you find suitable properties and coordinate the purchase with qualified legal partners, who review your individual eligibility.', 'ar' => 'عندما يتعلق الأمر بالإقامة، نساعدك في إيجاد العقارات المناسبة وننسّق الشراء مع شركاء قانونيين مؤهلين يراجعون أهليتك.', 'el' => 'Όταν αφορά άδεια διαμονής, σας βοηθάμε να βρείτε κατάλληλα ακίνητα και συντονίζουμε την αγορά με εξειδικευμένους νομικούς συνεργάτες, που εξετάζουν την επιλεξιμότητά σας.']),
                'image' => $this->photo('islet'),
                'points' => [],
                'link_text' => $t(['en' => 'Investment & residency support', 'ar' => 'دعم الاستثمار والإقامة', 'el' => 'Υποστήριξη επένδυσης & διαμονής']),
                'link' => 'services',
            ]),
            $this->block('closing_cta', $this->cta($l)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function contact(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            $this->block('page_header', [
                'eyebrow' => '',
                'heading' => $t(['en' => 'Talk to our team', 'ar' => 'تحدّث مع فريقنا', 'el' => 'Μιλήστε με την ομάδα μας']),
                'text' => $t(['en' => 'Whether you are looking for a home, an investment opportunity or a development partner, tell us what you need and we will take the next step with you.',
                    'ar' => 'سواء كنت تبحث عن منزل أو فرصة استثمارية أو شريك في التطوير، أخبرنا بما تحتاج وسنخطو الخطوة التالية معك.',
                    'el' => 'Είτε αναζητάτε κατοικία, επενδυτική ευκαιρία ή συνεργάτη ανάπτυξης, πείτε μας τι χρειάζεστε και θα κάνουμε μαζί το επόμενο βήμα.']),
            ]),
            $this->block('contact_panel', [
                'heading' => '',
                'reply_note' => $t(['en' => 'We reply within one working day.', 'ar' => 'نردّ خلال يوم عمل واحد.', 'el' => 'Απαντάμε εντός μίας εργάσιμης ημέρας.']),
                'privacy_link' => '',
                'map' => '1',
            ]),
            $this->block('link_strip', [
                'text' => $t(['en' => 'Buying from abroad? Start with our property guide.', 'ar' => 'تشتري من الخارج؟ ابدأ بدليل العقارات.', 'el' => 'Αγοράζετε από το εξωτερικό; Ξεκινήστε από τον οδηγό μας.']),
                'link_text' => $t(['en' => 'International buyers guide', 'ar' => 'دليل المشترين الدوليين', 'el' => 'Οδηγός διεθνών αγοραστών']),
                'link' => 'international-buyers',
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function tiles(string $l, bool $home): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            'eyebrow' => $home ? $t(['en' => 'Services', 'ar' => 'الخدمات', 'el' => 'Υπηρεσίες']) : '',
            'heading' => $home ? $t(['en' => 'Real estate and investment services', 'ar' => 'خدمات العقارات والاستثمار', 'el' => 'Υπηρεσίες ακινήτων και επενδύσεων']) : $t(['en' => 'What we do', 'ar' => 'ما نقدّمه', 'el' => 'Τι κάνουμε']),
            'link_text' => $home ? $t(['en' => 'All services', 'ar' => 'كل الخدمات', 'el' => 'Όλες οι υπηρεσίες']) : '',
            'link' => $home ? 'services' : '',
            'items' => [
                $this->item(['image' => $this->photo('hillside'),
                    'title' => $t(['en' => 'Property development & construction', 'ar' => 'تطوير العقارات وبناؤها', 'el' => 'Ανάπτυξη & κατασκευή ακινήτων']),
                    'text' => $t(['en' => 'Private homes and residential buildings with modern design, quality materials and practical living spaces.', 'ar' => 'منازل خاصة ومبانٍ سكنية بتصميم حديث ومواد عالية الجودة ومساحات معيشة عملية.', 'el' => 'Κατοικίες και πολυκατοικίες με σύγχρονο σχεδιασμό, ποιοτικά υλικά και λειτουργικούς χώρους.']),
                    'link_text' => $t(['en' => 'Explore developments', 'ar' => 'تصفّح المشاريع', 'el' => 'Δείτε τα έργα']), 'link' => 'developments']),
                $this->item(['image' => $this->photo('town'),
                    'title' => $t(['en' => 'Property sales & acquisition', 'ar' => 'بيع العقارات وشراؤها', 'el' => 'Πωλήσεις & αγορές ακινήτων']),
                    'text' => $t(['en' => 'From selection and negotiation through to acquisition and completion, for homes and investments alike.', 'ar' => 'من الاختيار والتفاوض حتى الشراء وإتمام الصفقة، للمنازل والاستثمارات على حد سواء.', 'el' => 'Από την επιλογή και τη διαπραγμάτευση έως την αγορά και την ολοκλήρωση, για κατοικία ή επένδυση.']),
                    'link_text' => $t(['en' => 'Find a property', 'ar' => 'ابحث عن عقار', 'el' => 'Βρείτε ακίνητο']), 'link' => 'properties']),
                $this->item(['image' => $this->photo('islet'),
                    'title' => $t(['en' => 'Investment & residency support', 'ar' => 'دعم الاستثمار والإقامة', 'el' => 'Υποστήριξη επένδυσης & διαμονής']),
                    'text' => $t(['en' => 'Suitable opportunities for international investors, with Golden Visa-related purchases coordinated with qualified partners.', 'ar' => 'فرص مناسبة للمستثمرين الدوليين، مع تنسيق عمليات الشراء المتعلقة بالتأشيرة الذهبية مع شركاء مؤهلين.', 'el' => 'Κατάλληλες ευκαιρίες για διεθνείς επενδυτές, με συντονισμό αγορών για Χρυσή Βίζα με εξειδικευμένους συνεργάτες.']),
                    'link_text' => $t(['en' => 'International buyers', 'ar' => 'المشترون الدوليون', 'el' => 'Διεθνείς αγοραστές']), 'link' => 'international-buyers']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function guide(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        $q = fn (array $s): array => $this->item(['question' => $s[$l], 'link' => 'international-buyers']);
        return [
            'eyebrow' => $t(['en' => 'International buyers', 'ar' => 'المشترون الدوليون', 'el' => 'Διεθνείς αγοραστές']),
            'heading' => $t(['en' => 'Buying property in Greece as an international buyer', 'ar' => 'شراء عقار في اليونان كمشترٍ دولي', 'el' => 'Αγορά ακινήτου στην Ελλάδα από το εξωτερικό']),
            'text' => $t(['en' => 'Guidance on ownership, process, costs and tax numbers, coordinated with our qualified legal and professional partners.', 'ar' => 'إرشادات حول الملكية والإجراءات والتكاليف والرقم الضريبي، بالتنسيق مع شركائنا القانونيين والمهنيين المؤهلين.', 'el' => 'Καθοδήγηση για ιδιοκτησία, διαδικασία, κόστη και ΑΦΜ, σε συντονισμό με τους εξειδικευμένους συνεργάτες μας.']),
            'image' => $this->photo('sea'),
            'questions' => [
                $q(['en' => 'Can foreigners buy property in Greece?', 'ar' => 'هل يمكن للأجانب شراء عقار في اليونان؟', 'el' => 'Μπορούν αλλοδαποί να αγοράσουν ακίνητο;']),
                $q(['en' => 'What is the process for buying property?', 'ar' => 'ما هي إجراءات شراء العقار؟', 'el' => 'Ποια είναι η διαδικασία αγοράς;']),
                $q(['en' => 'What costs are involved?', 'ar' => 'ما التكاليف المترتبة؟', 'el' => 'Ποια είναι τα κόστη;']),
                $q(['en' => 'Do I need a Greek tax number?', 'ar' => 'هل أحتاج إلى رقم ضريبي يوناني؟', 'el' => 'Χρειάζομαι ελληνικό ΑΦΜ;']),
                $q(['en' => 'Can I buy property remotely?', 'ar' => 'هل يمكنني الشراء عن بُعد؟', 'el' => 'Μπορώ να αγοράσω εξ αποστάσεως;']),
            ],
            'link_text' => $t(['en' => 'Read the buyers guide', 'ar' => 'اقرأ دليل المشترين', 'el' => 'Διαβάστε τον οδηγό']),
            'link' => 'international-buyers',
        ];
    }

    /** @return array<string, mixed> */
    private function cta(string $l): array
    {
        $t = fn (array $s): string => $s[$l];
        return [
            'heading' => $t(['en' => 'Find the right property in Greece.', 'ar' => 'اعثر على العقار المناسب في اليونان.', 'el' => 'Βρείτε το κατάλληλο ακίνητο στην Ελλάδα.']),
            'text' => $t(['en' => "Whether you're looking for a home, an investment or a development partner, our team can help you take the next step.", 'ar' => 'سواء كنت تبحث عن منزل أو استثمار أو شريك في التطوير، يمكن لفريقنا مساعدتك في الخطوة التالية.', 'el' => 'Είτε αναζητάτε κατοικία, επένδυση ή συνεργάτη ανάπτυξης, η ομάδα μας μπορεί να σας βοηθήσει στο επόμενο βήμα.']),
            'button' => $t(['en' => 'Explore properties', 'ar' => 'تصفّح العقارات', 'el' => 'Δείτε τα ακίνητα']),
            'link' => 'properties',
            'button2' => $t(['en' => 'Talk to our team', 'ar' => 'تحدّث مع فريقنا', 'el' => 'Μιλήστε μαζί μας']),
            'link2' => 'contact',
        ];
    }

    /** @param list<array{0: array<string,string>, 1: array<string,string>}> $pairs @return list<array<string,string>> */
    private function faqItems(string $l, array $pairs): array
    {
        return array_map(fn (array $p): array => $this->item(['question' => $p[0][$l], 'answer' => $p[1][$l]]), $pairs);
    }

    /** @return array<string, string>|null */
    /** Slide buttons (the panel's Slider) need the full path: "/en/properties" (the site sits at the domain root). */
    private function slide(string $photo, string $heading, string $text, string $button, string $link): ?array
    {
        $media = $this->photo($photo);
        if ($media === '') {
            return null; // a slide needs a picture
        }
        return $this->item(['media' => $media, 'heading' => $heading, 'text' => $text, 'button' => $button, 'link' => $link, 'position' => 'middle-start', 'tone' => 'light', 'shown' => '1']);
    }

    /** @param array<string, mixed> $data @param array<string, string> $settings */
    private function block(string $type, array $data, array $settings = []): array
    {
        $node = ['id' => 'b' . substr(md5($type . (++$this->seq)), 0, 10), 'type' => $type, 'data' => $data];
        if ($settings !== []) {
            $node['settings'] = $settings;
        }
        return $node;
    }

    /** @param array<string, mixed> $fields */
    private function item(array $fields): array
    {
        return ['_key' => 'k' . substr(md5(json_encode($fields) . (++$this->seq)), 0, 10)] + $fields;
    }

    private function photo(string $name): string
    {
        return $this->media[$name] ?? '';
    }

    /** @return array<string, object> English slug => page row (id, created_at, updated_at) */
    private function existingSlugs(Database $db): array
    {
        $slugs = [];
        foreach ($db->table('admin_pages')->select(['id', 'slug', 'created_at', 'updated_at'])->get() as $row) {
            $slug = json_decode((string) ($row->slug ?? ''), true);
            $slugs[is_array($slug) ? (string) ($slug['en'] ?? reset($slug)) : (string) ($row->slug ?? '')] = $row;
        }
        return $slugs;
    }

    /**
     * Copies the sample photos into the media folder and the media library
     * (once), and returns their ids by name. No library: pages without photos.
     *
     * @return array<string, string>
     */
    private function samplePhotos(Database $db): array
    {
        $root = dirname(__DIR__, 2);
        $config = is_file($root . '/config/admin.php') ? (require $root . '/config/admin.php') : [];
        $library = $config['media_library'] ?? true;
        if ($library === false || (is_array($library) && ($library['enabled'] ?? true) === false)) {
            return [];
        }
        $table = is_array($library) ? (string) ($library['table'] ?? 'media') : 'media';
        $mediaRoot = rtrim((string) ($config['media']['root'] ?? 'public/uploads/editor'), '/');
        $folder = (str_starts_with($mediaRoot, '/') ? $mediaRoot : $root . '/' . $mediaRoot) . '/samples';
        try {
            $db->table($table)->count();
        } catch (\Throwable) {
            return [];
        }
        if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
            return [];
        }
        $ids = [];
        $now = date('Y-m-d H:i:s');
        foreach (glob(__DIR__ . '/samples/sample-*.jpg') ?: [] as $source) {
            $name = substr(basename($source, '.jpg'), 7);
            $path = 'samples/' . basename($source);
            $found = $db->table($table)->where('path', '=', $path)->select(['id'])->get();
            if ($found !== [] && isset($found[0]->id)) {
                $ids[$name] = (string) $found[0]->id;
                continue;
            }
            if (!is_file($folder . '/' . basename($source)) && !@copy($source, $folder . '/' . basename($source))) {
                continue;
            }
            $size = @getimagesize($source);
            $ids[$name] = (string) $db->table($table)->insert([
                'kind' => 'image', 'path' => $path, 'thumb_path' => null, 'mime' => 'image/jpeg',
                'bytes' => (int) filesize($source), 'width' => is_array($size) ? (int) $size[0] : null, 'height' => is_array($size) ? (int) $size[1] : null,
                'file_name' => basename($source), 'hash' => hash_file('sha256', $source) ?: null,
                'title' => 'SAMPLE – replace with a company photo', 'alt' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return $ids;
    }
}
