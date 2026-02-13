<?php

declare(strict_types=1);

namespace GRP\Core;

use ZipArchive;

class ReviewExporter
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'handle_export_request']);
    }

    public function handle_export_request(): void
    {
        if (!isset($_POST['grp_action']) || !isset($_POST['grp_export_nonce'])) {
            return;
        }

        // Check Nonce + Permissions
        if (!wp_verify_nonce($_POST['grp_export_nonce'], 'grp_export_action')) {
            wp_die(__('Security check failed', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'google-reviews-pro'));
        }

        if ($_POST['grp_action'] === 'export_zip') {
            $zip_path = $this->generate_backup_zip();

            if (file_exists($zip_path)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="grp-backup-full.zip"');
                header('Content-Length: ' . filesize($zip_path));
                readfile($zip_path);

                unlink($zip_path);
                exit;
            } else {
                wp_die('Error creating ZIP file.');
            }
        }

        $data = $this->get_raw_data();
        $filename = 'google-reviews-' . date('Y-m-d') . '-' . count($data);

        if ($_POST['grp_action'] === 'export_json') {
            $this->send_json_download($data, $filename . '.json');
        } elseif ($_POST['grp_action'] === 'export_csv') {
            $this->send_csv_download($data, $filename . '.csv');
        }
    }

    /**
     * Creates a ZIP archive with JSON data and physical images (if local).
     * @return string The path to the generated ZIP file or an empty string on error.
     */
    private function generate_backup_zip(): string
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

    private function get_raw_data(): array
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

    private function send_json_download(array $data, string $filename): void
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function send_csv_download(array $data, string $filename): void
    {
        if (empty($data)) {
            wp_die(__('No reviews to export.', 'google-reviews-pro'));
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fputs($output, "\xEF\xBB\xBF");

        // Headers
        fputcsv($output, array_keys($data[0]));

        // Rows
        foreach ($data as $row) {
            // Formatting the date for CSV to be readable (in JSON we store it as a timestamp)
            $row['time'] = date('Y-m-d H:i:s', (int)$row['time']);

            // sanitization against Excel Injection
            $row = array_map(function($value) {
                if (is_string($value) && preg_match('/^[=\+\-@]/', $value)) {
                    return "'" . $value; // add "'" so it's treated like text
                }
                return $value;
            }, $row);

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
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