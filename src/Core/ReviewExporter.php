<?php

declare(strict_types=1);

namespace GRP\Core;

use ZipArchive;

class ReviewExporter
{
    /**
     * Creates a ZIP archive with JSON data and physical images (if local).
     * @return string The path to the generated ZIP file or an empty string on error.
     */
    public function generate_backup_zip(): string
    {
        $reviews = $this->get_raw_data();
        $upload_dir = wp_upload_dir();
        // We create a file name: grp-backup-YYYY-MM-DD-His.zip
        $zip_filename = 'grp-backup-' . date('Y-m-d-His') . '.zip';
        $zip_file_path = $upload_dir['basedir'] . '/' . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return '';
        }

        $export_data = [];
        $added_images = []; // Cache to avoid file duplication in the archive

        foreach ($reviews as $review) {
            $local_path = $this->get_local_path_from_url($review['photo_url'] ?? '');

            if (empty($local_path)) {
                $local_path = $this->get_local_path_from_url($review['featured_image_url'] ?? '');
            }

            if ($local_path && file_exists($local_path)) {
                $filename = basename($local_path);
                $hash = hash('sha256', $filename);

                // We add it to the 'images/' folder inside the ZIP
                if (!isset($added_images[$hash])) {
                    $zip->addFile($local_path, 'images/' . $filename);
                    $added_images[$hash] = $filename;
                }

                // We record the relative path that the Importer will use
                $review['zip_image_path'] = 'images/' . $filename;
            }

            $export_data[] = $review;
        }

        // Add the JSON file (with the updated paths to the photos)
        $zip->addFromString('data.json', json_encode($export_data, JSON_UNESCAPED_UNICODE));

        $zip->close();

        return $zip_file_path;
    }

    public function get_raw_data(): array
    {
        $args = [
            'post_type'  => 'grp_review',
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'update_post_term_cache' => false,
        ];

        $query = new \WP_Query($args);
        $data = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();

                $data[] = [
                    'id' => $id,
                    'external_id' => get_post_meta($id, '_grp_external_id', true),
                    'author_name' => get_the_title(),
                    'text' => get_the_content(),
                    'rating' => get_post_meta($id, '_grp_rating', true),
                    'time'  => get_post_timestamp(), // Unix timestamp
                    'photo_url' => get_post_meta($id, '_grp_photo_url', true),
                    'featured_image_url' => get_the_post_thumbnail_url($id, 'full'),
                    'place_id' => get_post_meta($id, '_grp_assigned_place_id', true),
                    'source' => get_post_meta($id, '_grp_source', true),
                    'is_hidden' => get_post_meta($id, '_grp_is_hidden', true) ? 1 : 0
                ];
            }
            wp_reset_postdata();
        }

        return $data;
    }

    /**
     * Checks if the URL is local (from the uploads folder) and returns the physical path.
     */
    private function get_local_path_from_url(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

        // If the URL starts with the site domain
        if (str_starts_with($url, $base_url)) {
            // We replace the URL part with the directory
            return str_replace($base_url, $upload_dir['basedir'], $url);
        }

        return null; // External URL (e.g. Google CDN)
    }
}