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
        check_ajax_referer('grp_nonce', 'nonce');

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
        check_ajax_referer('grp_nonce', 'nonce');

        $sync_result = $this->api->sync_reviews();

        if (is_wp_error($sync_result)) {
            wp_send_json_error($sync_result->get_error_message());
        }

        $formatted_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), time());
        wp_send_json_success(['last_sync' => $formatted_time]);
    }

    public function handle_find(): void
    {
        check_ajax_referer('grp_nonce', 'nonce');

        $query = sanitize_text_field($_POST['query'] ?? '');
        $api_options = $this->api->getApiOptions();
        $source = $api_options['data_source'] ?? '';
        $api_key = ($source === 'google') ? $api_options['google_api_key'] : $api_options['serpapi_key'];

        if (!$query || !$source || !$api_key) {
            wp_send_json_error('Missing parameters');
        }

        $result = [];

        if ($source === 'google') {
            $url = sprintf(
                'https://maps.googleapis.com/maps/api/place/textsearch/json?query=%s&key=%s',
                urlencode($query),
                $api_key
            );
            $response = wp_remote_get($url);

            if (is_wp_error($response)) wp_send_json_error($response->get_error_message());

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($body['results'][0])) {
                $place = $body['results'][0];
                $result = [
                    'place_id' => $place['place_id'],
                    'name'     => $place['name'],
                    'address'  => $place['formatted_address'],
                    'lat'      => $place['geometry']['location']['lat'] ?? '',
                    'lng'      => $place['geometry']['location']['lng'] ?? '',
                ];
            } else {
                wp_send_json_error($body['error_message'] ?? __('No results found on Google.', 'google-reviews-pro'));
            }

        } elseif ($source === 'serpapi') {
            $url = sprintf(
                'https://serpapi.com/search.json?engine=google_maps&q=%s&api_key=%s&type=search',
                urlencode($query),
                $api_key
            );
            $response = wp_remote_get($url);

            if (is_wp_error($response)) wp_send_json_error($response->get_error_message());

            $body = json_decode(wp_remote_retrieve_body($response), true);

            $place = $body['local_results'][0] ?? $body['place_results'] ?? null;

            if (!empty($place)) {
                $result = [
                    'place_id' => $place['place_id'],
                    'data_id' => $place['data_id'],
                    'name'     => $place['title'],
                    'address'  => $place['address'] ?? '',
                    'lat'      => $place['gps_coordinates']['latitude'] ?? '',
                    'lng'      => $place['gps_coordinates']['longitude'] ?? '',
                ];
            } else {
                wp_send_json_error($body['error'] ?? __('No business found via SerpApi.', 'google-reviews-pro'));
            }
        }

        if (!empty($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(__('Unknown error or no data found.', 'google-reviews-pro'));
        }
    }
}
