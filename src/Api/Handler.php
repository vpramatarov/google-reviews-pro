<?php

declare(strict_types=1);

namespace GRP\Api;

use GRP\Api\Handler\ApiHandler;
use GRP\Api\Handler\Google;
use GRP\Api\Handler\SerpApi;

readonly class Handler
{
    private array $options;

    /** @var ApiHandler[] */
    private array $apiHandlers;

    public function __construct()
    {
        $this->options = empty(get_option('grp_settings')) ? [] : get_option('grp_settings');
        $this->apiHandlers = [
            new Google($this->options),
            new SerpApi($this->options),
        ];
    }

    /**
     * @return array{
     *     "data_source": string,
     *     "google_api_key": string,
     *     "place_id": string,
     *     "serpapi_key": string,
     *     "serpapi_data_id": string,
     *     "grp_business_name": string,
     *     "grp_address": string,
     *     "grp_phone": string,
     *     "grp_latitude": float|string,
     *     "grp_longitude": float|string,
     *     "grp_price": string,
     *     "grp_review_limit": int,
     *     "grp_text_color": string,
     *     "grp_bg_color": string,
     *     "grp_accent_color": string,
     *     "grp_btn_text_color": string,
     *     "grp_layout": string,
     *     "grp_min_rating": int,
     *     "grp_sort_order": string,
     *     "email_alerts": bool|int,
     *     "notification_email": string,
     *     "serpapi_pages": int,
     *     "auto_sync": bool|int,
     *     "sync_frequency": string
     * }|array{}
     */
    public function getApiOptions(): array
    {
        return $this->options;
    }

    /** @return ApiHandler[] */
    public function getApiHandlers(): array
    {
        return $this->apiHandlers;
    }

    public function get_stored_locations(): array
    {
        global $wpdb;

        $sql = sprintf("
            SELECT meta_value as place_id, COUNT(post_id) as count
            FROM %s
            WHERE meta_key = '_grp_assigned_place_id'
            AND meta_value != ''
            GROUP BY meta_value",
            $wpdb->postmeta
        );

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $cached_meta = get_option('grp_locations_db', []);

        return array_map(function($item) use ($cached_meta) {
            $pid = $item['place_id'];
            // if we have name in the cache, use it. Otherwise, use the place id.
            $name = !empty($cached_meta[$pid]['name']) ? $cached_meta[$pid]['name'] : $pid;

            return [
                'place_id' => $pid,
                'name' => $name,
                'count' => $item['count']
            ];
        }, $results);
    }

    public function get_location_metadata(string $place_id): ?array
    {
        $db = get_option('grp_locations_db', []);
        return $db[$place_id] ?? null;
    }

    public function get_reviews(int $limit = 10, int $offset = 0, string $specific_place_id = ''): array
    {
        $min_rating = absint($this->options['grp_min_rating'] ?? 0);
        $sort_order = $this->options['grp_sort_order'] ?? 'date_desc';

        $args = [
            'post_type'      => 'grp_review',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'status'         => 'publish',
            'meta_query'     => ['relation' => 'AND']
        ];

        // Filter: hide manual hidden reviews
        // (When meta _grp_is_hidden is '1', don't show)
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => '_grp_is_hidden',
                'compare' => 'NOT EXISTS', // if not exists it means it's not hidden
            ],
            [
                'key'     => '_grp_is_hidden',
                'value'   => '1',
                'compare' => '!=', // if not 1
            ]
        ];

        // Filter: minimum rating
        if ($min_rating > 0) {
            $args['meta_query'][] = [
                'key'     => '_grp_rating',
                'value'   => $min_rating,
                'compare' => '>=',
                'type'    => 'NUMERIC'
            ];
        }

        // Filter by Place ID (if passed as argument to shortcode)
        if (!empty($specific_place_id)) {
            $args['meta_query'][] = [
                'key'     => '_grp_assigned_place_id',
                'value'   => $specific_place_id,
                'compare' => '='
            ];
        }

        // Sorting
        switch ($sort_order) {
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order']   = 'ASC';
                break;
            case 'rating_desc':
                $args['meta_key'] = '_grp_rating';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'rating_asc':
                $args['meta_key'] = '_grp_rating';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'date_desc':
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;
        }

        $query = new \WP_Query($args);
        $reviews = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $photo_url = get_the_post_thumbnail_url($id, 'thumbnail');

                if (empty($photo_url)) {
                    $photo_url = '';
                }

                $reviews[] = [
                    'author_name'       => get_the_title(),
                    'text'              => get_the_content(),
                    'rating'            => get_post_meta($id, '_grp_rating', true),
                    'profile_photo_url' => $photo_url,
                    'time'              => (int) get_post_timestamp()
                ];
            }
            wp_reset_postdata();
        }

        return $reviews;
    }

    public function count_total_reviews(string $specific_place_id = ''): int
    {
        $min_rating = absint($this->options['grp_min_rating'] ?? 0);

        $args = [
            'post_type'  => 'grp_review',
            'status'     => 'publish',
            'fields'     => 'ids',
            'posts_per_page' => -1,
            'meta_query' => [ 'relation' => 'AND' ]
        ];

        // hidden filter
        $args['meta_query'][] = [
            'relation' => 'OR',
            ['key' => '_grp_is_hidden', 'compare' => 'NOT EXISTS'],
            ['key' => '_grp_is_hidden', 'value' => '1', 'compare' => '!=']
        ];

        // rating filter
        if ($min_rating > 0) {
            $args['meta_query'][] = [
                'key' => '_grp_rating',
                'value' => $min_rating,
                'compare' => '>=', 'type' => 'NUMERIC'
            ];
        }

        if (!empty($specific_place_id)) {
            $args['meta_query'][] = [
                'key'     => '_grp_assigned_place_id',
                'value'   => $specific_place_id,
                'compare' => '='
            ];
        }

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    public function sync_reviews(): \WP_Error|array
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }
        
        $source = $this->options['data_source'] ?? 'google';
        $current_place_id = '';
        $data = null;

        foreach ($this->apiHandlers as $apiHandler) {
            if ($apiHandler->supports($source)) {
                $current_place_id = $this->options['place_id'] ?? '';
                $data = $apiHandler->fetch();
            }
        }

        if ($data === null) {
            return ['success' => true, 'count' => 0, 'message' => __('Manual mode active.', 'google-reviews-pro')];
        }

        if (is_wp_error($data)) {
            return $data;
        }

        $reviews = $data['reviews'] ?? [];
        $meta = $data['meta'] ?? [];

        $stats = $this->save_reviews($reviews, $current_place_id);

        if (!empty($current_place_id) && !empty($meta)) {
            $this->save_location_metadata($current_place_id, $meta);
        }

        update_option('grp_last_sync_time', time());

        return $stats;
    }

    /**
     * Returns aggregated statistics (Total Count and Average Rating) directly from the database.
     * This is much faster than WP_Query for calculations.
     *
     * @return array{"reviewCount": int, "ratingValue": float}
     */
    public function get_aggregate_stats(string $place_id = ''): array
    {
        global $wpdb;

        /**
         * @note: Basic query: We get the average of the rating and the count of the rows.
         * We join the posts table to make sure that the reviews are 'published'.
         */
        $sql = "
            SELECT 
                COUNT(pm.post_id) as review_count, 
                AVG(pm.meta_value) as rating_value
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'grp_review' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_grp_rating'
        ";

        /**
         * @note: If we have a specific Place ID, we need to filter.
         * This requires a JOIN with the meta table itself once more (self-join) to check the other meta field as well
         */
        if (!empty($place_id)) {
            $sql = "
                SELECT 
                    COUNT(pm_rating.post_id) as review_count, 
                    AVG(pm_rating.meta_value) as rating_value
                FROM {$wpdb->postmeta} pm_rating
                JOIN {$wpdb->posts} p ON pm_rating.post_id = p.ID
                JOIN {$wpdb->postmeta} pm_place ON pm_rating.post_id = pm_place.post_id
                WHERE p.post_type = 'grp_review' 
                AND p.post_status = 'publish'
                AND pm_rating.meta_key = '_grp_rating'
                AND pm_place.meta_key = '_grp_assigned_place_id'
                AND pm_place.meta_value = %s
            ";
            $sql = $wpdb->prepare($sql, $place_id);
        }

        $result = $wpdb->get_row($sql, ARRAY_A);

        return [
            'reviewCount' => isset($result['review_count']) ? (int)$result['review_count'] : 0,
            'ratingValue' => isset($result['rating_value']) ? round((float)$result['rating_value'], 1) : 5.0
        ];
    }

    private function save_location_metadata(string $place_id, array $meta): void
    {
        $db = get_option('grp_locations_db', []);
        $db[$place_id] = [
            'name'      => sanitize_text_field($meta['name'] ?? ''),
            'address'   => sanitize_text_field($meta['address'] ?? ''),
            'phone'     => sanitize_text_field($meta['phone'] ?? ''),
            'lat'       => sanitize_text_field($meta['lat'] ?? ''),
            'lng'       => sanitize_text_field($meta['lng'] ?? ''),
            'updated'   => time()
        ];

        update_option('grp_locations_db', $db);
    }

    private function save_reviews(array $reviews, string $assigned_place_id): array
    {
        $inserted = 0;
        $updated = 0;

        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $newly_added_for_email = [];
        $send_email_alerts = !empty($this->options['email_alerts']);

        foreach ($reviews as $review) {
            $args = [
                'post_type' => 'grp_review',
                'meta_query' => [
                    [
                        'key' => '_grp_external_id',
                        'value' => $review['external_id']
                    ]
                ],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ];

            $existing_query = new \WP_Query($args);
            $post_id = null;

            if ($existing_query->have_posts()) {
                $post_id = $existing_query->posts[0];
                $updated++;
            } else {
                $post_id = wp_insert_post([
                    'post_type'    => 'grp_review',
                    'post_title'   => $review['author_name'],
                    'post_content' => $review['text'],
                    'post_status'  => 'publish',
                    'post_date'    => date('Y-m-d H:i:s', $review['time']),
                ]);
                $inserted++;

                if ($send_email_alerts) {
                    $newly_added_for_email[] = [
                        'author' => $review['author_name'],
                        'rating' => $review['rating'],
                        'text'   => wp_trim_words($review['text'], 20)
                    ];
                }
            }

            if ($post_id) {
                update_post_meta($post_id, '_grp_external_id', $review['external_id']);
                update_post_meta($post_id, '_grp_rating', $review['rating']);
                update_post_meta($post_id, '_grp_author_url', $review['author_url'] ?? '');
                update_post_meta($post_id, '_grp_source', $review['source'] ?? 'manual');

                if (!empty($assigned_place_id)) {
                    update_post_meta($post_id, '_grp_assigned_place_id', $assigned_place_id);
                }

                $external_url = $review['photo_url'] ?? '';
                $has_thumbnail = has_post_thumbnail($post_id);

                if (!$has_thumbnail && !empty($external_url)) {
                    $this->download_image($external_url, $post_id, $review['author_name']);
                    update_post_meta($post_id, '_grp_photo_url', $external_url);
                }
            }
        }

        if (!empty($newly_added_for_email)) {
            $this->send_notification_email($newly_added_for_email);
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Downloads an external image to the Media Library.
     */
    private function download_image(string $url, int $post_id, string $desc): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $response = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }

        $image_contents = wp_remote_retrieve_body($response);
        $file_type = wp_remote_retrieve_header($response, 'content-type');

        $extension = 'jpg'; // Fallback
        if ($file_type === 'image/png') {
            $extension = 'png';
        }

        if ($file_type === 'image/gif') {
            $extension = 'gif';
        }

        if ($file_type === 'image/webp') {
            $extension = 'webp';
        }

        $filename = 'review-avatar-' . $post_id . '-' . md5($url) . '.' . $extension;
        $upload = wp_upload_bits($filename, null, $image_contents);

        if ($upload['error']) {
            return;
        }

        $file_path = $upload['file'];
        $file_url  = $upload['url'];

        $attachment = [
            'post_mime_type' => $file_type,
            'post_title'     => $desc . ' Avatar',
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            set_post_thumbnail($post_id, $attach_id);
        }
    }

    private function send_notification_email(array $reviews): void
    {
        $notification_email = $this->options['notification_email'] ?? '';
        $to_email = (!empty($notification_email) && is_email($notification_email)) ? $notification_email : get_option('admin_email');
        $site_name = get_bloginfo('name');
        $count = count($reviews);

        $subject = sprintf(
            _n(
                'New Google Review on %s from %s',
                '%d New Google Reviews on %s',
                $count,
                'google-reviews-pro'
            ),
            ($count === 1 ? $reviews[0]['author'] : $count),
            $site_name
        );

        // HTML Header
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= sprintf('<h2 style="color: #4285F4;">%s</h2>', __('You have received new reviews!', 'google-reviews-pro'));
        $message .= '<ul style="list-style: none; padding: 0;">';

        foreach ($reviews as $review) {
            $stars = str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']);

            $message .= '<li style="background: #f9f9f9; padding: 15px; margin-bottom: 10px; border-left: 4px solid #fbbc04;">';
            $message .= sprintf('<strong>%s</strong> <span style="color: #fbbc04; font-size: 18px;">%s</span><br>', esc_html($review['author']), $stars);

            if (!empty($review['text'])) {
                $message .= sprintf('<em style="color: #666;">"%s"</em>', esc_html($review['text']));
            }

            $message .= '</li>';
        }

        $message .= '</ul>';
        $message .= sprintf(
            '<p><a href="%s" style="background: #4285F4; color: #fff; text-decoration: none; padding: 10px 15px; border-radius: 4px;">%s</a></p>',
            admin_url('edit.php?post_type=grp_review'),
            __('Manage Reviews in WordPress', 'google-reviews-pro')
        );
        $message .= '</body></html>';

        // Headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Send
        wp_mail($to_email, $subject, $message, $headers);
    }

}
