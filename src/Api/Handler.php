<?php

declare(strict_types=1);

namespace GRP\Api;

use GRP\Api\Handler\ApiHandler;
use GRP\Api\Handler\Google;
use GRP\Api\Handler\ScrapingDog;
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
            new ScrapingDog($this->options),
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
     *     "scrapingdog_api_key": string,
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
     *     "sync_frequency": string,
     *     "grp_hide_empty": bool|int
     * }|array{}
     */
    public function get_api_options(): array
    {
        return $this->options;
    }

    /** @return ApiHandler[] */
    public function get_api_handlers(): array
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
        $hide_empty = isset($this->options['grp_hide_empty']) && $this->options['grp_hide_empty'];

        $args = [
            'post_type' => 'grp_review',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'status' => 'publish',
            'meta_query' => ['relation' => 'AND']
        ];

        // Filter: hide manual hidden reviews
        // (When meta _grp_is_hidden is '1', don't show)
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_grp_is_hidden',
                'compare' => 'NOT EXISTS', // if not exists it means it's not hidden
            ],
            [
                'key' => '_grp_is_hidden',
                'value' => '1',
                'compare' => '!=', // if not 1
            ]
        ];

        // Filter: minimum rating
        if ($min_rating > 1) {
            $args['meta_query'][] = [
                'key' => '_grp_rating',
                'value' => $min_rating,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        }

        // Filter by Place ID (if passed as argument to shortcode)
        if (!empty($specific_place_id)) {
            $args['meta_query'][] = [
                'key' => '_grp_assigned_place_id',
                'value' => $specific_place_id,
                'compare' => '='
            ];
        }

        // Sorting
        switch ($sort_order) {
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'rating_desc':
                $args['meta_key'] = '_grp_rating';
                $args['orderby'] = [
                    'meta_value_num' => 'DESC',
                    'date' => 'DESC',
                    'ID' => 'DESC'
                ];
                break;
            case 'rating_asc':
                $args['meta_key'] = '_grp_rating';
                $args['orderby'] = [
                    'meta_value_num' => 'ASC',
                    'date' => 'DESC',
                    'ID' => 'DESC'
                ];
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'date_desc':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }

        if ($hide_empty) {
            add_filter('posts_where', [$this, 'filter_where_not_empty']);
        }

        $query = new \WP_Query($args);

        // IMPORTANT: We remove the filter immediately so as not to break other WordPress queries!
        if ($hide_empty) {
            remove_filter('posts_where', [$this, 'filter_where_not_empty']);
        }
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
                    'author_name' => get_the_title(),
                    'text' => get_the_content(),
                    'rating' => get_post_meta($id, '_grp_rating', true),
                    'profile_photo_url' => $photo_url,
                    'time' => (int) get_post_timestamp()
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
            'post_type' => 'grp_review',
            'status' => 'publish',
            'fields' => 'ids',
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
                'key' => '_grp_assigned_place_id',
                'value' => $specific_place_id,
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
        $current_place_id = $this->options['place_id'] ?? '';
        $data = null;

        $db = get_option('grp_locations_db', []);

        if (empty($db) && !empty($current_place_id)) {
            $db[$current_place_id] = [];
        }

        $locations = array_keys($db);

        if (empty($locations)) {
            return new \WP_Error('api_error', __('No locations found.', 'google-reviews-pro'));
        }

        foreach ($this->apiHandlers as $apiHandler) {
            if (!$apiHandler->supports($source)) {
                continue;
            }

            $data = [];
            foreach ($locations as $place_id) {
                $id = $db[$place_id]['data_id'] ?? $place_id;
                $data[$place_id] = $apiHandler->fetch($id);
            }

            break;
        }

        if ($data === null) {
            return ['success' => true, 'count' => 0, 'message' => __('Manual mode active.', 'google-reviews-pro')];
        }

        $response = [];

        foreach ($data as $place_id => $locationData) {
            if (is_wp_error($locationData)) {
                return $locationData;
            }

            $reviews = $locationData['reviews'] ?? [];
            $meta = $locationData['meta'] ?? [];
            $stats = $this->save_reviews($reviews, $place_id);

            if (!empty($place_id) && !empty($meta)) {
                $this->save_location_metadata($place_id, $meta);
            }

            $response[$place_id] = $stats;
        }

        update_option('grp_last_sync_time', time());

        return $response;
    }

    /**
     * Returns aggregated statistics (Total Count and Average Rating) directly from the database.
     *  PRIORITY 1: Real data from API (stored in meta options).
     *  PRIORITY 2: Calculation from local database (SQL).
     *
     * @return array{"reviewCount": int, "ratingValue": float}
     */
    public function get_aggregate_stats(string $place_id = ''): array
    {
        if (!empty($place_id)) {
            $meta = $this->get_location_metadata($place_id);

            // Checking if we have valid data (more than 0 reviews)
            if (!empty($meta) && !empty($meta['count']) && $meta['count'] > 0) {
                return [
                    'reviewCount' => (int) $meta['count'],
                    'ratingValue' => (float) ($meta['rating'] ?? 5.0)
                ];
            }
        }

        // Fallback
        global $wpdb;

        /**
         * @note: Basic query: We get the average of the rating and the count of the rows.
         * We join the posts table to make sure that the reviews are 'published' and not 'hidden'.
         */
        $sql = "
            SELECT 
                COUNT(pm.post_id) as review_count, 
                AVG(pm.meta_value) as rating_value
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm_hidden ON (
                p.ID = pm_hidden.post_id AND pm_hidden.meta_key = '_grp_is_hidden'
            )
            WHERE p.post_type = 'grp_review' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_grp_rating'
            AND pm_hidden.post_id IS NULL
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
                LEFT JOIN {$wpdb->postmeta} pm_hidden ON (
                    p.ID = pm_hidden.post_id AND pm_hidden.meta_key = '_grp_is_hidden'
                )
                WHERE p.post_type = 'grp_review' 
                AND p.post_status = 'publish'
                AND pm_rating.meta_key = '_grp_rating'
                AND pm_place.meta_key = '_grp_assigned_place_id'
                AND pm_place.meta_value = %s
                AND pm_hidden.post_id IS NULL
            ";
            $sql = $wpdb->prepare($sql, $place_id);
        }

        $result = $wpdb->get_row($sql, ARRAY_A);

        return [
            'reviewCount' => isset($result['review_count']) ? (int)$result['review_count'] : 0,
            'ratingValue' => isset($result['rating_value']) ? round((float)$result['rating_value'], 1) : 5.0
        ];
    }

    /**
     * @param array{business_name: string, address: string, phone: string, rating: float|int, total_count: int} $data
     * @return bool
     */
    public function update_location(string $place_id, array $data): bool
    {
        if (empty($place_id) || empty($data)) {
            return false;
        }

        $required_fields = ['business_name', 'address'];
        foreach ($required_fields as $field) {
            if (empty($data[$field]) || trim($data[$field]) === '') {
                return false;
            }
        }

        $phone = $data['phone'] ?? '';
        $rating = !empty($data['rating']) ? (float)$data['rating'] : 0;
        $total_count = absint($data['total_count'] ?? 0);

        $db = get_option('grp_locations_db', []);
        if (isset($db[$place_id])) {
            $db[$place_id] = [
                'name' => sanitize_text_field($data['business_name']),
                'address' => sanitize_textarea_field($data['address']),
                'updated' => time()
            ];

            if (!empty($phone) && $phone !== $db[$place_id]['phone']) {
                $db[$place_id]['phone'] = sanitize_text_field($phone);
            }

            if ($total_count > 0 && ($total_count !== $db[$place_id]['count'])) {
                $db[$place_id]['count'] = $total_count;
            }

            if (($rating >= 1 && $rating <= 5) && $db[$place_id]['rating'] !== $rating) {
                $db[$place_id]['rating'] = $rating;
            }

            return update_option('grp_locations_db', $db);
        }

        return false;
    }

    /**
     * Deletes a location and all associated data (reviews, images, metadata).
     * @return array{"success": bool, "reviews_deleted": int, "images_deleted": int}
     */
    public function delete_location(string $place_id): array
    {
        $args = [
            'post_type' => 'grp_review',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_grp_assigned_place_id',
                    'value' => $place_id
                ]
            ]
        ];

        $query = new \WP_Query($args);
        $deleted_reviews = 0;
        $deleted_images = 0;

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                // Delete attachment (Featured Image), if any
                $thumb_id = get_post_thumbnail_id($post_id);
                if ($thumb_id) {
                    wp_delete_attachment($thumb_id, true);
                    $deleted_images++;
                }

                // Delete the review
                wp_delete_post($post_id, true);
                $deleted_reviews++;
            }
        }

        $db = get_option('grp_locations_db', []);
        if (isset($db[$place_id])) {
            unset($db[$place_id]);
            update_option('grp_locations_db', $db);
        }

        return [
            'success' => true,
            'reviews_deleted' => $deleted_reviews,
            'images_deleted' => $deleted_images
        ];
    }

    public function save_location_metadata(string $place_id, array $meta): void
    {
        $db = get_option('grp_locations_db', []);
        $existing = $db[$place_id] ?? [];
        $db[$place_id] = [
            'data_id' => sanitize_text_field($meta['data_id'] ?? $existing['data_id'] ?? null),
            'name' => sanitize_text_field($meta['name'] ?? $existing['name'] ?? null),
            'address' => sanitize_text_field($meta['address'] ?? $existing['address'] ?? null),
            'phone' => sanitize_text_field($meta['phone'] ?? $existing['phone'] ?? null),
            'lat' => sanitize_text_field($meta['lat'] ?? $existing['lat'] ?? null),
            'lng' => sanitize_text_field($meta['lng'] ?? $existing['lng'] ?? null),
            'price_level' => isset($meta['price_level']) ? (int)$meta['price_level'] : ($existing['price_level'] ?? null),
            'maps_url' => esc_url_raw($meta['maps_url'] ?? $existing['maps_url'] ?? null),
            'website' => esc_url_raw($meta['website'] ?? $existing['website'] ?? get_home_url()),
            'periods' => $meta['periods'] ?? $existing['periods'] ?? null,
            'rating' => !empty($meta['rating']) ? (float)$meta['rating'] : ($existing['rating'] ?? 0),
            'count' => !empty($meta['count']) ? (int)$meta['count'] : ($existing['count'] ?? 0),
            'updated' => time()
        ];

        update_option('grp_locations_db', $db);
    }

    public function save_reviews(array $reviews, string $assigned_place_id): array
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
                    'post_type' => 'grp_review',
                    'post_title' => $review['author_name'],
                    'post_content' => $review['text'],
                    'post_status' => 'publish',
                    'post_date' => date('Y-m-d H:i:s', $review['time'] ?: time()),
                ]);
                $inserted++;

                if ($send_email_alerts) {
                    $newly_added_for_email[] = [
                        'author' => $review['author_name'],
                        'rating' => $review['rating'],
                        'text' => wp_trim_words($review['text'], 20)
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
        if (!wp_http_validate_url($url)) {
            return;
        }

        $response = wp_remote_get($url, ['timeout' => GRP_TIMEOUT]);
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
            'post_title' => $desc . ' Avatar',
            'post_content' => '',
            'post_status' => 'inherit'
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

    /**
     * SQL Filter: Exclude posts with empty content.
     * Use TRIM() to avoid showing reviews that only contain spaces.
     */
    public function filter_where_not_empty(string $where): string
    {
        global $wpdb;

        $where .= " AND TRIM({$wpdb->posts}.post_content) != '' ";
        return $where;
    }

}
