<?php

declare(strict_types=1);

namespace GRP\Ajax;

use GRP\Api\Handler as ApiHandler;
use GRP\Frontend\Display;

readonly class Handler
{
    public function __construct(private ApiHandler $api, private Display $display)
    {
        add_action('wp_ajax_grp_refresh', [$this, 'handle']);
        add_action('wp_ajax_grp_find_business', [$this, 'handle_find']);
        add_action('wp_ajax_grp_load_more', [$this, 'handle_load_more']);
        add_action('wp_ajax_nopriv_grp_load_more', [$this, 'handle_load_more']);
        add_action('wp_ajax_grp_delete_location', [$this, 'handle_delete_location']);
        add_action('wp_ajax_grp_get_location_details', [$this, 'handle_get_location_details']);
        add_action('wp_ajax_grp_get_schema_details', [$this, 'handle_get_schema_details']);
        add_action('wp_ajax_grp_save_api_location_data', [$this, 'handle_save_api_location_data']);
        add_action('wp_ajax_grp_get_nonce', [$this, 'handle_get_nonce']);
        add_action('wp_ajax_nopriv_grp_get_nonce', [$this, 'handle_get_nonce']);
    }

    public function handle_get_nonce(): void
    {
        wp_send_json_success(['nonce' => wp_create_nonce('grp_nonce')]);
    }

    public function handle_load_more(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'google-reviews-pro')]);
        }

        $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $options = $this->api->get_api_options();
        $reviews_limit = absint($options['grp_review_limit'] ?? 3);
        $limit_req = isset($_POST['limit']) ? absint($_POST['limit']) : $reviews_limit;
        $limit = max(1, min(10, $limit_req));
        $req_layout = isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : '';

        if (!in_array($req_layout, ['grid', 'list', 'badge', 'slider'])) {
            $req_layout = '';
        }

        $layout = $req_layout ?: $options['grp_layout'] ?? 'grid';
        $reviews = $this->api->get_reviews($limit, $offset, $place_id);
        $total = $this->api->count_total_reviews($place_id);

        if (empty($reviews)) {
            wp_send_json_error(['message' => __('No more reviews', 'google-reviews-pro')]);
        }

        $html = '';
        foreach ($reviews as $review) {
            $html .= $this->display->render_card($review, $layout);
        }

        $has_more = ($offset + $limit) < $total;

        wp_send_json_success(['html' => $html, 'has_more' => $has_more]);
    }

    public function handle(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $sync_result = $this->api->sync_reviews();

        if (is_wp_error($sync_result)) {
            wp_send_json_error($sync_result->get_error_message());
        }

        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), time());
        wp_send_json_success(['last_sync' => $formatted_time]);
    }

    public function handle_find(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $query = sanitize_text_field($_POST['query'] ?? '');
        $api_options = $this->api->get_api_options();
        $source = $api_options['data_source'] ?? '';
        $api_key = '';
        if ($source === 'google') {
            $api_key = $api_options['google_api_key'];
        } elseif ($source === 'serpapi') {
            $api_key = $api_options['serpapi_key'];
        } elseif ($source === 'scrapingdog') {
            $api_key = $api_options['scrapingdog_api_key'];
        }

        if (!$query || !$source || !$api_key) {
            wp_send_json_error('Missing parameters');
        }

        $result = [];

        foreach ($this->api->get_api_handlers() as $apiHandler) {
            if (!$apiHandler->supports($source)) {
                continue;
            }

            $result = $apiHandler->fetch_business_info($query);
            break;
        }

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        if (!empty($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(__('Unknown error or no data found.', 'google-reviews-pro'));
        }
    }

    public function handle_delete_location(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $place_id = sanitize_text_field($_POST['place_id'] ?? '');

        if (empty($place_id)) {
            wp_send_json_error(__('Invalid Place ID.', 'google-reviews-pro'));
        }

        $result = $this->api->delete_location($place_id);

        wp_send_json_success([
            'message' => sprintf(
                __('Location deleted. Removed %d reviews and %d images.', 'google-reviews-pro'),
                $result['reviews_deleted'],
                $result['images_deleted']
            )
        ]);
    }

    public function handle_get_location_details(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $place_id = sanitize_text_field($_POST['place_id'] ?? '');

        if (empty($place_id)) {
            wp_send_json_error(__('Invalid Place ID.', 'google-reviews-pro'));
        }

        if ($meta = $this->api->get_location_metadata($place_id)) {
            if (isset($meta['updated'])) {
                $meta['updated_human'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $meta['updated']);
            }
            wp_send_json_success($meta);
        } else {
            wp_send_json_error(__('No data found for this location.', 'google-reviews-pro'));
        }
    }

    public function handle_get_schema_details(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $place_id = sanitize_text_field($_POST['place_id'] ?? '');

        if (empty($place_id)) {
            wp_send_json_error(__('Invalid Place ID.', 'google-reviews-pro'));
        }

        $debug_info = $this->display->get_schema_debug_info($place_id);

        wp_send_json_success($debug_info);
    }

    public function handle_save_api_location_data(): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized action.', 'google-reviews-pro'));
        }

        $post_data = $_POST['data'] ?? null;

        if (empty($post_data)) {
            wp_send_json_error(__('Invalid API data.', 'google-reviews-pro'));
        }

        $place_id = sanitize_text_field($post_data['place_id'] ?? '');

        if (empty($place_id)) {
            wp_send_json_error(__('Invalid Place ID.', 'google-reviews-pro'));
        }

        $data_id = sanitize_text_field($post_data['data_id'] ?? '');
        $name = sanitize_text_field($post_data['name'] ?? '');
        $address = sanitize_text_field($post_data['address'] ?? '');
        $phone = sanitize_text_field($post_data['phone'] ?? '');
        $coordinates = sanitize_text_field($post_data['coordinates'] ?? '');
        $lat = null;
        $lng = null;

        if (!empty($coordinates)) {
            $cords = explode(' / ', $coordinates);
            $lat = $cords[0] ?? null;
            $lng = $cords[1] ?? null;
        }

        $price_lvl = sanitize_text_field($post_data['price_lvl'] ?? '');
        $rating = sanitize_text_field($post_data['rating'] ?? '');
        $reviews_count = sanitize_text_field($post_data['reviews_count'] ?? '');
        $working_days = $post_data['working_days'] ?? [];
        array_walk($working_days, 'sanitize_text_field');

        $meta = [];
        $meta['count'] = (int)$reviews_count;

        if (!empty($data_id)) {
            $meta['data_id'] = $data_id;
        }

        if (!empty($name)) {
            $meta['name'] = $name;
        }

        if (!empty($address)) {
            $meta['address'] = $address;
        }

        if (!empty($phone)) {
            $meta['phone'] = $phone;
        }

        if (!empty($price_lvl)) {
            $meta['price_level'] = $price_lvl;
        }

        if (!empty($lat)) {
            $meta['lat'] = $lat;
        }

        if (!empty($lng)) {
            $meta['lng'] = $lng;
        }

        if (!empty($rating)) {
            $meta['rating'] = (float)$rating;
        }

        $meta['periods'] = $working_days;

        $source = $this->api->get_api_options()['data_source'] ?? '';
        $this->api->save_location_metadata($place_id, $meta);
        $handlers = $this->api->get_api_handlers();
        $data = null;

        foreach ($handlers as $handler) {
            if (!$handler->supports($source)) {
                continue;
            }

            $id = $meta['data_id'] ?? $place_id;
            $data = $handler->fetch($id);
            break;
        }

        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        if ($data === null) {
            wp_send_json_error(__('No reviews found.', 'google-reviews-pro'));
        }

        $stats = $this->api->save_reviews($data['reviews'] ?? [], $place_id);

        wp_send_json_success([
            'redirect_url' => admin_url('options-general.php?page=grp-settings&sync-success=1'),
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
        ]);
    }
}
