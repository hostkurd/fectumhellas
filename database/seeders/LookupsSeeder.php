<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\CLI\Database\Seeder;
use FloCMS\Core\Database;

/**
 * Starting cities, property types and amenities in English, Arabic and Greek
 * (design note D14). Editors change them later in the admin. A table that
 * already has rows is left alone.
 */
final class LookupsSeeder extends Seeder
{
    public const CITIES = [
        ['Athens', 'أثينا', 'Αθήνα'],
        ['Piraeus', 'بيرايوس', 'Πειραιάς'],
        ['Glyfada', 'غليفادا', 'Γλυφάδα'],
        ['Voula', 'فولا', 'Βούλα'],
        ['Vouliagmeni', 'فولياغميني', 'Βουλιαγμένη'],
        ['Marousi', 'ماروسي', 'Μαρούσι'],
        ['Kifisia', 'كيفيسيا', 'Κηφισιά'],
        ['Thessaloniki', 'سالونيك', 'Θεσσαλονίκη'],
    ];

    public const TYPES = [
        ['Apartment', 'شقة', 'Διαμέρισμα'],
        ['Duplex', 'دوبلكس', 'Διώροφο'],
        ['Maisonette', 'ميزونيت', 'Μεζονέτα'],
        ['Penthouse', 'بنتهاوس', 'Ρετιρέ'],
        ['Villa', 'فيلا', 'Βίλα'],
        ['Detached house', 'منزل مستقل', 'Μονοκατοικία'],
        ['Studio', 'ستوديو', 'Στούντιο'],
        ['Land plot', 'قطعة أرض', 'Οικόπεδο'],
        ['Commercial', 'عقار تجاري', 'Επαγγελματικός χώρος'],
    ];

    public const AMENITIES = [
        ['Sea view', 'إطلالة على البحر', 'Θέα θάλασσα'],
        ['Balcony', 'شرفة', 'Μπαλκόνι'],
        ['Roof terrace', 'تراس على السطح', 'Ταράτσα'],
        ['Private garden', 'حديقة خاصة', 'Ιδιωτικός κήπος'],
        ['Swimming pool', 'مسبح', 'Πισίνα'],
        ['Elevator', 'مصعد', 'Ανελκυστήρας'],
        ['Covered parking', 'موقف مغطى', 'Στεγασμένη θέση στάθμευσης'],
        ['Storage room', 'غرفة تخزين', 'Αποθήκη'],
        ['Air conditioning', 'تكييف', 'Κλιματισμός'],
        ['Underfloor heating', 'تدفئة أرضية', 'Ενδοδαπέδια θέρμανση'],
        ['Fireplace', 'مدفأة', 'Τζάκι'],
        ['Solar water heater', 'سخان شمسي', 'Ηλιακός θερμοσίφωνας'],
        ['Security door', 'باب أمان', 'Πόρτα ασφαλείας'],
        ['Smart home', 'منزل ذكي', 'Έξυπνο σπίτι'],
        ['Furnished', 'مفروش', 'Επιπλωμένο'],
        ['Near the metro', 'قريب من المترو', 'Κοντά στο μετρό'],
    ];

    public function run(Database $db): void
    {
        foreach (['cities' => self::CITIES, 'property_types' => self::TYPES, 'amenities' => self::AMENITIES] as $table => $rows) {
            if ($db->table($table)->count() > 0) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            foreach ($rows as $i => [$en, $ar, $el]) {
                $db->table($table)->insert([
                    'name' => ['en' => $en, 'ar' => $ar, 'el' => $el],
                    'sort' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
