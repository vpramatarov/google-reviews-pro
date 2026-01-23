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
    }

    public function handle_load_more(): void
    {
        if (!check_ajax_referer('grp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'google-reviews-pro')]);
        }

        $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $options = $this->api->getApiOptions();
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
        $api_options = $this->api->getApiOptions();
        $source = $api_options['data_source'] ?? '';
        $api_key = ($source === 'google') ? $api_options['google_api_key'] : $api_options['serpapi_key'];

        if (!$query || !$source || !$api_key) {
            wp_send_json_error('Missing parameters');
        }

        $result = [];

        foreach ($this->api->getApiHandlers() as $apiHandler) {
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
}
