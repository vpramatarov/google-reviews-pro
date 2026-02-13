<?php

declare(strict_types=1);

namespace GRP\Core;

use ZipArchive;

class ReviewImporter
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'handle_import_request']);
    }

    public function handle_import_request(): void
    {
        if (!isset($_POST['grp_action']) || $_POST['grp_action'] !== 'import_file' || !isset($_FILES['grp_import_file'])) {
            return;
        }

        if (!check_admin_referer('grp_import_action', 'grp_import_nonce')) {
            wp_die(__('Security check failed', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'google-reviews-pro'));
        }

        $file = $_FILES['grp_import_file'];

        // check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('grp_settings', 'upload_error', __('File upload failed.', 'google-reviews-pro'));
            return;
        }

        // Check MIME type (basic)
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'json') {
            add_settings_error('grp_settings', 'invalid_type', __('Only JSON files are allowed.', 'google-reviews-pro'));
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if ($ext === 'zip') {
            $stats = $this->import_zip($file['tmp_name']);
        } elseif ($ext === 'json') {
            $stats = $this->import_json($file['tmp_name']);
        } elseif ($ext === 'csv') {
            $stats = $this->import_csv($file['tmp_name']);
        } else {
            add_settings_error(
                'grp_settings',
                'invalid_type',
                __('Invalid file type. Allowed: .json, .csv, .zip', 'google-reviews-pro')
            );
            return;
        }

        if ($stats['errors'] > 0 && $stats['success'] === 0) {
            add_settings_error('grp_settings', 'import_fail', __('Failed to parse JSON file.', 'google-reviews-pro'));
        } else {
            add_settings_error(
                'grp_settings',
                'import_success',
                sprintf(
                    __('Import complete! Added: %d, Skipped (Duplicates): %d, Errors: %d', 'google-reviews-pro'),
                    $stats['success'],
                    $stats['skipped'],
                    $stats['errors']
                ),
                'success'
            );
        }
    }

    /**
     * @param string $zip_file_path The temporary path to the uploaded file (tmp_name)
     * @return array{success: int, skipped: int, errors: int}
     */
    private function import_zip(string $zip_file_path): array
    {
        $stats = ['success' => 0, 'skipped' => 0, 'errors' => 0];
        $upload_dir = wp_upload_dir();
        $extract_path = $upload_dir['basedir'] . '/grp-import-' . time();

        if (!mkdir($extract_path) && !is_dir($extract_path)) {
            return $stats;
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path) === true) {
            $allowed_extensions = ['json', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $fileinfo = pathinfo($filename);

                // Directory Traversal (Zip Slip)
                if (str_contains($filename, '..') || str_contains($filename, '__MACOSX')) {
                    continue;
                }

                if (!isset($fileinfo['extension']) || !in_array(strtolower($fileinfo['extension']), $allowed_extensions)) {
                    continue;
                }

                $zip->extractTo($extract_path, $filename);
            }
            $zip->close();
        } else {
            // Invalid ZIP
            return $stats;
        }

        $json_file = $extract_path . '/data.json';
        if (!file_exists($json_file)) {
            $this->cleanup($extract_path);
            return $stats;
        }

        $reviews = json_decode(file_get_contents($json_file), true);

        if (is_array($reviews)) {
            foreach ($reviews as $review) {
                // We pass $extract_path so we can find the photos
                $this->process_single_review($review, $stats, $extract_path);
            }
        }

        $this->cleanup($extract_path);

        return $stats;
    }

    private function import_json(string $json_file_path): array
    {
        $stats = ['success' => 0, 'skipped' => 0, 'errors' => 0];

        if (!file_exists($json_file_path)) {
            return $stats;
        }

        $reviews = json_decode(file_get_contents($json_file_path), true);

        if (is_array($reviews)) {
            foreach ($reviews as $review) {
                // We are passing an empty path because we have no local files.
                $this->process_single_review($review, $stats, '');
            }
        }

        return $stats;
    }

    private function import_csv(string $file_path): array
    {
        $stats = ['success' => 0, 'skipped' => 0, 'errors' => 0];

        if (!file_exists($file_path)) {
            return $stats;
        }

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return $stats;
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            return $stats;
        }

        $required_headers = ['external_id', 'author_name'];
        if (count(array_intersect($required_headers, $headers)) !== count($required_headers)) {
            fclose($handle);
            return ['success' => 0, 'skipped' => 0, 'errors' => 1];
        }

        // Remove BOM (Byte Order Mark), if any
        $headers[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $headers[0]);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($headers) !== count($row)) {
                continue;
            }

            $review_data = array_combine($headers, $row);

            // The CSV export records the date as "Y-m-d H:i:s", but our processor wants a timestamp
            if (isset($review_data['time']) && !is_numeric($review_data['time'])) {
                $review_data['time'] = strtotime($review_data['time']);
            }

            // CSV has no ZIP paths, so we pass an empty string for extract_path
            $this->process_single_review($review_data, $stats, '');
        }

        fclose($handle);

        return $stats;
    }

    private function process_single_review(array $review, array &$stats, string $extract_path): void
    {
        if (empty($review['external_id']) || empty($review['author_name'])) {
            $stats['errors']++;
            return;
        }

        // Check for duplicates
        $exists_query = new \WP_Query([
            'post_type'   => 'grp_review',
            'meta_key'    => '_grp_external_id',
            'meta_value'  => $review['external_id'],
            'post_status' => 'any',
            'fields'      => 'ids',
            'no_found_rows' => true
        ]);

        if ($exists_query->have_posts()) {
            $stats['skipped']++;
            return;
        }

        $post_data = [
            'post_type' => 'grp_review',
            'post_title' => sanitize_text_field($review['author_name']),
            'post_content' => wp_kses_post($review['text'] ?? ''),
            'post_status' => 'publish',
            'post_date' => date('Y-m-d H:i:s', (int)$review['time']),
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $stats['errors']++;
            return;
        }

        $final_photo_url = '';

        // file migration if file is archive
        if (!empty($review['zip_image_path']) && !empty($extract_path)) {
            $safe_filename = basename($review['zip_image_path']);
            $image_source_path = $extract_path . '/images/' . $safe_filename;

            if (file_exists($image_source_path) && str_starts_with(realpath($image_source_path), realpath($extract_path))) {
                if ($attach_id = $this->insert_image_to_media_library($image_source_path, $post_id)) {
                    $final_photo_url = wp_get_attachment_url($attach_id);
                }
            }
        }

        // Fallback to original url
        if (empty($final_photo_url) && !empty($review['photo_url'])) {
            $photo_url = $review['photo_url'];

            /**
             * Filter for changing URL during import.
             * Useful when changing domain, if the images are not in the ZIP, or for changing protocol (http -> https).
             */
            $final_photo_url = apply_filters('grp_import_photo_url', $photo_url, $review);
        }

        if (!empty($final_photo_url)) {
            update_post_meta($post_id, '_grp_photo_url', esc_url_raw($final_photo_url));
        }

        update_post_meta($post_id, '_grp_external_id', sanitize_text_field($review['external_id']));
        update_post_meta($post_id, '_grp_rating', (float)($review['rating'] ?? 5));

        if (!empty($review['place_id'])) {
            update_post_meta($post_id, '_grp_assigned_place_id', sanitize_text_field($review['place_id']));
        }

        if (!empty($review['source'])) {
            update_post_meta($post_id, '_grp_source', sanitize_text_field($review['source']));
        }

        if (!empty($review['is_hidden'])) {
            update_post_meta($post_id, '_grp_is_hidden', 1);
        }

        $stats['success']++;
    }

    private function insert_image_to_media_library(string $file_path, int $parent_post_id): int
    {
        $filename = basename($file_path);
        $file_content = file_get_contents($file_path);

        if ($file_content === false) {
            return 0;
        }

        $upload_file = wp_upload_bits($filename, null, $file_content);

        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null);

            $attachment = [
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => $parent_post_id,
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            ];

            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $parent_post_id);

            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $attach_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attach_data);

                return $attachment_id;
            }
        }
        return 0;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->cleanup("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }
}