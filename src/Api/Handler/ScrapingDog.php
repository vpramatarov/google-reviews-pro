<?php

declare(strict_types=1);

namespace GRP\Api\Handler;

class ScrapingDog implements ApiHandler
{
    private const string SOURCE = 'scrapingdog';
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function fetch(string $id): \WP_Error|array
    {
        $api_key = $this->options['scrapingdog_api_key'] ?? '';
        $id = $id ?: $this->options['serpapi_data_id'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('config_missing', __('Missing ScrapingDog API Key', 'google-reviews-pro'));
        }

        if (empty($id)) {
            return new \WP_Error('config_missing', __('Missing Data ID', 'google-reviews-pro'));
        }

        $all_reviews = [];
        $meta = [];
        $next_page_token = null;
        $page_count = 0;

        // We use the page setting (same as for SerpApi or 5 by default)
        $max_pages_setting = (int) ($this->options['serpapi_pages'] ?? 5);
        $max_pages = max(1, min(20, $max_pages_setting));

        do {
            // Documentation: https://docs.scrapingdog.com/google-maps-api/google-maps-reviews-api
            $url = sprintf(
                "https://api.scrapingdog.com/google_maps/reviews?api_key=%s&data_id=%s",
                $api_key,
                $id
            );

            if ($next_page_token) {
                $url .= "&next_page_token=" . $next_page_token;
            }

            $response = wp_remote_get($url, ['timeout' => GRP_TIMEOUT]);

            if (is_wp_error($response)) {
                if ($page_count === 0) {
                    return $response;
                }
                break;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                if ($page_count === 0) {
                    return new \WP_Error('scrapingdog_error', $body['error']);
                }
                break;
            }

            // We only keep the metadata from the first page
            if ($page_count === 0 && !empty($body['locationDetails'])) {
                $info = $body['locationDetails'];
                $meta = [
                    'name' => $info['title'] ?? null,
                    'address' => $info['address'] ?? null,
                    'rating' => $info['rating'] ?? 0,
                    'count' => $info['reviews'] ?? 0,
                ];
            }

            if (!empty($body['reviews_results'])) {
                foreach ($body['reviews_results'] as $review) {
                    if (!empty($review['review_id'])) {
                        $unique_id = $review['review_id'];
                    } else {
                        // Fallback ID
                        $unique_id = md5(($review['user']['name'] ?? 'Anon') . ($review['date'] ?? '') . substr($review['body'] ?? '', 0, 20));
                    }

                    // ScrapingDog often returns a date as the text "2 months ago".
                    // strtotime works well for these formats.
                    $time_str = $review['date'] ?? '';
                    $timestamp = !empty($time_str) ? strtotime($time_str) : time();

                    $all_reviews[] = [
                        'external_id' => $unique_id,
                        'author_name' => $review['user']['name'] ?? __('Anonymous', 'google-reviews-pro'),
                        'photo_url' => $review['user']['thumbnail'] ?? '',
                        'author_url' => $review['user']['link'] ?? '',
                        'rating' => isset($review['rating']) ? (float)$review['rating'] : 5.0,
                        'text' => $review['snippet'] ?? '',
                        'time' => $timestamp,
                        'source' => 'scrapingdog'
                    ];
                }
            } else {
                break;
            }

            $next_page_token = $body['pagination']['next_page_token'] ?? null;
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

    public function fetch_business_info_by_place_id(string $place_id): \WP_Error|array
    {
        $api_key = $this->options['scrapingdog_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('api_error', __('Missing ScrapingDog API Key.', 'google-reviews-pro'));
        }

        $place_id = trim($place_id);

        if (empty($place_id)) {
            return new \WP_Error('api_error', __('Empty Place ID.', 'google-reviews-pro'));
        }

        $locales = explode('_', get_locale());
        $locale = strtolower($locales[0]);

        $url = sprintf(
            'https://api.scrapingdog.com/google_maps/places?api_key=%s&place_id=%s&country=%s',
            $api_key,
            $place_id,
            $locale
        );

        $response = wp_remote_get($url, ['timeout' => GRP_TIMEOUT]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $place = $body['place_results'] ?? null;

        if (!empty($place)) {
            $result = [
                'place_id' => $place['place_id'] ?? $place_id,
                'data_id' => $place['data_id'] ?? null,
                'name' => $place['title'] ?? null,
                'address' => $place['address'] ?? null,
                'phone' => $place['phone'] ?? null,
                'lat' => $place['gps_coordinates']['latitude'] ?? null,
                'lng' => $place['gps_coordinates']['longitude'] ?? null,
                'price_level' => $this->normalize_price($place['price'] ?? null),
                'maps_url' => $place['google_maps_url'] ?? null,
                'website' => $place['website'] ?? get_home_url(),
                'periods' => $this->normalize_hours($place['hours'] ?? null),
                'weekday_text'=> $place['open_state'] ?? null,
                'icon' => $place['thumbnail'] ?? null,
                'rating' => $place['rating'] ?? 0,
                'count' => $place['reviews'] ?? 0,
            ];
        } else {
            return new \WP_Error('api_error', $body['message'] ?? __('No business found via ScrapingDog.', 'google-reviews-pro'));
        }

        return $result;
    }

    public function fetch_business_info(string $query): \WP_Error|array
    {
        $api_key = $this->options['scrapingdog_api_key'] ?? '';

        if (empty($api_key)) {
            return new \WP_Error('api_error', __('Missing ScrapingDog API Key.', 'google-reviews-pro'));
        }

        $query = trim($query);

        if (empty($query)) {
            return new \WP_Error('api_error', __('Empty query.', 'google-reviews-pro'));
        }

        $locales = explode('_', get_locale());
        $locale = strtolower($locales[0]);

        $url = sprintf(
            'https://api.scrapingdog.com/google_maps?api_key=%s&query=%s&type=search&language=%s',
            $api_key,
            urlencode($query),
            $locale
        );

        $response = wp_remote_get($url, ['timeout' => GRP_TIMEOUT]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        // ScrapingDog usually returns an array of "search_results" or "place_results"
        $place = $body['search_results'][0] ?? $body['place_results'] ?? null;

        if (!empty($place)) {
            $result = [
                'name' => $place['title'] ?? null,
                'address' => $place['address'] ?? null,
                'phone' => $place['phone'] ?? null,
                'lat' => $place['gps_coordinates']['latitude'] ?? null,
                'lng' => $place['gps_coordinates']['longitude'] ?? null,
                'price_level' => $this->normalize_price($place['price'] ?? null), // $1–10
                'maps_url' => $place['google_maps_url'] ?? null,
                'website' => $place['website'] ?? get_home_url(),
                'periods' => $place['operating_hours'] ?? null,
                'weekday_text'=> $place['open_state'] ?? $place['hours'] ?? null,
                'icon' => $place['thumbnail'] ?? null,
                'rating' => $place['rating'] ?? 0,
                'count' => $place['reviews'] ?? 0,
            ];

            // ScrapingDog Reviews API requires a 'data_id' (CID), which is usually in the format 0x...
            // The Search API returns 'data_id' or 'place_id'. Priority is data_id.
            $result['place_id'] = $place['place_id'] ?? $place['data_id'] ?? null;
            $result['data_id'] = $place['data_id'] ?? null;
        } else {
            return new \WP_Error('api_error', $body['message'] ?? __('No business found via ScrapingDog.', 'google-reviews-pro'));
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