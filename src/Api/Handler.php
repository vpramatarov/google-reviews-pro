<?php

declare(strict_types=1);

namespace GRP\Api;

readonly class Handler
{
    /**
     * @var array{"data_source": string, "google_api_key": string, "place_id": string, "serpapi_key": string, "serpapi_data_id": string}|array{}
     */
    private array $options;

    public function __construct() {
        $this->options = empty(get_option('grp_settings')) ? [] : get_option('grp_settings');
    }

    /**
     * @return array{"data_source": string, "google_api_key": string, "place_id": string, "serpapi_key": string, "serpapi_data_id": string}|array{}
     */
    public function getApiOptions(): array
    {
        return $this->options;
    }

    public function get_stored_locations(): array
    {
        global $wpdb;

        $sql = sprintf("
            SELECT meta_value as place_id, COUNT(post_id) as count
            FROM %s
            WHERE meta_key = '_grp_assigned_place_id'
            AND meta_value != ''
            GROUP BY meta_value
            ORDER BY count DESC",
            $wpdb->postmeta
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
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
        $source = $this->options['data_source'] ?? 'google';
        $current_place_id = '';

        if ($source === 'google') {
            $current_place_id = $this->options['place_id'] ?? '';
            $data = $this->fetch_google();
        } elseif ($source === 'serpapi') {
            $current_place_id = $this->options['place_id'] ?? '';
            $data = $this->fetch_serpapi();
        } else {
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

    private function fetch_google(): \WP_Error|array
    {
        $api_key = $this->options['google_api_key'] ?? '';
        $place_id = $this->options['place_id'] ?? '';

        if (!$api_key || !$place_id) {
            return new \WP_Error('config_missing', __('Missing Google Config', 'google-reviews-pro'));
        }

        $fields = 'reviews,rating,formatted_address,international_phone_number,geometry,name';
        $url = sprintf("https://maps.googleapis.com/maps/api/place/details/json?place_id=%s&fields=%s&key=%s", $place_id, $fields, $api_key);
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (($body['status'] ?? '') !== 'OK') {
            return new \WP_Error('api_error', $body['status'] ?? __('Unknown API Error', 'google-reviews-pro'));
        }

        $result = $body['result'];

        $normalized_reviews = [];
        if (!empty($result['reviews'])) {
            foreach ($result['reviews'] as $review) {
                $unique_id = md5(($review['author_name'] ?? '') . ($review['time'] ?? ''));
                $normalized_reviews[] = [
                    'external_id' => $unique_id,
                    'author_name' => $review['author_name'] ?? 'Anonymous',
                    'photo_url'   => $review['profile_photo_url'] ?? '',
                    'author_url'  => $review['author_url'] ?? '',
                    'rating'      => $review['rating'] ?? 5,
                    'text'        => $review['text'] ?? '',
                    'time'        => $review['time'] ?? time(),
                    'source'      => 'google'
                ];
            }
        }

        $meta = [
            'name'    => $result['name'] ?? '',
            'address' => $result['formatted_address'] ?? '',
            'phone'   => $result['international_phone_number'] ?? '',
            'lat'     => $result['geometry']['location']['lat'] ?? '',
            'lng'     => $result['geometry']['location']['lng'] ?? '',
        ];

        return [
            'reviews' => $normalized_reviews,
            'meta'    => $meta
        ];
    }

    private function fetch_serpapi(): \WP_Error|array
    {
        $api_key = $this->options['serpapi_key'] ?? '';
        $data_id = $this->options['serpapi_data_id'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('config_missing', __('Missing SerpApi Key', 'google-reviews-pro'));
        }

        if (empty($data_id)) {
            return new \WP_Error('config_missing', __('Missing SerpApi Data ID', 'google-reviews-pro'));
        }

        $all_reviews = [];
        $meta_captured = false;
        $meta = [];
        $next_page_token = null;
        $page_count = 0;
        $max_pages_setting = (int) ($this->options['serpapi_pages'] ?? 5);
        $max_pages = max(1, min(50, $max_pages_setting));

        do {
            $url = sprintf("https://serpapi.com/search.json?engine=google_maps_reviews&data_id=%s&api_key=%s", $data_id, $api_key);

            if ($next_page_token) {
                $url .= "&next_page_token=" . $next_page_token;
            }

            $response = wp_remote_get($url, ['timeout' => 20]);

            if (is_wp_error($response)) {
                break;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                return new \WP_Error('serpapi_error', $body['error']);
            }

            if (!$meta_captured && !empty($body['place_info'])) {
                $info = $body['place_info'];
                $meta = [
                    'name'    => $info['title'] ?? '',
                    'address' => $info['address'] ?? '',
                    'phone'   => $info['phone'] ?? '', // SerpApi понякога не връща телефон тук, но често го има
                    'lat'     => $info['gps_coordinates']['latitude'] ?? '',
                    'lng'     => $info['gps_coordinates']['longitude'] ?? '',
                ];
                $meta_captured = true;
            }

            if (empty($body['reviews'])) {
                break;
            }

            foreach ($body['reviews'] as $review) {
                if (!empty($review['review_id'])) {
                    $unique_id = $review['review_id'];
                } elseif (!empty($review['link'])) {
                    $unique_id = md5($review['link']);
                } else {
                    $unique_id = md5(($review['user']['name'] ?? 'Anon') . ($review['date'] ?? '') . substr($review['snippet'] ?? '', 0, 20));
                }

                $all_reviews[] = [
                    'external_id' => $unique_id,
                    'author_name' => $review['user']['name'] ?? __('Anonymous', 'google-reviews-pro'),
                    'photo_url'   => $review['user']['thumbnail'] ?? '',
                    'author_url'  => $review['link'] ?? '',
                    'rating'      => $review['rating'] ?? 5,
                    'text'        => $review['snippet'] ?? '',
                    'time'        => isset($review['iso_date']) ? strtotime($review['iso_date']) : time(),
                    'source'      => 'serpapi'
                ];
            }

            $next_page_token = $body['serpapi_pagination']['next_page_token'] ?? null;
            $page_count++;

            if ($next_page_token) {
                sleep(1);
            }

        } while ($next_page_token && $page_count < $max_pages);

        return [
            'reviews' => $all_reviews,
            'meta'    => $meta
        ];
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
        $site_name   = get_bloginfo('name');
        $count       = count($reviews);

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

    public function manage_cron(): void
    {
        $options = $this->getApiOptions();
        $is_enabled = !empty($options['auto_sync']);
        $frequency = $options['sync_frequency'] ?? 'weekly';

        // Remove old hook, to be sure there's no duplication
        // or old schedules when changing frequency.
        wp_clear_scheduled_hook('grp_daily_sync');

        if ($is_enabled) {
            wp_schedule_event(time(), $frequency, 'grp_daily_sync');
        }
    }

    public function add_custom_cron_schedules(array $schedules): array
    {
        $schedules['monthly'] = [
            'interval' => 2592000, // 30 days in seconds
            'display'  => __('Once Monthly', 'google-reviews-pro')
        ];
        return $schedules;
    }
}
