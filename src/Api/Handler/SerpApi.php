<?php

declare(strict_types=1);

namespace GRP\Api\Handler;

class SerpApi implements ApiHandler
{

    private const string SOURCE = 'serpapi';

    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function fetch(): \WP_Error|array
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

    public function fetch_business_info(string $query): \WP_Error|array
    {
        $api_key = $this->options['serpapi_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('api_error', __('Missing API Key.', 'google-reviews-pro'));
        }

        $url = sprintf(
            'https://serpapi.com/search.json?engine=google_maps&q=%s&api_key=%s&type=search',
            urlencode($query),
            $api_key
        );
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

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
            return new \WP_Error('api_error', $body['error'] ?? __('No business found via SerpApi.', 'google-reviews-pro'));
        }

        return $result;
    }

    public function supports(string $source): bool
    {
        return self::SOURCE === $source;
    }
}