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

    public function fetch(string $id): \WP_Error|array
    {
        $api_key = $this->options['serpapi_key'] ?? '';
        $id = $id ?: $this->options['serpapi_data_id'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('config_missing', __('Missing SerpApi Key', 'google-reviews-pro'));
        }

        if (empty($id)) {
            return new \WP_Error('config_missing', __('Missing Data ID', 'google-reviews-pro'));
        }

        $all_reviews = [];
        $meta_captured = false;
        $meta = [];
        $next_page_token = null;
        $page_count = 0;
        $max_pages_setting = (int) ($this->options['serpapi_pages'] ?? 5);
        $max_pages = max(1, min(50, $max_pages_setting));
        $locales = explode('_', get_locale());
        $locale = strtolower($locales[0]);

        do {
            $url = sprintf(
                "https://serpapi.com/search.json?engine=google_maps_reviews&data_id=%s&api_key=%s&hl=%s",
                $id,
                $api_key,
                $locale
            );

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
                    'name' => $info['title'] ?? null,
                    'address' => $info['address'] ?? null,
                    'phone' => $info['phone'] ?? null,
                    'lat' => $info['gps_coordinates']['latitude'] ?? null,
                    'lng' => $info['gps_coordinates']['longitude'] ?? null,
                    'price_level' => $this->normalize_price($info['price'] ?? null), // $1–10
                    'maps_url' => $info['url'] ?? null,
                    'website' => $info['website'] ?? get_home_url(),
                    'periods' => $this->normalize_hours($info['operating_hours'] ?? $info['hours'] ?? null),
                    'weekday_text'=> null,
                    'icon' => $place['thumbnail'] ?? null,
                    'rating' => $info['rating'] ?? 0,
                    'count' => $info['reviews'] ?? 0,
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
                    'photo_url' => $review['user']['thumbnail'] ?? '',
                    'author_url' => $review['link'] ?? '',
                    'rating' => $review['rating'] ?? 5,
                    'text' => $review['snippet'] ?? '',
                    'time' => isset($review['iso_date']) ? strtotime($review['iso_date']) : time(),
                    'source' => 'serpapi'
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
            'meta' => $meta
        ];
    }

    public function fetch_business_info(string $query): \WP_Error|array
    {
        $api_key = $this->options['serpapi_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('api_error', __('Missing API Key.', 'google-reviews-pro'));
        }

        $query = trim($query);

        if (empty($query)) {
            return new \WP_Error('api_error', __('Empty query.', 'google-reviews-pro'));
        }

        $locales = explode('_', get_locale());

        $url = sprintf(
            'https://serpapi.com/search.json?engine=google_maps&q=%s&api_key=%s&type=search&hl=%s',
            urlencode($query),
            $api_key,
            strtolower($locales[0])
        );
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $place = $body['local_results'][0] ?? $body['place_results'] ?? null;
        $response_meta = $body['search_metadata'] ?? null;

        if (!empty($place)) {
            $result = [
                'place_id' => $place['place_id'],
                'data_id' => $place['data_id'],
                'name' => $place['title'],
                'address' => $place['address'] ?? null,
                'phone' => $place['phone'] ?? null,
                'lat' => $place['gps_coordinates']['latitude'] ?? null,
                'lng' => $place['gps_coordinates']['longitude'] ?? null,
                'price_level' => $this->normalize_price($place['price_level'] ?? null), // $1–10
                'maps_url' => $response_meta['google_maps_url'] ?? null,
                'website' => $place['website'] ?? get_home_url(),
                'periods' => $this->normalize_hours($place['operating_hours'] ?? $place['hours'] ?? null),
                'weekday_text'=> $place['open_state'] ?? null,
                'icon' => $place['thumbnail'] ?? null,
                'rating' => $place['rating'] ?? 0,
                'count' => $place['reviews'] ?? 0,
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

    /**
     * Normalizes the price to a number from 0 to 4.
     * Supports formats: 2, "$$", "$1-10", "€€€"
     */
    private function normalize_price(?string $price): ?int
    {
        if (empty($price)) {
            return null;
        }

        // if it's a number (Google API style)
        if (is_numeric($price)) {
            return (int)$price;
        }

        // If it's a string, count the currency symbols
        // Ex: "$$" -> 2, "$1-10" -> 1, "€€€" -> 3
        preg_match_all('/[\$\€\£\¥\₩]/', (string)$price, $matches);
        $count = count($matches[0]);

        if ($count > 0) {
            // Limit to 4 (Google max)
            return min(4, $count);
        }

        return null;
    }

    private function normalize_hours(?array $hours): array
    {
        if (empty($hours)) {
            return [];
        }

        if (isset($hours[0])) {
            $working_hours = [];
            foreach ($hours as $hourData) {
                foreach ($hourData as $day => $hour) {
                    $working_hours[strtolower($day)] = $hour;
                }
            }
            return $working_hours;
        } else {
            return $hours;
        }
    }
}