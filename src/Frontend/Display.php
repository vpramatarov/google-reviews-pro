<?php

declare(strict_types=1);

namespace GRP\Frontend;

use GRP\Api\Handler as ApiHandler;
use GRP\Core\SeoIntegrator;
use GRP\Frontend\Layout\Badge;
use GRP\Frontend\Layout\Grid;
use GRP\Frontend\Layout\LayoutRender;
use GRP\Frontend\Layout\ListLayout;
use GRP\Frontend\Layout\Slider;

readonly class Display
{
    /** @var LayoutRender[] */
    private array $layoutRender;

    public function __construct(private ApiHandler $api, private SeoIntegrator $seo)
    {
        $this->layoutRender = [
            new Grid(),
            new ListLayout(),
            new Badge(),
            new Slider()
        ];

        add_shortcode('google_reviews', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        wp_enqueue_style('grp-css', plugin_dir_url(dirname(__DIR__)) . 'assets/css/style.css');
        wp_register_script(
                'grp-js',
                plugin_dir_url(dirname(__DIR__)) . 'assets/js/scripts.js',
                ['jquery'],
                false,
                ['in_footer' => true, 'strategy' => 'async']
        );

        $options = $this->api->getApiOptions();
        $limit = absint($options['grp_review_limit'] ?? 3);
        if ($limit < 1) $limit = 3;
        if ($limit > 5) $limit = 5;

        wp_localize_script( 'grp-js', 'gprJs', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'buttonText' => __('Load More', 'google-reviews-pro'),
            'loadingText' => __('Loading...', 'google-reviews-pro'),
            'reviewsLimit' => $limit,
        ]);

        $text_color     = sanitize_hex_color($options['grp_text_color'] ?? '#333333');
        $bg_color       = sanitize_hex_color($options['grp_bg_color'] ?? '#ffffff');
        $accent_color   = sanitize_hex_color($options['grp_accent_color'] ?? '#4285F4');
        $btn_text_color = sanitize_hex_color($options['grp_btn_text_color'] ?? '#ffffff');

        $custom_css = "
            .grp-grid {
                display: grid;
                gap: 20px;
                grid-template-columns: repeat({$limit}, 1fr);
            }
            
            /* Responsive: Tablet (2 columns, if limit > 1) */
            @media (max-width: 900px) {
                .grp-grid {
                    grid-template-columns: repeat(" . ($limit > 1 ? 2 : 1) . ", 1fr);
                }
            }

            /* Responsive: Mobile (1 column always) */
            @media (max-width: 600px) {
                .grp-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .grp-grid .grp-card, 
            .grp-list-view .grp-card,
            .grp-slider-track .grp-card {
                background-color: {$bg_color} !important;
                color: {$text_color} !important;
            }
            .grp-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
            .grp-profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #e5e7eb; }
            .grp-header-info strong { color: {$text_color} !important; font-size: 1em; font-weight: 600; }
            .grp-stars { display: flex; gap: 2px; font-size: 14px; }
            .grp-star { color: #e0e0e0; }
            .grp-star.filled { color: #fbbc04; }
            .grp-read-more-btn { color: {$accent_color} !important; font-size: 0.9em; cursor: pointer; display: inline-block; margin-top: 5px; }
            
            /* Footer Buttons */
            .grp-write-btn { background-color: {$accent_color} !important; border-color: {$accent_color} !important; color: {$btn_text_color} !important; }
            .grp-write-btn:hover { opacity: 0.9; }
            .grp-view-all-link { color: {$accent_color} !important; font-size: 0.9em; text-decoration: none; margin-top: 8px; display: inline-block; }
            
            /* LOAD MORE BUTTON */
            .grp-load-more-container { text-align: center; margin: 20px 0; }
            .grp-load-more-btn {
                background: transparent;
                border: 2px solid {$accent_color};
                color: {$accent_color};
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s;
            }
            .grp-load-more-btn:hover {
                background: {$accent_color};
                color: #fff;
            }
            .grp-load-more-btn.loading { opacity: 0.6; cursor: wait; }

            /* Layouts */
            .grp-list-view { display: flex; flex-direction: column; gap: 20px; }
            .grp-list-view .grp-card { width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .grp-slider-wrapper { position: relative; width: 100%; }
            .grp-slider-track { display: flex; gap: 20px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; padding-bottom: 10px; scrollbar-width: none; }
            .grp-slider-track::-webkit-scrollbar { display: none; }
            .grp-slider-track .grp-card { min-width: 300px; max-width: 300px; scroll-snap-align: start; flex-shrink: 0; }
            .grp-slider-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: #fff; border: 1px solid #ddd; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .grp-slider-arrow.prev { left: -15px; }
            .grp-slider-arrow.next { right: -15px; }
            
            /* Badge */
            .grp-badge-trigger { position: fixed; bottom: 20px; right: 80px; background: #fff; padding: 10px 15px; border-radius: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; cursor: pointer; z-index: 9998; border: 1px solid #eee; transition: transform 0.2s; }
            .grp-badge-trigger:hover { transform: translateY(-2px); }
            .grp-badge-icon { width: 24px; height: 24px; }
            .grp-badge-modal { display: none; position: fixed; bottom: 80px; right: 20px; width: 320px; max-height: 500px; background: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.2); border-radius: 8px; z-index: 9999; overflow-y: auto; padding: 15px; }
            .grp-badge-modal.open { display: block; }
            .grp-badge-close { position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 20px; font-weight: bold; }
        ";

        wp_add_inline_style('grp-css', $custom_css);
    }

    /**
     * @param array{"place_id"?: string, "layout"?: 'grid'|'list'|'badge'|'slider'} $atts
     */
    public function render_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['place_id' => '', 'layout' => ''], $atts, 'google_reviews');
        $specific_place_id = sanitize_text_field($atts['place_id']);

        if (!is_string($atts['layout']) || !in_array($atts['layout'], ['grid', 'list', 'badge', 'slider'])) {
            $atts['layout'] = '';
        }

        $specific_layout = sanitize_text_field($atts['layout']);
        $stats = $this->api->get_aggregate_stats($specific_place_id);
        $total_reviews = $stats['reviewCount'];

        if ($total_reviews < 1) {
            return '<p>' . __('No reviews found yet.', 'google-reviews-pro') . '</p>';
        }

        $options = $this->api->getApiOptions();
        $limit = absint($options['grp_review_limit'] ?? 3);
        if ($limit < 1) {
            $limit = 3;
        }

        if ($limit > 5) {
            $limit = 5;
        }

        $source = $options['data_source'] ?? 'cpt';
        $global_place_id = $options['place_id'] ?? '';
        $place_id = !empty($specific_place_id) ? $specific_place_id : $global_place_id;
        $layout = $specific_layout ?: $options['grp_layout'] ?? 'grid';

        if ($layout === 'slider') {
            // if the layout is slider we add more items, so there's something to slide...
            $limit += $limit;
        }

        $reviews = $this->api->get_reviews($limit, 0, $specific_place_id);

        $html = '';

        foreach ($this->layoutRender as $layoutRender) {
            if (!$layoutRender->supports($layout)) {
                continue;
            }

            $html .= $layoutRender->render($reviews, $stats, $limit, $place_id, $source);
        }

        $html .= $this->generate_json_ld($reviews, $specific_place_id, $stats);
        wp_enqueue_script('grp-js'); // enqueue js file
        return $html;
    }

    public function render_card(array $review, string $layout): string
    {
        foreach ($this->layoutRender as $layoutRender) {
            if (!$layoutRender->supports($layout)) {
                continue;
            }

            return $layoutRender->render_card($review);
        }

        return '';
    }

    /**
     * @param array<int,array{"rating": int, "author_name": string, "text": string, "time": string}>|array{} $reviews
     * @param array{"reviewCount": int, "ratingValue": float} $stats
     */
    private function generate_json_ld(array $reviews, string $current_place_id, array $stats): string
    {
        if (empty($reviews)) {
            return '';
        }

        $options = $this->api->getApiOptions();
        $seo_data = $this->seo->get_local_data();
        $auto_meta = !empty($current_place_id) ? $this->api->get_location_metadata($current_place_id) : null;
        $global_place_id = $options['place_id'] ?? '';
        $default_name = !empty($seo_data['name']) ? $seo_data['name'] : ($options['grp_business_name'] ?: get_bloginfo('name'));
        $default_addr = !empty($seo_data['address']) ? $seo_data['address'] : ($options['grp_address'] ?? '');
        $default_phone = !empty($seo_data['phone']) ? $seo_data['phone'] : ($options['grp_phone'] ?? '');
        $default_lat = !empty($seo_data['lat']) ? $seo_data['lat'] : ($options['grp_latitude'] ?? '');
        $default_lng = !empty($seo_data['lng']) ? $seo_data['lng'] : ($options['grp_longitude'] ?? '');

        /**
         * Default: Secondary location, but not yet synced (no metadata).
         * Safety Mode: We show the name, but hide the address/phone to avoid showing those of the central office.
         */
        $site_name = $default_name;
        $address = '';
        $phone = '';
        $lat = '';
        $lng = '';

        if ($auto_meta) {
            /**
             * We have automatic data from Google for this specific location (Sync passed successfully).
             * This is the ideal case for Multi-Location.
             */
            $site_name = !empty($auto_meta['name']) ? $auto_meta['name'] : $default_name;
            $address   = $auto_meta['address'];
            $phone     = $auto_meta['phone'];
            $lat       = $auto_meta['lat'];
            $lng       = $auto_meta['lng'];
        } elseif (empty($current_place_id) || $current_place_id === $global_place_id) {
            /**
             * This is the main location (or ID is not set).
             * We use the global settings (from Settings or SEO Plugin)
             */
            $address   = $default_addr;
            $phone     = $default_phone;
            $lat       = $default_lat;
            $lng       = $default_lng;
        }

        $price_range = !empty($seo_data['price_range']) ? $seo_data['price_range'] : ($options['grp_price'] ?? '$$');
        $site_url = get_home_url();
        $favicon_url = get_site_icon_url();
        $schema_reviews = [];

        foreach ($reviews as $review) {
            $schema_reviews[] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) ($review['rating'] ?? 5),
                    'bestRating' => '5',
                    'worstRating' => '1'
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => strip_tags($review['author_name'])
                ],
                'reviewBody' => wp_strip_all_tags($review['text']),
                'datePublished' => date('Y-m-d', $review['time']),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $site_name
                ]
            ];
        }

        $schema_payload = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $site_name,
            'url' => $site_url,
            'image' => $favicon_url,
            'priceRange' => $price_range,
            'review' => $schema_reviews
        ];

        if (!empty($address)) {
            $schema_payload['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address
            ];
        }

        if (!empty($phone)) {
            $schema_payload['telephone'] = $phone;
        }

        if (!empty($lat) && !empty($lng)) {
            $schema_payload['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $lat,
                'longitude' => $lng
            ];
        }

        if ($stats) {
            $schema_payload['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $stats['ratingValue'],
                'reviewCount' => (string) $stats['reviewCount'],
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }

        return '<script type="application/ld+json">' . json_encode($schema_payload, JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
