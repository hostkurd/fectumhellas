<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

/**
 * Sample content for testing and screenshots (design note D14): six listings,
 * the development Residence One with three residences, stages and two
 * progress updates, one article, two team members and one inquiry.
 *
 *     php flo db:seed --class=DemoSeeder
 *
 * Never run it on the live site: every record says SAMPLE in its Property ID
 * or text and must be replaced by real content. Runs only on an empty
 * listings table. Photos come from the media library later.
 */
final class DemoSeeder extends Seeder
{
    public function run(Database $db): void
    {
        $this->call(LookupsSeeder::class);
        if ($db->table('listings')->count() > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $city = fn (string $en): ?int => $this->idByName($db, 'cities', $en);
        $type = fn (string $en): ?int => $this->idByName($db, 'property_types', $en);

        $projectId = $db->table('projects')->insert([
            'title' => ['en' => 'Residence One', 'ar' => 'ريزيدنس ون', 'el' => 'Residence One'],
            'slug' => 'residence-one',
            'summary' => [
                'en' => 'Sample development: twelve contemporary residences near the Glyfada coast.',
                'ar' => 'مشروع تجريبي: اثنتا عشرة وحدة سكنية عصرية قرب ساحل غليفادا.',
                'el' => 'Δείγμα έργου: δώδεκα σύγχρονες κατοικίες κοντά στην ακτή της Γλυφάδας.',
            ],
            'description' => [
                'en' => '<p>Sample text. Residence One is a six-storey building with twelve residences, private parking and a shared roof garden.</p>',
                'ar' => '<p>نص تجريبي. ريزيدنس ون مبنى من ستة طوابق يضم اثنتي عشرة وحدة سكنية وموقفاً خاصاً وحديقة مشتركة على السطح.</p>',
                'el' => '<p>Δείγμα κειμένου. Το Residence One είναι εξαώροφο κτίριο με δώδεκα κατοικίες, ιδιωτική στάθμευση και κοινόχρηστο κήπο στην ταράτσα.</p>',
            ],
            'city_id' => $city('Glyfada'),
            'area' => ['en' => 'Glyfada centre', 'ar' => 'وسط غليفادا', 'el' => 'Κέντρο Γλυφάδας'],
            'latitude' => '37.8640000',
            'longitude' => '23.7530000',
            'progress' => 'under_construction',
            'progress_percent' => 45,
            'expected_completion' => ['en' => 'Q4 2027', 'ar' => 'الربع الرابع 2027', 'el' => 'Δ΄ τρίμηνο 2027'],
            'stages' => [
                ['_key' => 'kstage0001', 'name' => ['en' => 'Permits', 'ar' => 'التراخيص', 'el' => 'Άδειες'], 'status' => 'completed', 'date' => ['en' => 'Jan 2026', 'ar' => 'يناير 2026', 'el' => 'Ιαν 2026']],
                ['_key' => 'kstage0002', 'name' => ['en' => 'Foundations', 'ar' => 'الأساسات', 'el' => 'Θεμελίωση'], 'status' => 'completed', 'date' => ['en' => 'Apr 2026', 'ar' => 'أبريل 2026', 'el' => 'Απρ 2026']],
                ['_key' => 'kstage0003', 'name' => ['en' => 'Structure', 'ar' => 'الهيكل الإنشائي', 'el' => 'Φέρων οργανισμός'], 'status' => 'in_progress', 'date' => ['en' => 'Dec 2026', 'ar' => 'ديسمبر 2026', 'el' => 'Δεκ 2026']],
                ['_key' => 'kstage0004', 'name' => ['en' => 'Finishes', 'ar' => 'التشطيبات', 'el' => 'Τελειώματα'], 'status' => 'upcoming', 'date' => ['en' => 'Jun 2027', 'ar' => 'يونيو 2027', 'el' => 'Ιουν 2027']],
                ['_key' => 'kstage0005', 'name' => ['en' => 'Handover', 'ar' => 'التسليم', 'el' => 'Παράδοση'], 'status' => 'upcoming', 'date' => ['en' => 'Q4 2027', 'ar' => 'الربع الرابع 2027', 'el' => 'Δ΄ τρίμ. 2027']],
            ],
            'key_facts' => [
                ['key' => 'Floors', 'value' => ['en' => '6', 'ar' => '6', 'el' => '6']],
                ['key' => 'Residences', 'value' => ['en' => '12', 'ar' => '12', 'el' => '12']],
                ['key' => 'Energy class', 'value' => ['en' => 'A+', 'ar' => 'A+', 'el' => 'Α+']],
            ],
            'publish_status' => 'published',
            'featured' => 1,
            'sort' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['2026-04-20', ['en' => 'Foundations completed', 'ar' => 'اكتمال الأساسات', 'el' => 'Ολοκλήρωση θεμελίωσης'],
                ['en' => 'Sample update. The foundations and basement are complete.', 'ar' => 'تحديث تجريبي. اكتملت الأساسات والطابق السفلي.', 'el' => 'Δείγμα ενημέρωσης. Η θεμελίωση και το υπόγειο ολοκληρώθηκαν.']],
            ['2026-10-15', ['en' => 'Third floor slab poured', 'ar' => 'صب سقف الطابق الثالث', 'el' => 'Σκυροδέτηση πλάκας τρίτου ορόφου'],
                ['en' => 'Sample update. The structure has reached the third floor.', 'ar' => 'تحديث تجريبي. وصل الهيكل إلى الطابق الثالث.', 'el' => 'Δείγμα ενημέρωσης. Ο φέρων οργανισμός έφτασε στον τρίτο όροφο.']],
        ] as [$date, $title, $body]) {
            $db->table('project_updates')->insert([
                'project_id' => $projectId, 'update_date' => $date, 'title' => $title, 'body' => $body,
                'publish_status' => 'published', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $listings = [
            ['SAMPLE-GR54-00', 'duplex-athens-sample', ['en' => 'Contemporary duplex in Athens', 'ar' => 'دوبلكس عصري في أثينا', 'el' => 'Σύγχρονο διώροφο στην Αθήνα'],
                'for_sale', 1200000, 'Duplex', 'Athens', ['en' => 'Kolonaki', 'ar' => 'كولوناكي', 'el' => 'Κολωνάκι'], 3, 2, 176, 24, ['en' => '4th–5th', 'ar' => 'الرابع والخامس', 'el' => '4ος–5ος'], 1, 'A', 2026, 1, null, 1],
            ['SAMPLE-GR11-02', 'sea-view-villa-sample', ['en' => 'Sea view villa in Vouliagmeni', 'ar' => 'فيلا بإطلالة بحرية في فولياغميني', 'el' => 'Βίλα με θέα θάλασσα στη Βουλιαγμένη'],
                'for_sale', 3400000, 'Villa', 'Vouliagmeni', ['en' => 'Kavouri', 'ar' => 'كافوري', 'el' => 'Καβούρι'], 5, 4, 420, 650, ['en' => 'Ground + 2', 'ar' => 'أرضي + 2', 'el' => 'Ισόγειο + 2'], 3, 'B+', 2019, 0, null, 1],
            ['SAMPLE-GR20-07', 'kolonaki-apartment-rent-sample', ['en' => 'Bright apartment for rent near Syntagma', 'ar' => 'شقة مشرقة للإيجار قرب ساحة سينتاغما', 'el' => 'Φωτεινό διαμέρισμα προς ενοικίαση κοντά στο Σύνταγμα'],
                'for_rent', 1800, 'Apartment', 'Athens', ['en' => 'Syntagma', 'ar' => 'سينتاغما', 'el' => 'Σύνταγμα'], 2, 1, 92, 8, ['en' => '3rd', 'ar' => 'الثالث', 'el' => '3ος'], 0, 'C', 2008, 0, null, 0],
            ['SAMPLE-R1-A1', 'residence-one-a1-sample', ['en' => 'Residence One, apartment A1', 'ar' => 'ريزيدنس ون، شقة A1', 'el' => 'Residence One, διαμέρισμα Α1'],
                'off_plan', 420000, 'Apartment', 'Glyfada', ['en' => 'Glyfada centre', 'ar' => 'وسط غليفادا', 'el' => 'Κέντρο Γλυφάδας'], 2, 1, 84, 12, ['en' => '1st', 'ar' => 'الأول', 'el' => '1ος'], 1, 'A+', 2027, 1, $projectId, 0],
            ['SAMPLE-R1-B3', 'residence-one-b3-sample', ['en' => 'Residence One, apartment B3', 'ar' => 'ريزيدنس ون، شقة B3', 'el' => 'Residence One, διαμέρισμα Β3'],
                'sold', 560000, 'Apartment', 'Glyfada', ['en' => 'Glyfada centre', 'ar' => 'وسط غليفادا', 'el' => 'Κέντρο Γλυφάδας'], 3, 2, 112, 18, ['en' => '3rd', 'ar' => 'الثالث', 'el' => '3ος'], 1, 'A+', 2027, 1, $projectId, 0],
            ['SAMPLE-R1-PH', 'residence-one-penthouse-sample', ['en' => 'Residence One, penthouse', 'ar' => 'ريزيدنس ون، بنتهاوس', 'el' => 'Residence One, ρετιρέ'],
                'off_plan', null, 'Penthouse', 'Glyfada', ['en' => 'Glyfada centre', 'ar' => 'وسط غليفادا', 'el' => 'Κέντρο Γλυφάδας'], 3, 3, 148, 95, ['en' => '6th', 'ar' => 'السادس', 'el' => '6ος'], 2, 'A+', 2027, 1, $projectId, 1],
        ];
        $amenities = $db->table('amenities')->select(['id'])->orderBy('sort')->limit(6)->get();
        foreach ($listings as [$ref, $slug, $title, $status, $price, $typeName, $cityName, $area, $beds, $baths, $m2, $outdoor, $floor, $parking, $energy, $year, $building, $project, $featured]) {
            $id = $db->table('listings')->insert([
                'reference' => $ref,
                'title' => $title,
                'slug' => $slug,
                'project_id' => $project,
                'status' => $status,
                'price' => $price,
                'price_on_request' => $price === null ? 1 : 0,
                'property_type_id' => $type($typeName),
                'bedrooms' => $beds,
                'bathrooms' => $baths,
                'area_m2' => $m2,
                'outdoor_m2' => $outdoor,
                'floor' => $floor,
                'parking' => $parking,
                'energy_class' => $energy,
                'construction_year' => $year,
                'under_construction' => $building,
                'city_id' => $city($cityName),
                'area' => $area,
                'summary' => [
                    'en' => 'Sample listing for testing. Replace with a real property.',
                    'ar' => 'عقار تجريبي للاختبار. استبدله بعقار حقيقي.',
                    'el' => 'Δείγμα αγγελίας για δοκιμή. Αντικαταστήστε το με πραγματικό ακίνητο.',
                ],
                'description' => [
                    'en' => '<p>Sample description. Natural light, quality materials and a practical layout close to shops and transport.</p>',
                    'ar' => '<p>وصف تجريبي. إضاءة طبيعية ومواد عالية الجودة وتصميم عملي قرب المتاجر والمواصلات.</p>',
                    'el' => '<p>Δείγμα περιγραφής. Φυσικό φως, ποιοτικά υλικά και πρακτική διαρρύθμιση κοντά σε καταστήματα και συγκοινωνίες.</p>',
                ],
                'featured' => $featured,
                'publish_status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach (array_slice($amenities, 0, 2 + ($id % 4)) as $i => $amenity) {
                $db->table('listing_amenity')->insert(['listing_id' => $id, 'amenity_id' => $amenity->id, 'sort' => $i + 1]);
            }
        }

        $db->table('articles')->insert([
            'title' => [
                'en' => 'Can foreigners buy property in Greece?',
                'ar' => 'هل يمكن للأجانب شراء عقار في اليونان؟',
                'el' => 'Μπορούν οι αλλοδαποί να αγοράσουν ακίνητο στην Ελλάδα;',
            ],
            'slug' => 'can-foreigners-buy-property-in-greece',
            'excerpt' => [
                'en' => 'Sample article. A short guide for international buyers; the final text is reviewed by legal partners.',
                'ar' => 'مقال تجريبي. دليل قصير للمشترين الدوليين؛ يراجع الشركاء القانونيون النص النهائي.',
                'el' => 'Δείγμα άρθρου. Σύντομος οδηγός για διεθνείς αγοραστές· το τελικό κείμενο ελέγχεται από νομικούς συνεργάτες.',
            ],
            'body' => [
                'en' => '<p>Sample text. Replace with the reviewed International Buyers guidance.</p>',
                'ar' => '<p>نص تجريبي. استبدله بإرشادات المشترين الدوليين بعد مراجعتها.</p>',
                'el' => '<p>Δείγμα κειμένου. Αντικαταστήστε το με τον ελεγμένο οδηγό για διεθνείς αγοραστές.</p>',
            ],
            'publish_status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['Sample Person', ['en' => 'Managing Director', 'ar' => 'المدير العام', 'el' => 'Διευθύνων Σύμβουλος']],
            ['Sample Colleague', ['en' => 'Sales & Acquisition', 'ar' => 'المبيعات والاستحواذ', 'el' => 'Πωλήσεις & Αγορές']],
        ] as $i => [$name, $role]) {
            $db->table('team_members')->insert([
                'name' => $name, 'role' => $role, 'visible' => 1, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $first = $db->table('listings')->select(['id'])->where('reference', '=', 'SAMPLE-GR54-00')->first();
        $db->table('inquiries')->insert([
            'reference' => 'FH-SAMPLE1',
            'type' => 'listing',
            'listing_id' => $first->id ?? null,
            'name' => 'Sample Visitor',
            'email' => 'visitor@example.com',
            'phone' => '+30 210 000 0000',
            'preferred_contact' => 'whatsapp',
            'message' => 'Sample inquiry: is a viewing possible next week?',
            'consent_at' => $now,
            'lang' => 'en',
            'page_url' => '/en/properties/duplex-athens-sample',
            'status' => 'new',
            'email_sent' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function idByName(Database $db, string $table, string $english): ?int
    {
        foreach ($db->table($table)->select(['id', 'name'])->get() as $row) {
            $name = json_decode((string) $row->name, true);
            if (is_array($name) && ($name['en'] ?? '') === $english) {
                return (int) $row->id;
            }
        }
        return null;
    }
}
