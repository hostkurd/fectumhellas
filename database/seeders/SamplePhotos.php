<?php
declare(strict_types=1);

namespace Database\Seeders;

use FloCMS\Core\Database;

/**
 * The sample photos in database/seeders/samples, shared by PagesSeeder and
 * InsightsSeeder. Each photo is copied into the media library only once (found
 * again by its path), titled "SAMPLE – replace with a company photo".
 */
trait SamplePhotos
{
    /**
     * Copies the sample photos into the media folder and the media library
     * (once), and returns their ids by name. No library: pages without photos.
     *
     * @return array<string, string>
     */
    protected function samplePhotos(Database $db): array
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
