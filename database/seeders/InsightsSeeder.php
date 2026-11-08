<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

require_once __DIR__ . '/SamplePhotos.php';

/**
 * Sample Insights articles for testing the Insights pages: eight published
 * articles in English, Arabic and Greek, two per category, each with a sample
 * cover photo; the newest market article is featured.
 *
 *     php flo db:seed --class=InsightsSeeder
 *
 * Every slug ends in "-sample" and every article ends with a "Sample article"
 * line: delete them in Admin › Insights (search "sample") before launch. The
 * texts are placeholders, not advice: nothing here has been reviewed by the
 * legal partners. Safe to run again: an article whose slug exists is skipped.
 */
final class InsightsSeeder extends Seeder
{
    use SamplePhotos;

    public function run(Database $db): void
    {
        $photos = $this->samplePhotos($db);
        $now = date('Y-m-d H:i:s');
        $note = [
            'en' => '<p><em>Sample article for testing. Replace it with the company\'s own content before launch.</em></p>',
            'ar' => '<p><em>مقال تجريبي للاختبار. استبدله بمحتوى الشركة قبل الإطلاق.</em></p>',
            'el' => '<p><em>Δείγμα άρθρου για δοκιμή. Αντικαταστήστε το με περιεχόμενο της εταιρείας πριν από την έναρξη.</em></p>',
        ];

        foreach ($this->articles() as [$slug, $category, $date, $photo, $featured, $title, $excerpt, $body]) {
            if ($db->table('articles')->where('slug', '=', $slug)->count() > 0) {
                continue;
            }
            $cover = isset($photos[$photo]) ? (int) $photos[$photo] : null;
            foreach ($body as $lang => $html) {
                $body[$lang] = $html . $note[$lang];
            }
            $db->table('articles')->insert([
                'title' => $title,
                'slug' => $slug,
                'category' => $category,
                'excerpt' => $excerpt,
                'body' => $body,
                'cover_id' => $cover,
                'published_at' => $date . ' 09:00:00',
                'featured' => $featured,
                'publish_status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return list<array{string, string, string, string, int, array<string, string>, array<string, string>, array<string, string>}> */
    private function articles(): array
    {
        return [
            ['athens-market-autumn-sample', 'market_insights', '2026-11-03', 'roofs', 1,
                [
                    'en' => 'Athens property market: what buyers are watching this autumn',
                    'ar' => 'سوق العقارات في أثينا: ما الذي يتابعه المشترون هذا الخريف',
                    'el' => 'Η αγορά ακινήτων της Αθήνας: τι παρακολουθούν οι αγοραστές αυτό το φθινόπωρο',
                ],
                [
                    'en' => 'Demand for renovated city apartments and new energy-efficient homes remains strong. Here is what our team hears from buyers.',
                    'ar' => 'يبقى الطلب قوياً على الشقق المجددة في المدينة والمنازل الجديدة الموفرة للطاقة. إليك ما يسمعه فريقنا من المشترين.',
                    'el' => 'Η ζήτηση για ανακαινισμένα διαμερίσματα στην πόλη και νέες ενεργειακά αποδοτικές κατοικίες παραμένει ισχυρή. Δείτε τι ακούει η ομάδα μας από τους αγοραστές.',
                ],
                [
                    'en' => '<p>Central Athens continues to attract both local families and international buyers. Well-located apartments close to metro stations and neighbourhood amenities tend to sell quickly, while larger homes in the southern suburbs appeal to buyers looking for more space and outdoor areas.</p><h2>What buyers ask about most</h2><ul><li>Energy class and running costs</li><li>Parking and storage</li><li>Completion dates for new developments</li></ul><p>For new buildings, buyers increasingly compare specifications such as insulation, heat pumps and smart-home features alongside the price per square metre.</p>',
                    'ar' => '<p>لا يزال وسط أثينا يجذب العائلات المحلية والمشترين الدوليين على حد سواء. الشقق ذات المواقع الجيدة القريبة من محطات المترو والخدمات تُباع بسرعة عادةً، بينما تجذب المنازل الأكبر في الضواحي الجنوبية من يبحثون عن مساحة أكبر ومساحات خارجية.</p><h2>أكثر ما يسأل عنه المشترون</h2><ul><li>فئة الطاقة وتكاليف التشغيل</li><li>موقف السيارات والمخزن</li><li>مواعيد تسليم المشاريع الجديدة</li></ul><p>في المباني الجديدة، يقارن المشترون بشكل متزايد بين المواصفات مثل العزل والمضخات الحرارية وأنظمة المنزل الذكي إلى جانب سعر المتر المربع.</p>',
                    'el' => '<p>Το κέντρο της Αθήνας συνεχίζει να προσελκύει τόσο ντόπιες οικογένειες όσο και διεθνείς αγοραστές. Τα καλά τοποθετημένα διαμερίσματα κοντά σε σταθμούς μετρό και υπηρεσίες πωλούνται συνήθως γρήγορα, ενώ οι μεγαλύτερες κατοικίες στα νότια προάστια ελκύουν όσους αναζητούν περισσότερο χώρο και εξωτερικούς χώρους.</p><h2>Τι ρωτούν περισσότερο οι αγοραστές</h2><ul><li>Ενεργειακή κλάση και κόστος λειτουργίας</li><li>Στάθμευση και αποθήκη</li><li>Ημερομηνίες παράδοσης νέων έργων</li></ul><p>Στα νέα κτίρια, οι αγοραστές συγκρίνουν όλο και περισσότερο προδιαγραφές όπως η θερμομόνωση, οι αντλίες θερμότητας και τα έξυπνα συστήματα, μαζί με την τιμή ανά τετραγωνικό μέτρο.</p>',
                ]],
            ['buying-property-step-by-step-sample', 'buying_guide', '2026-10-27', 'town', 0,
                [
                    'en' => 'Buying property in Greece, step by step',
                    'ar' => 'شراء عقار في اليونان خطوة بخطوة',
                    'el' => 'Αγορά ακινήτου στην Ελλάδα, βήμα προς βήμα',
                ],
                [
                    'en' => 'From the first viewing to the signing at the notary: an overview of the usual steps of a purchase.',
                    'ar' => 'من المعاينة الأولى حتى التوقيع لدى كاتب العدل: نظرة عامة على الخطوات المعتادة لعملية الشراء.',
                    'el' => 'Από την πρώτη επίσκεψη έως την υπογραφή στον συμβολαιογράφο: μια επισκόπηση των συνηθισμένων βημάτων μιας αγοράς.',
                ],
                [
                    'en' => '<p>Every purchase is different, but most follow a similar path. Knowing the steps in advance helps you plan your time and budget.</p><h2>The usual steps</h2><ul><li>Choose the property and agree on the price</li><li>Obtain a tax number and open a bank account</li><li>Legal and technical checks by your lawyer and engineer</li><li>Sign the contract before a notary and register the property</li></ul><p>Our team coordinates each step with qualified legal and technical partners, so you always know what comes next.</p>',
                    'ar' => '<p>كل عملية شراء مختلفة، لكن معظمها يتبع مساراً متشابهاً. معرفة الخطوات مسبقاً تساعدك على تنظيم وقتك وميزانيتك.</p><h2>الخطوات المعتادة</h2><ul><li>اختيار العقار والاتفاق على السعر</li><li>الحصول على رقم ضريبي وفتح حساب مصرفي</li><li>الفحوص القانونية والفنية من قبل المحامي والمهندس</li><li>توقيع العقد أمام كاتب العدل وتسجيل العقار</li></ul><p>ينسق فريقنا كل خطوة مع شركاء قانونيين وفنيين مؤهلين، لتعرف دائماً ما هي الخطوة التالية.</p>',
                    'el' => '<p>Κάθε αγορά είναι διαφορετική, αλλά οι περισσότερες ακολουθούν παρόμοια πορεία. Γνωρίζοντας τα βήματα εκ των προτέρων, οργανώνετε καλύτερα τον χρόνο και τον προϋπολογισμό σας.</p><h2>Τα συνηθισμένα βήματα</h2><ul><li>Επιλογή ακινήτου και συμφωνία τιμής</li><li>Έκδοση ΑΦΜ και άνοιγμα τραπεζικού λογαριασμού</li><li>Νομικός και τεχνικός έλεγχος από δικηγόρο και μηχανικό</li><li>Υπογραφή συμβολαίου στον συμβολαιογράφο και μεταγραφή</li></ul><p>Η ομάδα μας συντονίζει κάθε βήμα με εξειδικευμένους νομικούς και τεχνικούς συνεργάτες, ώστε να γνωρίζετε πάντα το επόμενο βήμα.</p>',
                ]],
            ['golden-visa-what-to-check-sample', 'golden_visa', '2026-10-20', 'sea', 0,
                [
                    'en' => 'Golden Visa and property: what to check before you buy',
                    'ar' => 'الإقامة الذهبية والعقار: ما الذي يجب التحقق منه قبل الشراء',
                    'el' => 'Golden Visa και ακίνητα: τι να ελέγξετε πριν αγοράσετε',
                ],
                [
                    'en' => 'Investors considering residency through property should confirm eligibility with qualified legal partners before choosing a home.',
                    'ar' => 'ينبغي للمستثمرين الذين يفكرون في الإقامة عبر العقار التأكد من الأهلية مع شركاء قانونيين مؤهلين قبل اختيار المنزل.',
                    'el' => 'Οι επενδυτές που εξετάζουν άδεια διαμονής μέσω ακινήτου πρέπει να επιβεβαιώνουν την επιλεξιμότητα με εξειδικευμένους νομικούς συνεργάτες πριν επιλέξουν κατοικία.',
                ],
                [
                    'en' => '<p>The rules for residency through real estate investment depend on the location, the type of property and the date of purchase, and they change from time to time. That is why every case starts with a review by a qualified lawyer.</p><h2>Questions to clear first</h2><ul><li>Does the property qualify in its area?</li><li>Which documents will the application need?</li><li>Can family members be included?</li></ul><p>FECTUM Hellas helps investors find suitable properties and coordinates the purchase with legal partners who advise on the residency side.</p>',
                    'ar' => '<p>تعتمد قواعد الإقامة عبر الاستثمار العقاري على الموقع ونوع العقار وتاريخ الشراء، وهي تتغير من وقت لآخر. لهذا تبدأ كل حالة بمراجعة من محامٍ مؤهل.</p><h2>أسئلة يجب حسمها أولاً</h2><ul><li>هل العقار مؤهل في منطقته؟</li><li>ما المستندات التي يحتاجها الطلب؟</li><li>هل يمكن إدراج أفراد العائلة؟</li></ul><p>تساعد FECTUM Hellas المستثمرين في العثور على العقارات المناسبة وتنسق عملية الشراء مع شركاء قانونيين يقدمون المشورة بشأن الإقامة.</p>',
                    'el' => '<p>Οι κανόνες για άδεια διαμονής μέσω επένδυσης σε ακίνητα εξαρτώνται από την περιοχή, τον τύπο του ακινήτου και την ημερομηνία αγοράς, και αλλάζουν κατά καιρούς. Γι\' αυτό κάθε περίπτωση ξεκινά με έλεγχο από εξειδικευμένο δικηγόρο.</p><h2>Ερωτήσεις που πρέπει να απαντηθούν πρώτα</h2><ul><li>Πληροί το ακίνητο τις προϋποθέσεις στην περιοχή του;</li><li>Ποια έγγραφα θα χρειαστεί η αίτηση;</li><li>Μπορούν να συμπεριληφθούν μέλη της οικογένειας;</li></ul><p>Η FECTUM Hellas βοηθά τους επενδυτές να βρουν κατάλληλα ακίνητα και συντονίζει την αγορά με νομικούς συνεργάτες που συμβουλεύουν για τη διαμονή.</p>',
                ]],
            ['residence-one-third-floor-sample', 'company_news', '2026-10-16', 'hillside', 0,
                [
                    'en' => 'Residence One reaches the third floor',
                    'ar' => 'ريزيدنس ون يصل إلى الطابق الثالث',
                    'el' => 'Το Residence One έφτασε στον τρίτο όροφο',
                ],
                [
                    'en' => 'Construction of our Glyfada development is on schedule, with the third floor slab now poured.',
                    'ar' => 'يسير البناء في مشروعنا في غليفادا وفق الجدول الزمني، وقد تم صب سقف الطابق الثالث.',
                    'el' => 'Η κατασκευή του έργου μας στη Γλυφάδα προχωρά σύμφωνα με το χρονοδιάγραμμα, με τη σκυροδέτηση της πλάκας του τρίτου ορόφου.',
                ],
                [
                    'en' => '<p>Work on Residence One continues as planned. After the foundations were completed in the spring, the structure has now reached the third floor.</p><h2>Next on site</h2><ul><li>Upper floors and roof garden structure</li><li>Window frames and insulation</li><li>Interior finishes from mid-2027</li></ul><p>A limited number of residences is still available. Contact our team for floor plans and a site visit.</p>',
                    'ar' => '<p>تستمر الأعمال في ريزيدنس ون كما هو مخطط. بعد اكتمال الأساسات في الربيع، وصل الهيكل الآن إلى الطابق الثالث.</p><h2>المرحلة التالية في الموقع</h2><ul><li>الطوابق العليا وهيكل حديقة السطح</li><li>إطارات النوافذ والعزل</li><li>التشطيبات الداخلية من منتصف 2027</li></ul><p>لا يزال عدد محدود من الوحدات السكنية متاحاً. تواصل مع فريقنا للحصول على المخططات وزيارة الموقع.</p>',
                    'el' => '<p>Οι εργασίες στο Residence One συνεχίζονται όπως προγραμματίστηκε. Μετά την ολοκλήρωση της θεμελίωσης την άνοιξη, ο φέρων οργανισμός έφτασε πλέον στον τρίτο όροφο.</p><h2>Τα επόμενα στο εργοτάξιο</h2><ul><li>Οι ανώτεροι όροφοι και η κατασκευή του κήπου στην ταράτσα</li><li>Κουφώματα και θερμομόνωση</li><li>Εσωτερικά τελειώματα από τα μέσα του 2027</li></ul><p>Περιορισμένος αριθμός κατοικιών είναι ακόμη διαθέσιμος. Επικοινωνήστε με την ομάδα μας για κατόψεις και επίσκεψη στο εργοτάξιο.</p>',
                ]],
            ['athens-riviera-coastal-living-sample', 'market_insights', '2026-10-08', 'coast', 0,
                [
                    'en' => 'Coastal living on the Athens Riviera',
                    'ar' => 'الحياة الساحلية على ريفييرا أثينا',
                    'el' => 'Ζωή δίπλα στη θάλασσα στην Αθηναϊκή Ριβιέρα',
                ],
                [
                    'en' => 'Glyfada, Voula and Vouliagmeni combine beaches, marinas and quick access to the city. A look at what makes the area popular.',
                    'ar' => 'تجمع غليفادا وفولا وفولياغميني بين الشواطئ والمراسي وسهولة الوصول إلى المدينة. نظرة على ما يجعل المنطقة مرغوبة.',
                    'el' => 'Η Γλυφάδα, η Βούλα και η Βουλιαγμένη συνδυάζουν παραλίες, μαρίνες και γρήγορη πρόσβαση στην πόλη. Τι κάνει την περιοχή τόσο δημοφιλή.',
                ],
                [
                    'en' => '<p>The coastline south of Athens offers a rare mix: sea views and an active lifestyle, while the city centre and the airport remain within easy reach.</p><h2>Why buyers choose the Riviera</h2><ul><li>Beaches, marinas and sports clubs</li><li>International schools and services nearby</li><li>New residential developments with modern specifications</li></ul><p>Supply of new homes close to the sea is limited, so well-designed properties in the area tend to hold their value.</p>',
                    'ar' => '<p>يوفر الساحل جنوب أثينا مزيجاً نادراً: إطلالات بحرية وأسلوب حياة نشط، مع بقاء وسط المدينة والمطار على مسافة قريبة.</p><h2>لماذا يختار المشترون الريفييرا</h2><ul><li>الشواطئ والمراسي والنوادي الرياضية</li><li>مدارس دولية وخدمات قريبة</li><li>مشاريع سكنية جديدة بمواصفات حديثة</li></ul><p>المعروض من المنازل الجديدة القريبة من البحر محدود، لذلك تحافظ العقارات المصممة جيداً في المنطقة على قيمتها عادةً.</p>',
                    'el' => '<p>Η ακτογραμμή νότια της Αθήνας προσφέρει έναν σπάνιο συνδυασμό: θέα στη θάλασσα και ενεργό τρόπο ζωής, ενώ το κέντρο της πόλης και το αεροδρόμιο παραμένουν κοντά.</p><h2>Γιατί οι αγοραστές επιλέγουν τη Ριβιέρα</h2><ul><li>Παραλίες, μαρίνες και αθλητικοί όμιλοι</li><li>Διεθνή σχολεία και υπηρεσίες σε κοντινή απόσταση</li><li>Νέα οικιστικά έργα με σύγχρονες προδιαγραφές</li></ul><p>Η προσφορά νέων κατοικιών κοντά στη θάλασσα είναι περιορισμένη, γι\' αυτό τα καλά σχεδιασμένα ακίνητα της περιοχής διατηρούν συνήθως την αξία τους.</p>',
                ]],
            ['costs-of-buying-explained-sample', 'buying_guide', '2026-09-29', 'harbour', 0,
                [
                    'en' => 'The costs of buying a home in Greece, explained',
                    'ar' => 'شرح تكاليف شراء منزل في اليونان',
                    'el' => 'Τα έξοδα αγοράς κατοικίας στην Ελλάδα, με απλά λόγια',
                ],
                [
                    'en' => 'Besides the price, a purchase comes with taxes and professional fees. Here are the usual cost items to budget for.',
                    'ar' => 'إلى جانب السعر، تترتب على الشراء ضرائب وأتعاب مهنية. إليك بنود التكاليف المعتادة التي يجب أخذها في الحسبان.',
                    'el' => 'Πέρα από την τιμή, μια αγορά συνοδεύεται από φόρους και αμοιβές επαγγελματιών. Αυτά είναι τα συνηθισμένα έξοδα που πρέπει να υπολογίσετε.',
                ],
                [
                    'en' => '<p>When you plan your budget, allow for the costs that come on top of the purchase price. The exact amounts depend on the property and the transaction, so ask for an estimate early.</p><h2>Typical cost items</h2><ul><li>Transfer tax or VAT, depending on the property</li><li>Notary and land registry fees</li><li>Lawyer and engineer fees</li></ul><p>Our team prepares a cost estimate for each property you consider, so there are no surprises at signing.</p>',
                    'ar' => '<p>عند التخطيط لميزانيتك، احسب التكاليف التي تضاف إلى سعر الشراء. تعتمد المبالغ الدقيقة على العقار والصفقة، لذا اطلب تقديراً مبكراً.</p><h2>بنود التكاليف المعتادة</h2><ul><li>ضريبة نقل الملكية أو ضريبة القيمة المضافة حسب العقار</li><li>رسوم كاتب العدل والسجل العقاري</li><li>أتعاب المحامي والمهندس</li></ul><p>يعد فريقنا تقديراً للتكاليف لكل عقار تفكر فيه، حتى لا تكون هناك مفاجآت عند التوقيع.</p>',
                    'el' => '<p>Όταν σχεδιάζετε τον προϋπολογισμό σας, υπολογίστε τα έξοδα που προστίθενται στην τιμή αγοράς. Τα ακριβή ποσά εξαρτώνται από το ακίνητο και τη συναλλαγή, γι\' αυτό ζητήστε εκτίμηση νωρίς.</p><h2>Συνηθισμένα έξοδα</h2><ul><li>Φόρος μεταβίβασης ή ΦΠΑ, ανάλογα με το ακίνητο</li><li>Αμοιβή συμβολαιογράφου και τέλη κτηματολογίου</li><li>Αμοιβές δικηγόρου και μηχανικού</li></ul><p>Η ομάδα μας ετοιμάζει εκτίμηση εξόδων για κάθε ακίνητο που εξετάζετε, ώστε να μην υπάρχουν εκπλήξεις στην υπογραφή.</p>',
                ]],
            ['new-website-three-languages-sample', 'company_news', '2026-09-22', 'bay', 0,
                [
                    'en' => 'Our new website, now in English, Arabic and Greek',
                    'ar' => 'موقعنا الجديد متاح الآن بالإنجليزية والعربية واليونانية',
                    'el' => 'Ο νέος μας ιστότοπος, τώρα στα Αγγλικά, Αραβικά και Ελληνικά',
                ],
                [
                    'en' => 'Search properties, follow our developments and save your favourites, in the language you prefer.',
                    'ar' => 'ابحث عن العقارات، وتابع مشاريعنا، واحفظ ما يعجبك، باللغة التي تفضلها.',
                    'el' => 'Αναζητήστε ακίνητα, παρακολουθήστε τα έργα μας και αποθηκεύστε τα αγαπημένα σας, στη γλώσσα που προτιμάτε.',
                ],
                [
                    'en' => '<p>We have redesigned our website to make finding the right property in Greece simpler for clients at home and abroad.</p><h2>What is new</h2><ul><li>Property search with filters by city, type, bedrooms and price</li><li>Saved properties and side-by-side comparison</li><li>Construction progress for each development</li></ul><p>Have a question? Send us a message from any property page and our team will reply.</p>',
                    'ar' => '<p>أعدنا تصميم موقعنا لتسهيل العثور على العقار المناسب في اليونان على العملاء داخل البلاد وخارجها.</p><h2>ما الجديد</h2><ul><li>البحث عن العقارات مع فلاتر حسب المدينة والنوع وعدد غرف النوم والسعر</li><li>حفظ العقارات ومقارنتها جنباً إلى جنب</li><li>متابعة تقدم البناء في كل مشروع</li></ul><p>هل لديك سؤال؟ أرسل لنا رسالة من صفحة أي عقار وسيرد عليك فريقنا.</p>',
                    'el' => '<p>Ανασχεδιάσαμε τον ιστότοπό μας ώστε η αναζήτηση του κατάλληλου ακινήτου στην Ελλάδα να είναι πιο απλή για πελάτες εντός και εκτός χώρας.</p><h2>Τι νέο υπάρχει</h2><ul><li>Αναζήτηση ακινήτων με φίλτρα για πόλη, τύπο, υπνοδωμάτια και τιμή</li><li>Αποθήκευση και σύγκριση ακινήτων</li><li>Πρόοδος κατασκευής για κάθε έργο</li></ul><p>Έχετε ερώτηση; Στείλτε μας μήνυμα από τη σελίδα οποιουδήποτε ακινήτου και η ομάδα μας θα σας απαντήσει.</p>',
                ]],
            ['residency-documents-checklist-sample', 'golden_visa', '2026-09-15', 'islet', 0,
                [
                    'en' => 'Residency through property: documents investors usually prepare',
                    'ar' => 'الإقامة عبر العقار: المستندات التي يجهزها المستثمرون عادةً',
                    'el' => 'Διαμονή μέσω ακινήτου: τα έγγραφα που ετοιμάζουν συνήθως οι επενδυτές',
                ],
                [
                    'en' => 'Preparing your paperwork early saves time. A general checklist to discuss with your lawyer.',
                    'ar' => 'تجهيز أوراقك مبكراً يوفر الوقت. قائمة عامة لمناقشتها مع محاميك.',
                    'el' => 'Η έγκαιρη προετοιμασία των εγγράφων εξοικονομεί χρόνο. Μια γενική λίστα για να τη συζητήσετε με τον δικηγόρο σας.',
                ],
                [
                    'en' => '<p>The exact list depends on your nationality and your family situation, and your lawyer will confirm it. Most investors start gathering the following documents early.</p><h2>A general checklist</h2><ul><li>Valid passports for every applicant</li><li>Proof of the property purchase</li><li>Health insurance and, where needed, translated certificates</li></ul><p>Our partners review each file before it is submitted, and our team keeps you updated on the property side.</p>',
                    'ar' => '<p>تعتمد القائمة الدقيقة على جنسيتك ووضعك العائلي، وسيؤكدها محاميك. يبدأ معظم المستثمرين بجمع المستندات التالية مبكراً.</p><h2>قائمة عامة</h2><ul><li>جوازات سفر سارية لكل مقدم طلب</li><li>إثبات شراء العقار</li><li>تأمين صحي وشهادات مترجمة عند الحاجة</li></ul><p>يراجع شركاؤنا كل ملف قبل تقديمه، ويبقيك فريقنا على اطلاع بكل ما يخص العقار.</p>',
                    'el' => '<p>Η ακριβής λίστα εξαρτάται από την υπηκοότητα και την οικογενειακή σας κατάσταση, και θα την επιβεβαιώσει ο δικηγόρος σας. Οι περισσότεροι επενδυτές αρχίζουν νωρίς να συγκεντρώνουν τα παρακάτω.</p><h2>Μια γενική λίστα</h2><ul><li>Έγκυρα διαβατήρια για κάθε αιτούντα</li><li>Αποδεικτικό αγοράς του ακινήτου</li><li>Ασφάλιση υγείας και, όπου χρειάζεται, μεταφρασμένα πιστοποιητικά</li></ul><p>Οι συνεργάτες μας ελέγχουν κάθε φάκελο πριν από την υποβολή και η ομάδα μας σας ενημερώνει για ό,τι αφορά το ακίνητο.</p>',
                ]],
        ];
    }
}
