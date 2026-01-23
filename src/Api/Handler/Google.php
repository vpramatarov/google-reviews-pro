<?php

declare(strict_types=1);

namespace GRP\Api\Handler;

class Google implements ApiHandler
{

    private const string SOURCE = 'google';

    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function fetch(): \WP_Error|array
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

    public function supports(string $source): bool
    {
        return self::SOURCE === $source;
    }
}