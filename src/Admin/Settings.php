<?php

declare(strict_types=1);

namespace GRP\Admin;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use GRP\Api\Handler as ApiHandler;
use GRP\Core\SeoIntegrator;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GRP\Utils\DateUtils;

readonly class Settings
{
    public function __construct(private SeoIntegrator $seo, private ApiHandler $api)
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_footer', [$this, 'render_admin_scripts']);
        add_action('admin_notices', [$this, 'show_sync_success_notices']);
    }

    public function show_sync_success_notices(): void
    {
        if (isset($_GET['sync-success']) && is_numeric($_GET['sync-success']) && (int)$_GET['sync-success'] === 1) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                __('API data saved successfully.', 'google-reviews-pro')
            );
        }
    }

    public function add_menu(): void
    {
        add_options_page(
            __('Google Reviews', 'google-reviews-pro'),
            __('Google Reviews', 'google-reviews-pro'),
            'manage_options',
            'grp-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('grp_group', 'grp_settings', [$this, 'sanitize']);

        add_settings_section('grp_main', '', null, 'grp-settings');
        add_settings_section('grp_locations', '', [$this, 'locations_section_desc'], 'grp-settings');
        add_settings_section('grp_filtering', '', null, 'grp-settings');
        add_settings_section('grp_styling', '', null, 'grp-settings');
        add_settings_section('grp_seo', '', [$this, 'seo_section_desc'], 'grp-settings');
        add_settings_section('grp_advanced', '', null, 'grp-settings');

        // --- TAB 1: API Sources ---
        add_settings_field(
            'data_source',
            __('Select Source', 'google-reviews-pro'),
            [$this, 'source_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'google_api_key',
            __('Google Places API Key', 'google-reviews-pro'),
            [$this, 'google_key_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'serpapi_key',
            __('SerpApi API Key', 'google-reviews-pro'),
            [$this, 'serpapi_key_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'scrapingdog_api_key',
            __('ScrapingDog API Key', 'google-reviews-pro'),
            [$this, 'scrapingdog_key_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'place_id',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Place ID', 'google-reviews-pro')),
            [$this, 'place_id_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'serpapi_data_id',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Data ID', 'google-reviews-pro')),
            [$this, 'serpapi_data_id_html'],
            'grp-settings',
            'grp_main'
        );

        // --- TAB 2: Find Business (finder_html rendered inline in render_page) ---

        // --- TAB 3: Sync & Pagination ---
        add_settings_field(
            'serpapi_pages',
            __('Max Pagination Pages', 'google-reviews-pro'),
            [$this, 'serpapi_pages_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'auto_sync',
            __('Enable Auto-Sync', 'google-reviews-pro'),
            [$this, 'auto_sync_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'sync_frequency',
            __('Sync Frequency', 'google-reviews-pro'),
            [$this, 'sync_frequency_html'],
            'grp-settings',
            'grp_main'
        );

        // --- TAB 4: Locations ---
        add_settings_field(
            'grp_stored_locations',
            __('Stored Locations', 'google-reviews-pro'),
            [$this, 'stored_locations_html'],
            'grp-settings',
            'grp_locations'
        );

        // --- TAB 5: Filtering ---
        add_settings_field(
            'grp_hide_empty',
            __('Hide reviews without text (Star-only ratings)', 'google-reviews-pro'),
            [$this, 'hide_empty_reviews_html'],
            'grp-settings',
            'grp_filtering'
        );

        add_settings_field(
            'grp_min_rating',
            __('Minimum Rating', 'google-reviews-pro'),
            [$this, 'min_rating_html'],
            'grp-settings',
            'grp_filtering'
        );

        add_settings_field(
            'grp_sort_order',
            __('Sort Order', 'google-reviews-pro'),
            [$this, 'sort_order_html'],
            'grp-settings',
            'grp_filtering'
        );

        // --- TAB 6: Styling ---
        add_settings_field(
            'grp_review_limit',
            __('Initial Reviews Limit', 'google-reviews-pro'),
            [$this, 'limit_html'],
            'grp-settings',
            'grp_styling'
        );

        add_settings_field(
            'grp_layout',
            __('Layout Style', 'google-reviews-pro'),
            [$this, 'layout_html'],
            'grp-settings',
            'grp_styling'
        );

        add_settings_field(
            'grp_text_color',
            __('Text Color', 'google-reviews-pro'),
            [$this, 'text_color_html'],
            'grp-settings',
            'grp_styling'
        );

        add_settings_field(
            'grp_bg_color',
            __('Card Background', 'google-reviews-pro'),
            [$this, 'bg_color_html'],
            'grp-settings',
            'grp_styling'
        );

        add_settings_field(
            'grp_accent_color',
            __('Links & Buttons',
               'google-reviews-pro'),
            [$this, 'accent_color_html'],
            'grp-settings',
            'grp_styling'
        );

        add_settings_field(
            'grp_btn_text_color',
            __('Button Text Color', 'google-reviews-pro'),
            [$this, 'btn_text_color_html'],
            'grp-settings',
            'grp_styling'
        );

        // --- TAB 7: SEO ---
        add_settings_field(
            'grp_business_name',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Business Name', 'google-reviews-pro')),
            [$this, 'business_name_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_latitude',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Latitude', 'google-reviews-pro')),
            [$this, 'latitude_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_longitude',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Longitude','google-reviews-pro')),
            [$this, 'longitude_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_address',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Business Address', 'google-reviews-pro')),
            [$this, 'address_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_phone',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Telephone', 'google-reviews-pro')),
            [$this, 'phone_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_price',
            sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Price Range', 'google-reviews-pro')),
            [$this, 'price_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_disable_seo_integration',
            __('SEO Plugin Integration', 'google-reviews-pro'),
            [$this, 'disable_seo_integration_html'],
            'grp-settings',
            'grp_seo'
        );

        // --- TAB 8: Advanced ---
        add_settings_field(
            'email_alerts',
            __('Email Notifications', 'google-reviews-pro'),
            [$this, 'email_alerts_html'],
            'grp-settings',
            'grp_advanced'
        );

        add_settings_field(
            'notification_email',
            __('Notification Email','google-reviews-pro'),
            [$this, 'notification_email_html'],
            'grp-settings',
            'grp_advanced'
        );

        add_settings_field(
            'wipe_on_uninstall',
            __('Uninstall Cleanup', 'google-reviews-pro'),
            [$this, 'wipe_html'],
            'grp-settings',
            'grp_advanced'
        );
    }

    public function sanitize(array $input): array
    {
        $clean = [
            'data_source' => sanitize_text_field($input['data_source']),
            'google_api_key' => sanitize_text_field($input['google_api_key'] ?? ''),
            'place_id' => sanitize_text_field($input['place_id'] ?? ''),
            'serpapi_data_id' => sanitize_text_field($input['serpapi_data_id'] ?? ''),
            'serpapi_key' => sanitize_text_field($input['serpapi_key'] ?? ''),
            'serpapi_pages' => absint($input['serpapi_pages'] ?? 5),
            'scrapingdog_api_key' => sanitize_text_field($input['scrapingdog_api_key'] ?? ''),
            'grp_review_limit' => max(1, min(GRP_MAX_REVIEW_LIMIT, absint($input['grp_review_limit'] ?? 3))),
            'auto_sync' => isset($input['auto_sync']) ? 1 : 0,
            'sync_frequency' => in_array($input['sync_frequency'], ['daily', 'weekly', 'monthly']) ? $input['sync_frequency'] : 'weekly',
            'grp_hide_empty' => isset($input['grp_hide_empty']) ? 1 : 0,
            'grp_min_rating' => absint($input['grp_min_rating'] ?? 0),
            'grp_sort_order' => sanitize_text_field($input['grp_sort_order'] ?? 'date_desc'),
            'grp_business_name' => sanitize_text_field($input['grp_business_name'] ?? ''),
            'grp_latitude' => sanitize_text_field($input['grp_latitude'] ?? ''),
            'grp_longitude' => sanitize_text_field($input['grp_longitude'] ?? ''),
            'grp_address' => sanitize_textarea_field($input['grp_address'] ?? ''),
            'grp_phone' => sanitize_text_field($input['grp_phone'] ?? ''),
            'grp_price' => sanitize_text_field($input['grp_price'] ?? ''),
            'grp_disable_seo_integration' => isset($input['grp_disable_seo_integration']) ? 1 : 0,
            'grp_layout' => sanitize_text_field($input['grp_layout'] ?? 'grid'),
            'grp_text_color' => sanitize_hex_color($input['grp_text_color'] ?? '#333333'),
            'grp_bg_color' => sanitize_hex_color($input['grp_bg_color'] ?? '#ffffff'),
            'grp_accent_color' => sanitize_hex_color($input['grp_accent_color'] ?? '#4285F4'),
            'grp_btn_text_color' => sanitize_hex_color($input['grp_btn_text_color'] ?? '#ffffff'),
            'email_alerts' => isset($input['email_alerts']) ? 1 : 0,
            'wipe_on_uninstall' => isset($input['wipe_on_uninstall']) ? 1 : 0,
        ];

        if ($clean['email_alerts'] === 1 && !empty($input['notification_email'])) {
            $notification_email = filter_var($input['notification_email'], FILTER_VALIDATE_EMAIL);
            if ($notification_email) {
                $clean['notification_email'] = $notification_email;
            } else {
                add_settings_error('grp_settings', 'invalid_email', __('The notification email provided is invalid. Saved with default admin email.', 'google-reviews-pro'));
                $clean['notification_email'] = '';
            }
        } else {
            $clean['notification_email'] = '';
        }

        return $clean;
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------

    public function source_html(): void
    {
        $val = get_option('grp_settings')['data_source'] ?? '';
        ?>
        <select name="grp_settings[data_source]" id="grp_data_source" required>
            <option value=""></option>
            <option value="google"     <?php selected($val, 'google'); ?>>Google Places API (Official)</option>
            <option value="serpapi"    <?php selected($val, 'serpapi'); ?>>SerpApi (Scraper)</option>
            <option value="scrapingdog" <?php selected($val, 'scrapingdog'); ?>>ScrapingDog (Scraper)</option>
            <option value="cpt"        <?php selected($val, 'cpt'); ?>>Manual Entry (Custom Post Type)</option>
        </select>
        <p class="description"><?php _e('Choose where to fetch reviews from.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function seo_section_desc(): void
    {
        $provider = $this->seo->get_active_provider();
        $is_active = $this->is_seo_integration_active(); // respects the toggle

        if ($provider && $is_active) {
            // Integration is ON and a SEO plugin was detected → read-only notice
            $name = match($provider) {
                'rank_math' => 'Rank Math SEO',
                'aioseo'    => 'All in One SEO',
                'seopress'  => 'SEOPress',
                'tsf'       => 'The SEO Framework',
                'yoast'     => 'Yoast SEO',
                default     => 'SEO Plugin'
            };
            echo '<div class="notice notice-info inline" style="margin-left:0; margin-bottom:15px;"><p>';
            printf(__('<strong>Integrated with %s:</strong> Business data is synced from your SEO plugin to prevent conflicts. Fields below are read-only.', 'google-reviews-pro'), $name);
            echo '</p></div>';

        } elseif ($provider && !$is_active) {
            // Integration is OFF but a SEO plugin IS present → warn the admin
            $name = match($provider) {
                'rank_math' => 'Rank Math SEO',
                'aioseo'    => 'All in One SEO',
                'seopress'  => 'SEOPress',
                'tsf'       => 'The SEO Framework',
                'yoast'     => 'Yoast SEO',
                default     => 'SEO Plugin'
            };
            echo '<div class="notice notice-warning inline" style="margin-left:0; margin-bottom:15px;"><p>';
            printf(__('<strong>%s detected but integration is disabled.</strong> Fields below use manual values only. Re-enable integration to sync automatically.', 'google-reviews-pro'), $name);
            echo '</p></div>';

        } else {
            // No SEO plugin detected (or irrelevant)
            echo '<p>' . __('Fill these fields to generate valid LocalBusiness Schema for Google.', 'google-reviews-pro') . '</p>';
        }
    }

    public function google_key_html(): void
    {
        $apiKey = esc_attr(get_option('grp_settings')['google_api_key'] ?? '');
        printf('<p><input type="text" id="grp_google_key" name="grp_settings[google_api_key]" value="%s" class="regular-text"></p>', $apiKey);
    }

    public function finder_html(): void
    {
        $google_api_key = esc_attr(get_option('grp_settings')['google_api_key'] ?? '');
        $serpapi_key = esc_attr(get_option('grp_settings')['serpapi_key'] ?? '');
        $scrapingdog_key = esc_attr(get_option('grp_settings')['scrapingdog_api_key'] ?? '');

        if (empty($google_api_key) && empty($serpapi_key) && empty($scrapingdog_key)) {
            echo '<p class="description">' . __('Please enter and save an API Key first to use the Business Finder.', 'google-reviews-pro') . '</p>';
            return;
        }

        printf('<div id="grp-finder-box">
                <input type="text" id="grp_finder_query" class="regular-text" placeholder="%s" value=""> <span>%s</span> <input type="text" id="grp_finder_place_id" class="regular-text" placeholder="%s" value="">
                <button type="button" id="grp-find-btn" class="button button-secondary">%s</button>
                <span class="spinner" id="grp-finder-spinner" style="float:none; margin:0;"></span>
                <button type="button" id="grp-save-api-btn" class="button button-secondary" style="display: none;">%s</button>
            </div>
            <p class="description" id="grp-finder-msg"></p>',
            __('Enter business name (e.g. Pizza Mario New York)', 'google-reviews-pro'),
            __('or', 'google-reviews-pro'),
            __('Enter Place ID', 'google-reviews-pro'),
            __('Search & Auto-fill', 'google-reviews-pro'),
            __('Sync Data', 'google-reviews-pro')
        );
        ?>
        <div id="grp_preview_wrapper">
            <h4><?php _e('Business Preview', 'google-reviews-pro'); ?></h4>
            <div class="grp-preview-card">
                <div style="margin-right: 15px;">
                    <img id="grp_prev_icon" src="" alt="Logo">
                </div>
                <div style="flex: 1;">
                    <div id="grp_prev_name"></div>
                    <div id="grp_prev_place_id" style="display: none"></div>
                    <div id="grp_prev_data_id" style="display: none"></div>

                    <div id="grp_prev_rating_wrapper">
                        <span id="grp_prev_rating"></span>
                        <div class="grp-stars"></div>
                        <span>(<span id="grp_prev_count"></span>)</span>
                    </div>

                    <div id="grp_prev_address" class="grp_prev_prop"></div>
                    <div id="grp_prev_phone" class="grp_prev_prop"></div>
                    <div id="grp_prev_coordinates" class="grp_prev_prop"></div>

                    <div style="font-size: 12px; display: flex; gap: 10px;">
                        <span id="grp_prev_price" style="color: #555;"></span>
                        <a id="grp_prev_map_link" href="#" target="_blank" style="text-decoration: none; color: #1a73e8;">View on Maps</a>
                    </div>

                    <div id="grp_prev_weekday" class="grp_prev_prop"></div>
                    <div id="grp_prev_periods" style="display: none"></div>
                </div>
                <div style="width: 24px; margin-left: 10px;">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                </div>
            </div>
        </div>
        <?php
    }

    public function place_id_html(): void
    {
        $options = $this->api->get_api_options();
        $val = $options['place_id'] ?? '';
        $locations = $this->api->get_stored_locations();
        ?>
        <select name="grp_settings[place_id]" id="grp_place_id" class="regular-text">
            <option value="">&mdash; <?php esc_html_e('Select a Place ID', 'google-reviews-pro'); ?> &mdash;</option>
            <?php
            if (!empty($locations)) {
                foreach ($locations as $location) {
                    $place_id = (string) ($location['place_id'] ?? '');
                    $name = (string) ($location['name'] ?? __('Unknown Location', 'google-reviews-pro'));
                    $count = (int) ($location['count'] ?? 0);

                    if ($place_id === '') {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr($place_id); ?>" <?php selected($val, $place_id); ?>>
                        <?php echo esc_html(sprintf('%s (%s - %d reviews)', $name, $place_id, $count)); ?>
                    </option>
                    <?php
                }
            }
            ?>
        </select>
        <?php
        printf(
            '<p class="description">%s <a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">%s</a>.</p>',
            __('Find your Place ID', 'google-reviews-pro'),
            __('here', 'google-reviews-pro')
        );
        ?>
        <p class="description">
            <?php esc_html_e('Select the fallback Place ID for the Google source. Only locations with synced reviews are shown.', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    public function serpapi_key_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['serpapi_key'] ?? '');
        printf('<input type="text" id="grp_serpapi_key" name="grp_settings[serpapi_key]" value="%s" class="regular-text">', $val);
    }

    public function serpapi_data_id_html(): void
    {
        $options = $this->api->get_api_options();
        $val = $options['serpapi_data_id'] ?? '';
        $locations = $this->api->get_stored_locations();
        ?>
        <select name="grp_settings[serpapi_data_id]" id="grp_serpapi_data_id" class="regular-text">
            <option value="">&mdash; <?php esc_html_e('Select a Data ID', 'google-reviews-pro'); ?> &mdash;</option>
            <?php
            if (!empty($locations)) {
                foreach ($locations as $location) {
                    $place_id = (string) ($location['data_id'] ?? '');
                    $name = (string) ($location['name'] ?? __('Unknown Location', 'google-reviews-pro'));
                    $count = (int) ($location['count'] ?? 0);

                    if ($place_id === '') {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr($place_id); ?>" <?php selected($val, $place_id); ?>>
                        <?php echo esc_html(sprintf('%s (%s - %d reviews)', $name, $place_id, $count)); ?>
                    </option>
                    <?php
                }
            }
            ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select the fallback Data ID for the SerpApi source.', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    public function serpapi_pages_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['serpapi_pages'] ?? 5);
        printf('<input type="number" name="grp_settings[serpapi_pages]" value="%d" min="1" max="50" class="small-text">', $val);
        echo '<p class="description">' . __('Limit the number of pages to fetch (1 page ≈ 10 reviews). Warning: High values increase sync time.', 'google-reviews-pro') . '</p>';
    }

    public function scrapingdog_key_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['scrapingdog_api_key'] ?? '');
        printf('<input type="text" id="grp_scrapingdog_key" name="grp_settings[scrapingdog_api_key]" value="%s" class="regular-text">', $val);
        echo '<p class="description">' . __('Get your API key from <a href="https://www.scrapingdog.com/" target="_blank">ScrapingDog</a>.', 'google-reviews-pro') . '</p>';
    }

    public function limit_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_review_limit'] ?? 3); ?>
        <input type="number" name="grp_settings[grp_review_limit]" value="<?php echo $val; ?>" min="1" max="5" class="small-text">
        <p class="description"><?php _e('Number of reviews to show initially. For Grid layout, this also determines the number of columns (Max 5).', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function auto_sync_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['auto_sync'] ?? 0);
        printf('<input type="checkbox" name="grp_settings[auto_sync]" value="1" %s>', checked(1, $val, false));
        echo '<p class="description">' . __('Automatically fetch reviews.', 'google-reviews-pro') . '</p>';
    }

    public function sync_frequency_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['sync_frequency'] ?? 'weekly'); ?>
        <select name="grp_settings[sync_frequency]" id="grp_sync_frequency">
            <option value="daily" <?php selected($val, 'daily'); ?>><?php _e('Daily', 'google-reviews-pro'); ?></option>
            <option value="weekly" <?php selected($val, 'weekly'); ?>><?php _e('Once Weekly', 'google-reviews-pro'); ?></option>
            <option value="monthly" <?php selected($val, 'monthly'); ?>><?php _e('Once Monthly', 'google-reviews-pro'); ?></option>
        </select>
        <p class="description"><?php _e('Choose how often the auto-sync should run.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function locations_section_desc(): void
    {
        echo '<p>' . __('This table shows all unique locations (Place IDs) found in your imported reviews database. Use these IDs in your shortcodes to display specific reviews.', 'google-reviews-pro') . '</p>';
    }

    public function hide_empty_reviews_html(): void
    {
        $hide_empty = esc_attr(get_option('grp_settings')['grp_hide_empty'] ?? 0);
        printf('<input type="checkbox" id="grp_hide_empty" name="grp_settings[grp_hide_empty]" value="1" %s>', checked(1, $hide_empty, false));
        echo '<p class="description">' . __('Enable this to show only reviews that contain actual comments.', 'google-reviews-pro') . '</p>';
    }

    public function stored_locations_html(): void
    {
        $locations = $this->api->get_stored_locations();
        $options = $this->api->get_api_options();
        $layout = esc_attr($options['grp_layout'] ?? 'grid');
        $global_place_id = $options['place_id'] ?? '';

        if (empty($locations)) {
            echo '<p class="description">' . __('No locations found yet. Sync some reviews first.', 'google-reviews-pro') . '</p>';
            return;
        }

        $qr_options = new QROptions([
            'version' => 5, // Balance between density and readability
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::L, // Low error correction for cleaner code
            'scale' => 20, // Pixel size
            'imageBase64' => true, // returns data:image/png;base64...
        ]);
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%; padding-left: 10px;"><?php _e('Place ID', 'google-reviews-pro'); ?></th>
                    <th style="width: 40%; padding-left: 10px;"><?php _e('Shortcode Snippet', 'google-reviews-pro'); ?></th>
                    <th style="width: 20%; padding-left: 10px;"><?php _e('QR Code', 'google-reviews-pro'); ?></th>
                    <th style="width: 15%; text-align: right; padding-right: 15px;"><?php _e('Actions', 'google-reviews-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($locations as $loc):
                $place_id = esc_html($loc['place_id']); ?>
                <tr>
                    <td>
                        <code><?php echo esc_html($place_id); ?></code>
                        <hr>
                        <code><?php echo esc_html($loc['name']); ?></code>
                        <hr>
                        <?php _e('Reviews Count', 'google-reviews-pro'); ?>: <strong><?php echo esc_html($loc['count']); ?></strong>
                    </td>
                    <td>
                        <div>
                            <input type="text"
                               readonly
                               class="regular-text"
                               value='[google_reviews place_id="<?php echo esc_attr($place_id); ?>" layout="<?php echo $layout; ?>"]'
                               style="width: 100%; font-family: monospace; background: #f9f9f9; color: #555;"
                               onclick="this.select();">
                        </div>
                        <div id="debug-place-<?php echo esc_attr($place_id); ?>" class="debug-place">
                            <hr>
                            <div class="debug-place-container">
                                <button type="button" class="button button-secondary grp-schema-btn" data-place-id="<?php echo esc_attr($place_id); ?>" style="margin-right: 5px;">
                                    <?php _e('Schema Check', 'google-reviews-pro'); ?>
                                </button>
                                <?php if ($is_main_location = (empty($place_id) || $place_id === $global_place_id)): ?>
                                    <button type="button" class="button button-secondary grp-seo-data-btn" data-place-id="<?php echo esc_attr($place_id); ?>" style="margin-right: 5px;">
                                        <?php _e('Seo Data Check', 'google-reviews-pro'); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="button button-secondary grp-details-btn" data-place-id="<?php echo esc_attr($place_id); ?>" style="margin-right: 5px;">
                                    <?php _e('Raw Data', 'google-reviews-pro'); ?>
                                </button>
                            </div>
                        </div>
                    </td>
                    <td style="text-align: center">
                        <?php
                        $review_url = 'https://search.google.com/local/writereview?placeid=' . esc_html($place_id);
                        try {
                            $qrcode = new QRCode($qr_options);
                            $qr_image_src = $qrcode->render($review_url);
                            printf('<p><img src="%s" alt="QR Code" style="display: block; width: 200px; height: 200px;"></p>', $qr_image_src);
                            printf('<div style="margin-top: 20px;">
                                    <p>
                                        <button class="button button-info open-qr-preview-mode" data-name="%s" data-url="%s">
                                            <span class="dashicons dashicons-exerpt-view"></span> %s
                                        </button>
                                    </p>
                                </div>',
                                esc_html($loc['name']),
                                esc_url($review_url),
                                __('Printable Card Preview', 'google-reviews-pro')
                            );
                        } catch (\Throwable $e) {
                            echo '<div class="notice notice-error inline"><p>' . sprintf(__('Error generating QR code: %s', 'google-reviews-pro'), $e->getMessage()) . '</p></div>';
                        }
                        ?>
                    </td>
                    <td style="text-align: right;">
                        <input type="radio" name="debug_data" data-place-id="<?php echo esc_attr($place_id); ?>" id="debug-data-<?php echo esc_attr($place_id); ?>">
                        <label for="debug-data-<?php echo esc_attr($place_id); ?>" class="button">
                            <?php _e('Show debug options', 'google-reviews-pro'); ?>
                        </label>
                        <hr>
                        <button type="button" class="button button-link-edit-place" data-place-id="<?php echo esc_attr($place_id); ?>">
                            <?php _e('Edit', 'google-reviews-pro'); ?>
                        </button>
                        <hr>
                        <button type="button" class="button button-link-refresh grp-refresh-loc-btn" data-place-id="<?php echo esc_attr($place_id); ?>">
                            <?php _e('Refresh', 'google-reviews-pro'); ?>
                        </button>
                        <hr>
                        <button type="button" class="button button-link-delete grp-delete-loc-btn" data-place-id="<?php echo esc_attr($place_id); ?>">
                            <?php _e('Delete', 'google-reviews-pro'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php $this->render_location_modals();
    }

    /** All modals used by the Locations tab. Extracted to keep stored_locations_html readable. */
    private function render_location_modals(): void
    {
        $days = array_values(DateUtils::get_days_of_week_l10n()); ?>
        <!-- Edit Location Modal -->
        <div id="grp-edit-place" class="modal-window">
            <div class="modal-wrapper">
                <div class="modal-header">
                    <h3 style="margin:0;"><?php _e('Edit Location', 'google-reviews-pro'); ?></h3>
                    <button type="button" class="grp-close-edit-location-modal button button-small">✕</button>
                </div>
                <div class="modal-content">
                    <div id="update-location-response" class="hidden"></div>
                    <div id="update-location-fields">
                        <ul>
                            <li>
                                <label for="edit-location-name">
                                    <?php _e('Business Name', 'google-reviews-pro'); ?>:
                                    <input type="text" name="location_name" id="edit-location-name" class="regular-text edit-location">
                                </label>
                            </li>
                            <li>
                                <label for="edit-location-address"><?php _e('Business Address', 'google-reviews-pro'); ?>:</label>
                                <textarea name="location_address" class="edit-location large-text" rows="3" id="edit-location-address"></textarea>
                            </li>
                            <li>
                                <label for="edit-location-phone">
                                    <?php _e('Phone', 'google-reviews-pro'); ?>:
                                    <input type="text" name="location_phone" id="edit-location-phone" class="regular-text edit-location">
                                </label>
                            </li>
                            <li>
                                <label for="edit-reviews-total-count">
                                    <?php _e('Reviews count', 'google-reviews-pro'); ?>:
                                    <input type="text" name="reviews_total_count" id="edit-reviews-total-count" class="regular-text edit-location">
                                </label>
                            </li>
                            <li>
                                <label for="edit-reviews-rating">
                                    <?php _e('Reviews rating', 'google-reviews-pro'); ?>:
                                    <input type="text" name="reviews_rating" id="edit-reviews-rating" class="regular-text edit-location">
                                </label>
                            </li>
                        </ul>

                        <hr style="margin: 12px 0;">
                        <h4 style="margin: 0 0 10px 0;"><?php _e('Working Hours (24h format)', 'google-reviews-pro'); ?></h4>
                        <p class="description" style="margin-bottom: 10px;">
                            <?php _e('Leave both fields empty to mark a day as Closed.', 'google-reviews-pro'); ?>
                        </p>

                        <table class="widefat fixed striped" id="edit-working-hours-table">
                            <thead>
                            <tr>
                                <th style="width:30%;"><?php _e('Day', 'google-reviews-pro'); ?></th>
                                <th style="width:35%;"><?php _e('Opens', 'google-reviews-pro'); ?> (HH:MM)</th>
                                <th style="width:35%;"><?php _e('Closes', 'google-reviews-pro'); ?> (HH:MM)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($days as $day) : ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst(__($day, 'google-reviews-pro'))); ?></td>
                                    <td>
                                        <input type="text"
                                           id="edit-hours-open-<?php echo esc_attr($day); ?>"
                                           class="edit-location-hours small-text"
                                           data-day="<?php echo esc_attr($day); ?>"
                                           data-type="open"
                                           maxlength="5"
                                           pattern="^([01]\d|2[0-3]):[0-5]\d$"
                                           style="width:70px;">
                                        <span>Ex.: 09:00</span>
                                    </td>
                                    <td>
                                        <input type="text"
                                           id="edit-hours-close-<?php echo esc_attr($day); ?>"
                                           class="edit-location-hours small-text"
                                           data-day="<?php echo esc_attr($day); ?>"
                                           data-type="close"
                                           maxlength="5"
                                           pattern="^([01]\d|2[0-3]):[0-5]\d$"
                                           style="width:70px;">
                                        <span>Ex.: 18:00</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <input name="place_id" type="hidden" id="edit-location-place-id">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-primary" id="grp-edit-loc-btn" disabled><?php _e('Update', 'google-reviews-pro'); ?></button>
                    <button type="button" class="button button-secondary grp-close-edit-location-modal"><?php _e('Close', 'google-reviews-pro'); ?></button>
                </div>
            </div>
        </div>

        <!-- Schema Check Modal -->
        <div id="grp-schema-modal" class="modal-window">
            <div class="modal-wrapper">
                <div class="modal-header">
                    <h3 style="margin:0;"><?php _e('Structured Data Source Analysis', 'google-reviews-pro'); ?></h3>
                    <button type="button" class="grp-close-schema button button-small">✕</button>
                </div>
                <div class="modal-content darker-bg">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Field', 'google-reviews-pro'); ?></th>
                                <th><?php _e('Current Value', 'google-reviews-pro'); ?></th>
                                <th><?php _e('Source', 'google-reviews-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="grp-schema-tbody"></tbody>
                    </table>
                    <p class="description" style="margin-top: 10px;">
                        <?php _e('Note: "SEO Plugin" has priority #1. "API Data" has priority #2. "Manual Settings" has priority #3.', 'google-reviews-pro'); ?>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-primary grp-close-schema"><?php _e('Close', 'google-reviews-pro'); ?></button>
                </div>
            </div>
        </div>

        <!-- Raw Data Modal -->
        <div id="grp-raw-data-modal" class="modal-window">
            <div class="modal-wrapper">
                <div class="modal-header">
                    <h3 style="margin:0;"><?php _e('Location Raw Data', 'google-reviews-pro'); ?></h3>
                    <button type="button" class="grp-close-modal button button-small">✕</button>
                </div>
                <div class="modal-content darker-bg">
                    <pre id="grp-raw-content"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-primary grp-close-modal"><?php _e('Close', 'google-reviews-pro'); ?></button>
                </div>
            </div>
        </div>

        <!-- Printable QR Card Modal -->
        <div id="grp-printable-qr-card-preview" class="modal-window">
            <div class="modal-wrapper">
                <div class="modal-header printable-qr-card-preview">
                    <h3 style="margin:0;"><?php _e('Printable Card Preview', 'google-reviews-pro'); ?></h3>
                    <button type="button" class="grp-close-printable-qr-modal button button-small">✕</button>
                </div>
                <div class="modal-content">
                    <?php printf('<p><strong>%s</strong></p>', __('Direct Link:', 'google-reviews-pro')); ?>
                    <p><input type="text" class="large-text" value="" readonly onclick="this.select();"></p>
                    <p class="description"><?php _e('Print this card and place it on your counter or tables.', 'google-reviews-pro'); ?></p>
                    <div id="grp-print-card">
                        <h2 style="color: #333; margin-top: 0;"><?php _e('Rate Us on Google', 'google-reviews-pro'); ?></h2>
                        <p style="color: #666;"><?php _e('Loving your experience at', 'google-reviews-pro'); ?> <br><strong id="grp-qr-code-business-name"></strong>?</p>
                        <div style="margin: 20px auto;">
                            <img src="" alt="QR Code" style="width: 150px; height: 150px;" id="grp-qr-code-img">
                        </div>
                        <p style="font-size: 12px; color: #999;"><?php _e('Scan with your phone camera', 'google-reviews-pro'); ?></p>
                        <div class="qr-code-rating"><span>★★★★★</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-primary" onclick="printCard()">
                        <span class="dashicons dashicons-printer"></span>
                        <?php _e('Print Card', 'google-reviews-pro'); ?>
                    </button>
                    <a href="" download="google-review-qr.png" class="button button-secondary" id="grp-google-review-qr-img">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Download QR Image', 'google-reviews-pro'); ?>
                    </a>
                    <button type="button" class="button button-primary grp-close-printable-qr-modal"><?php _e('Close', 'google-reviews-pro'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function min_rating_html(): void
    {
        $val = absint(get_option('grp_settings')['grp_min_rating'] ?? 0); ?>
        <select name="grp_settings[grp_min_rating]">
            <option value="0" <?php selected($val, 0); ?>><?php _e('Show All Reviews', 'google-reviews-pro'); ?></option>
            <option value="3" <?php selected($val, 3); ?>><?php _e('3 Stars & Up', 'google-reviews-pro'); ?></option>
            <option value="4" <?php selected($val, 4); ?>><?php _e('4 Stars & Up', 'google-reviews-pro'); ?></option>
            <option value="5" <?php selected($val, 5); ?>><?php _e('5 Stars Only', 'google-reviews-pro'); ?></option>
        </select>
        <p class="description"><?php _e('Reviews below this rating will be hidden from the frontend.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function sort_order_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_sort_order'] ?? 'date_desc'); ?>
        <select name="grp_settings[grp_sort_order]">
            <option value="date_desc" <?php selected($val, 'date_desc'); ?>><?php _e('Newest First', 'google-reviews-pro'); ?></option>
            <option value="date_asc" <?php selected($val, 'date_asc'); ?>><?php _e('Oldest First', 'google-reviews-pro'); ?></option>
            <option value="rating_desc" <?php selected($val, 'rating_desc'); ?>><?php _e('Highest Rated First', 'google-reviews-pro'); ?></option>
            <option value="rating_asc" <?php selected($val, 'rating_asc'); ?>><?php _e('Lowest Rated First', 'google-reviews-pro'); ?></option>
            <option value="random" <?php selected($val, 'random'); ?>><?php _e('Random', 'google-reviews-pro'); ?></option>
        </select>
        <p class="description"><?php _e('Choose the order in which reviews appear.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function layout_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_layout'] ?? 'grid'); ?>
        <select name="grp_settings[grp_layout]">
            <option value="grid" <?php selected($val, 'grid'); ?>><?php _e('Grid (Default)', 'google-reviews-pro'); ?></option>
            <option value="list" <?php selected($val, 'list'); ?>><?php _e('List View', 'google-reviews-pro'); ?></option>
            <option value="slider" <?php selected($val, 'slider'); ?>><?php _e('Slider / Carousel', 'google-reviews-pro'); ?></option>
            <option value="badge" <?php selected($val, 'badge'); ?>><?php _e('Floating Badge', 'google-reviews-pro'); ?></option>
        </select>
        <p class="description"><?php _e('Choose how the reviews should be displayed on your site.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function text_color_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_text_color'] ?? '#333333');
        printf('<input type="color" name="grp_settings[grp_text_color]" value="%s">', $val);
    }

    public function bg_color_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_bg_color'] ?? '#ffffff');
        printf('<input type="color" name="grp_settings[grp_bg_color]" value="%s">', $val);
    }

    public function accent_color_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_accent_color'] ?? '#4285F4');
        printf('<input type="color" name="grp_settings[grp_accent_color]" value="%s">', $val);
    }

    public function btn_text_color_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_btn_text_color'] ?? '#ffffff');
        printf('<input type="color" name="grp_settings[grp_btn_text_color]" value="%s">', $val);
    }

    public function business_name_html(): void
    {
        $local_data = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local_data['name']);
        $val = $is_managed ? $local_data['name'] : esc_attr(get_option('grp_settings')['grp_business_name'] ?? '');
        printf(
            '<input type="text" id="grp_business_name" name="grp_settings[grp_business_name]" value="%s" class="regular-text" %s>',
            $val,
            $is_managed ? 'disabled style="background:#f0f0f1; color:#666;"' : ''
        );
        if ($is_managed) {
            echo '<p class="description">' . __('Value synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function latitude_html(): void
    {
        $local = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local['lat']);
        $val = $is_managed ? $local['lat'] : esc_attr(get_option('grp_settings')['grp_latitude'] ?? '');
        printf(
            '<input type="text" id="grp_latitude" name="grp_settings[grp_latitude]" value="%s" class="medium-text" %s>',
            $val,
            $is_managed ? 'disabled style="background:#f0f0f1; color:#555;"' : ''
        );
        if ($is_managed) {
            echo '<p class="description">' . __('Value synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function longitude_html(): void
    {
        $local = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local['lng']);
        $val = $is_managed ? $local['lng'] : esc_attr(get_option('grp_settings')['grp_longitude'] ?? '');
        printf(
            '<input type="text" id="grp_longitude" name="grp_settings[grp_longitude]" value="%s" class="medium-text" %s>',
            $val,
            $is_managed ? 'disabled style="background:#f0f0f1; color:#555;"' : ''
        );
        if ($is_managed) {
            echo '<p class="description">' . __('Value synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function address_html(): void
    {
        $local = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local['address']);
        $val = $is_managed ? $local['address'] : esc_textarea(get_option('grp_settings')['grp_address'] ?? '');
        printf(
            '<textarea id="grp_address" name="grp_settings[grp_address]" class="large-text" rows="3" placeholder="123 Main St..." %s>%s</textarea>',
            $is_managed ? 'disabled style="background:#f0f0f1; color:#555; border-color:#ccc;"' : '',
            $val
        );
        if ($is_managed) {
            echo '<p class="description">' . __('Synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function phone_html(): void
    {
        $local = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local['phone']);
        $val = $is_managed ? $local['phone'] : esc_attr(get_option('grp_settings')['grp_phone'] ?? '');
        printf(
            '<input type="text" id="grp_phone" name="grp_settings[grp_phone]" value="%s" class="regular-text" placeholder="+359888888888" %s>',
            $val,
            $is_managed ? 'disabled style="background:#f0f0f1; color:#555; border-color:#ccc;"' : ''
        );
        if ($is_managed) {
            echo '<p class="description">' . __('Synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function price_html(): void
    {
        // Check for SEO plugin data (Priority #1)
        $local = $this->seo->get_local_data();
        $is_managed = $this->is_seo_integration_active() && !empty($local['price_range']);
        $options = get_option('grp_settings');
        // If it is managed by an SEO plugin, we get its value.
        // If not - we get our setting (or blank for Auto)
        $val = $is_managed ? $local['price_range'] : ($options['grp_price'] ?? '');

        // --- SEO plugin is active and price range is set. ---
        if ($is_managed) {
            printf('<input type="text" value="%s" class="regular-text" disabled style="background:#f0f0f1; color:#555;">', esc_attr($val));
            echo '<p class="description">' . __('Synced from SEO plugin.', 'google-reviews-pro') . '</p>';
            return;
        }

        // --- Manual or API integration ---
        $place_id = $options['place_id'] ?? '';
        $db = get_option('grp_locations_db', []);
        $auto_info = '';
        if (!empty($place_id) && isset($db[$place_id]['price_level'])) {
            $lvl = (int) $db[$place_id]['price_level'];
            if ($lvl > 0) {
                $auto_info = sprintf(
                    '<span style="color: #46b450; font-weight: 600; margin-left: 10px;">%s %s</span>',
                    __('✓ API Detected:', 'google-reviews-pro'),
                    str_repeat('$', $lvl)
                );
            }
        }
        ?>
        <select name="grp_settings[grp_price]" id="grp_price">
            <option value="" <?php selected($val, ''); ?>><?php _e('Auto (Use API Data)', 'google-reviews-pro'); ?></option>
            <option value="$" <?php selected($val, '$'); ?>>$ (Cheap)</option>
            <option value="$$" <?php selected($val, '$$'); ?>>$$ (Moderate)</option>
            <option value="$$$" <?php selected($val, '$$$'); ?>>$$$ (Expensive)</option>
            <option value="$$$$" <?php selected($val, '$$$$'); ?>>$$$$ (Luxury)</option>
        </select>
        <?php echo $auto_info; ?>
        <p class="description"><?php _e('Select a price range manually to override, or leave "Auto" to use data fetched from Google.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function disable_seo_integration_html(): void
    {
        $provider = $this->seo->get_active_provider();
        $disabled = (bool) (get_option('grp_settings')['grp_disable_seo_integration'] ?? false); ?>
        <label>
            <input type="checkbox"
               name="grp_settings[grp_disable_seo_integration]"
               id="grp_disable_seo_integration"
               value="1"
                <?php checked(1, $disabled ? 1 : 0); ?>>
            <?php _e('Disable SEO plugin integration (use manual values only)', 'google-reviews-pro'); ?>
        </label>
        <?php if (!$provider) : ?>
        <p class="description"><?php _e('No supported SEO plugin detected. This option has no effect until one is installed.', 'google-reviews-pro'); ?></p>
    <?php else : ?>
        <p class="description"><?php _e('When checked, the fields above become editable and the SEO plugin data is ignored.', 'google-reviews-pro'); ?></p>
    <?php endif; ?>
        <?php
    }

    public function backup_section_html(): void
    {
        ?>
        <div class="grp-export-options">
            <h3><?php _e('Export Reviews', 'google-reviews-pro'); ?></h3>
            <p><?php _e('Download a backup of your current reviews.', 'google-reviews-pro'); ?></p>
            <div style="display: flex; gap: 15px;">
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_json">
                    <button type="submit" class="button button-secondary"><?php _e('Export JSON', 'google-reviews-pro'); ?></button>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_csv">
                    <button type="submit" class="button button-secondary"><?php _e('Export CSV', 'google-reviews-pro'); ?></button>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_zip">
                    <button class="button button-primary"><?php _e('Download Full Backup (.ZIP)', 'google-reviews-pro'); ?></button>
                </form>
            </div>
        </div>
        <div class="grp-export-options">
            <h3><?php _e('Import Reviews', 'google-reviews-pro'); ?></h3>
            <p><?php _e('Upload a JSON or Zip file previously exported from this plugin.', 'google-reviews-pro'); ?></p>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('grp_import_action', 'grp_import_nonce'); ?>
                <input type="hidden" name="grp_action" value="import_file">
                <input type="file" name="grp_import_file" accept=".json,.zip,.csv" required>
                <br><br>
                <button type="submit" class="button button-primary"><?php _e('Import Reviews', 'google-reviews-pro'); ?></button>
            </form>
        </div>
        <?php
    }

    public function email_alerts_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['email_alerts'] ?? 0); ?>
        <label>
            <input type="checkbox" id="grp_email_alerts" name="grp_settings[email_alerts]" value="1" <?php checked(1, $val); ?>>
            <?php _e('Send email notifications when new reviews are imported.', 'google-reviews-pro'); ?>
        </label>
        <?php
    }

    public function notification_email_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['notification_email'] ?? '');
        $is_enabled = !empty(get_option('grp_settings')['email_alerts']); ?>
        <input type="email"
           id="grp_notification_email"
           name="grp_settings[notification_email]"
           value="<?php echo $val; ?>"
           class="regular-text"
            <?php echo $is_enabled ? '' : 'disabled'; ?>>
        <p class="description"><?php _e('Leave empty to use the WordPress Admin email.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function wipe_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['wipe_on_uninstall'] ?? 0); ?>
        <label>
            <input type="checkbox" name="grp_settings[wipe_on_uninstall]" value="1" <?php checked(1, $val); ?>>
            <?php _e('Delete all reviews, local images, and settings when deleting the plugin.', 'google-reviews-pro'); ?>
        </label>
        <p class="description" style="color: #d63638;">
            <?php _e('Warning: This action is irreversible. If checked, all your collected reviews will be lost upon uninstallation.', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    // -------------------------------------------------------------------------
    // Admin scripts
    // -------------------------------------------------------------------------

    public function render_admin_scripts(): void
    {
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_grp-settings') {
            return;
        }
        $days_for_js = DateUtils::get_days_of_week_l10n(); ?>
        <script type="text/javascript">
            const localizedDays = <?php echo json_encode($days_for_js); ?>;

            jQuery(document).ready(function($) {

                // Tab switching
                // Tabs that are always accessible regardless of API config.
                const ALWAYS_ENABLED_TABS = ['api-sources', 'find-business'];

                function isApiConfigured() {
                    const source = $('#grp_data_source').val();
                    if (!source || source === 'cpt') {
                        return true; // manual mode — no key needed
                    }

                    const keyMap = {
                        google:      $('input[name="grp_settings[google_api_key]"]').val(),
                        serpapi:     $('input[name="grp_settings[serpapi_key]"]').val(),
                        scrapingdog: $('#grp_scrapingdog_key').val(),
                    };
                    return !!(keyMap[source] || '').trim();
                }

                function applyTabLock() {
                    const configured = isApiConfigured();
                    $('.grp-nav-tab').each(function() {
                        const $this = $(this);
                        const tab = $this.data('tab');
                        if (ALWAYS_ENABLED_TABS.includes(tab)) {
                            return; // always unlocked
                        }

                        if (configured) {
                            $this.removeClass('grp-tab-disabled').removeAttr('title').css({ opacity: '', cursor: '', pointerEvents: '' });
                        } else {
                            $this.addClass('grp-tab-disabled')
                                .attr('title', '<?php _e('Save an API key on the API Sources tab first.', 'google-reviews-pro'); ?>')
                                .css({ opacity: '0.4', cursor: 'not-allowed', pointerEvents: 'none' });
                        }
                    });

                    // If the currently active tab became locked, redirect to Tab 1.
                    if (!configured) {
                        const activeTab = $('.grp-nav-tab.nav-tab-active').data('tab');
                        if (!ALWAYS_ENABLED_TABS.includes(activeTab)) {
                            $('.grp-nav-tab[data-tab="api-sources"]').trigger('click');
                        }
                    }
                }

                $('.grp-nav-tab').on('click', function(e) {
                    e.preventDefault();
                    const $this = $(this);

                    if ($this.hasClass('grp-tab-disabled')) {
                        return;
                    }

                    const target = $this.data('tab');
                    $('.grp-nav-tab').removeClass('nav-tab-active');
                    $this.addClass('nav-tab-active');
                    $('.grp-tab-panel').hide();
                    $('#grp-tab-' + target).show();
                    // Persist active tab across page load
                    try {
                        localStorage.setItem('grp_active_tab', target);
                    } catch(err) {

                    }
                });

                // Restore previously active tab (or fall back to first)
                (function() {
                    let saved = null;
                    try {
                        saved = localStorage.getItem('grp_active_tab');
                    } catch(err) {

                    }
                    const $target = saved ? $('.grp-nav-tab[data-tab="' + saved + '"]') : null;
                    if ($target && $target.length && !$target.hasClass('grp-tab-disabled')) {
                        $target.trigger('click');
                    } else {
                        $('.grp-nav-tab').first().trigger('click');
                    }
                })();

                const $sourceSelect = $('#grp_data_source');
                const $inputGoogleApiKey = $('input[name="grp_settings[google_api_key]"]');
                const $inputPlaceId = $('input[name="grp_settings[place_id]"]');
                const $inputSerpApiKey = $('input[name="grp_settings[serpapi_key]"]');
                const $inputSerpApiDataId = $('#serpapi_data_id');
                const $syncBtn = $('#grp-sync-btn');
                const $syncStatus = $('#grp-sync-status');
                const $lastSyncSpan = $('#grp-last-sync-time');
                const $autoSync = $('input[name="grp_settings[auto_sync]"]');
                const $serpPages = $('input[name="grp_settings[serpapi_pages]"]');
                const $inputScrapingDogKey = $('#grp_scrapingdog_key');
                const $previewWrapper = $('#grp_preview_wrapper');
                const $serpPagesRow = $serpPages.closest('tr');
                const $autoSyncRow = $autoSync.closest('tr');
                const $googleKeyRow = $inputGoogleApiKey.closest('tr');
                const $placeIdRow = $inputPlaceId.closest('tr');
                const $serpApiDataIdRow = $inputSerpApiDataId.closest('tr');
                const $serpApiRow = $inputSerpApiKey.closest('tr');
                const $finderRow = $('#grp-finder-box').closest('tr');
                const $findBtn = $('#grp-find-btn');
                const $saveApiDataBtn = $('#grp-save-api-btn');
                const $spinner = $('#grp-finder-spinner');
                const $msg = $('#grp-finder-msg');
                const $scrapingDogRow = $inputScrapingDogKey.closest('tr');

                function getStars(rating) {
                    const r = Math.round(rating);
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        stars += i <= r ? '★' : '☆';
                    }
                    return stars;
                }

                function toggleFields() {
                    const val = $sourceSelect.val();

                    $googleKeyRow.hide();
                    $placeIdRow.hide();
                    $serpApiRow.hide();
                    $serpApiDataIdRow.hide();
                    $syncBtn.hide();
                    $scrapingDogRow.hide();
                    $inputGoogleApiKey.prop('required', false);
                    $inputPlaceId.prop('required', false);
                    $inputSerpApiKey.prop('required', false);
                    $inputScrapingDogKey.prop('required', false);

                    if (val === 'google') {
                        $inputGoogleApiKey.prop('required', true);
                        $inputPlaceId.prop('required', true);
                        $googleKeyRow.show();
                        $syncBtn.show();
                    } else if (val === 'serpapi') {
                        $inputSerpApiKey.prop('required', true);
                        $serpApiRow.show();
                        $serpApiDataIdRow.show();
                        $syncBtn.show();
                    } else if (val === 'scrapingdog') {
                        $inputScrapingDogKey.prop('required', true);
                        $inputPlaceId.prop('required', true); // ScrapingDog also uses Place ID
                        $scrapingDogRow.show();
                        $placeIdRow.show();
                        $serpApiDataIdRow.show(); // ScrapingDog also uses Data ID
                        $syncBtn.show();
                    }

                    $autoSyncRow.toggle(val !== 'cpt');
                    $placeIdRow.toggle(val !== 'cpt');
                    $serpPagesRow.toggle(val !== 'cpt' && val !== 'google');
                    $finderRow.toggle(val !== 'cpt');
                }

                toggleFields();
                $sourceSelect.on('change', function() {
                    toggleFields();
                    applyTabLock();
                });

                // Re-evaluate lock whenever an API key field loses focus.
                $('input[name="grp_settings[google_api_key]"], input[name="grp_settings[serpapi_key]"], #grp_scrapingdog_key')
                    .on('input blur', applyTabLock);

                // Run once on page load after everything is initialised.
                applyTabLock();

                // Manual sync
                $syncBtn.on('click', function(e) {
                    e.preventDefault();
                    $syncBtn.prop('disabled', true).text('<?php _e('Syncing...', 'google-reviews-pro'); ?>');
                    $syncStatus.text('');
                    $.post(ajaxurl, {
                        action: 'grp_refresh',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>'
                    }, function(response) {
                        if (response.success) {
                            $syncStatus.css('color', 'green').text('<?php _e('Reviews synced successfully!', 'google-reviews-pro'); ?>');
                            $lastSyncSpan.text(response.data.last_sync || '');
                        } else {
                            $syncStatus.css('color', 'red').text('<?php _e('Error', 'google-reviews-pro'); ?>: ' + response.data);
                        }
                    }).fail(function() {
                        $syncStatus.css('color', 'red').text('<?php _e('Server error occurred.', 'google-reviews-pro'); ?>');
                    }).always(function() {
                        $syncBtn.prop('disabled', false).text('<?php _e('Sync Reviews Now', 'google-reviews-pro'); ?>');
                    });
                });

                //  Find Business
                $findBtn.on('click', function(e) {
                    e.preventDefault();
                    const queryInput = $('#grp_finder_query');
                    const queryPlaceIdInput = $('#grp_finder_place_id');
                    const query = queryInput.val();
                    const placeId = queryPlaceIdInput.val();
                    const source = $sourceSelect.val();
                    const searchedValue = query.trim().length ? query : placeId;
                    let apiKey = '';

                    if (source === 'google') {
                        apiKey = $('#grp_google_key').val();
                    } else if (source === 'serpapi') {
                        apiKey = $('#grp_serpapi_key').val();
                    } else if (source === 'scrapingdog') {
                        apiKey = $('#grp_scrapingdog_key').val();
                    }

                    if (!searchedValue.trim().length || !apiKey) {
                        alert('<?php _e('Please enter a business name and ensure your API key is filled in.', 'google-reviews-pro'); ?>');
                        return;
                    }

                    $saveApiDataBtn.hide();
                    $spinner.addClass('is-active');
                    $findBtn.prop('disabled', true);
                    $msg.text('');
                    $previewWrapper.hide();
                    const $action = query.trim().length ? 'grp_find_business' : 'grp_find_business_by_place_id';

                    $.post(ajaxurl, {
                        action: $action,
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        query: searchedValue
                    }, function(res) {
                        $spinner.removeClass('is-active');
                        $findBtn.prop('disabled', false);

                        if (res.success) {
                            const data = res.data;
                            let coordinates = '';

                            if (data.lat) {
                                coordinates += data.lat;
                            }

                            if (data.lat && data.lng) {
                                coordinates += ' / ';
                            }

                            if (data.lng) {
                                coordinates += data.lng;
                            }

                            $msg.css('color', 'green').html('<?php _e('Business found! Fields autofilled. Please <strong>Save Changes</strong>.', 'google-reviews-pro'); ?>');
                            $('#grp_prev_data_id').text(data.data_id);
                            $('#grp_prev_name').text(data.name || 'Unknown');
                            $('#grp_prev_place_id').text(data.place_id);
                            $('#grp_prev_rating').text(data.rating || '0.0');
                            $('#grp_prev_count').text(data.count || '0');
                            $('#grp_prev_address').text(data.address || '');
                            $('#grp_prev_phone').text(data.phone || '');
                            $('#grp_prev_coordinates').text(coordinates);
                            $('.grp-stars').text(getStars(data.rating || 0));
                            $('#grp_prev_weekday').text(data.weekday_text || '');
                            const priceLvl = data.price_level || 0;

                            if (data.periods) {
                                $('#grp_prev_periods').text(JSON.stringify(data.periods));
                            }

                            $('#grp_prev_price').text(priceLvl > 0 ? '$'.repeat(priceLvl) : '');

                            if (data.maps_url) {
                                $('#grp_prev_map_link').attr('href', data.maps_url).show();
                            } else {
                                $('#grp_prev_map_link').hide();
                            }

                            const iconUrl = data.icon || data.photo_url || 'https://maps.gstatic.com/mapfiles/place_api/icons/v1/png_71/generic_business-71.png';
                            $('#grp_prev_icon').attr('src', iconUrl);
                            $previewWrapper.slideDown();
                            queryInput.val('');
                            $saveApiDataBtn.show();
                        } else {
                            $msg.css('color', 'red').text('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Not found', 'google-reviews-pro'); ?>'));
                            $previewWrapper.hide();
                            $saveApiDataBtn.hide();
                        }
                    }).fail(function() {
                        $spinner.removeClass('is-active');
                        $findBtn.prop('disabled', false);
                        $saveApiDataBtn.hide();
                        $msg.css('color', 'red').text('<?php _e('Server error', 'google-reviews-pro'); ?>.');
                    });
                });

                //  Save API location data
                $saveApiDataBtn.on('click', function(e) {
                    e.preventDefault();
                    const data_id = $('#grp_prev_data_id').text();
                    const place_id = $('#grp_prev_place_id').text();
                    const business_name = $('#grp_prev_name').text();
                    const address = $('#grp_prev_address').text();
                    const phone = $('#grp_prev_phone').text();
                    const rating = $('#grp_prev_rating').text();
                    const reviews_count = $('#grp_prev_count').text();
                    const price_lvl = $('#grp_prev_price').text();
                    const coordinates = $('#grp_prev_coordinates').text();
                    const periods = $('#grp_prev_periods').text();

                    if (!business_name || business_name === 'Unknown') {
                        console.log('Location not found.');
                        return false;
                    }

                    $msg.text('');
                    let working_days = periods.length ? JSON.parse(periods) : [];
                    $spinner.addClass('is-active');
                    $saveApiDataBtn.prop('disabled', true);
                    $findBtn.prop('disabled', true);

                    const request_data = {
                        data_id: data_id,
                        place_id: place_id,
                        name: business_name,
                        address: address,
                        phone: phone,
                        price_lvl: price_lvl,
                        coordinates: coordinates,
                        rating: rating,
                        reviews_count: reviews_count,
                        working_days: working_days
                    };

                    $.post(ajaxurl, {
                        action: 'grp_save_api_location_data',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        data: request_data
                    }, function(res) {
                        $spinner.removeClass('is-active');
                        $saveApiDataBtn.prop('disabled', false);
                        $findBtn.prop('disabled', false);
                        $previewWrapper.slideUp();
                        if (res.success) {
                            $msg.css('color', 'green').html('<?php _e('API data saved successfully.', 'google-reviews-pro'); ?>');
                            setTimeout(() => { window.location.href = res.data.redirect_url; }, 1000);
                        } else {
                            $msg.css('color', 'red').text('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'));
                        }
                    }).fail(function(res) {
                        $spinner.removeClass('is-active');
                        $saveApiDataBtn.hide().prop('disabled', false);
                        $findBtn.prop('disabled', false);
                        $msg.css('color', 'red').text('<?php _e('Server error', 'google-reviews-pro'); ?>.');
                        console.log(res);
                    });
                });

                //  Email toggle
                const $emailCheckbox = $('#grp_email_alerts');
                const $emailInput = $('#grp_notification_email');

                function toggleEmailInput() {
                    if ($emailCheckbox.is(':checked')) {
                        $emailInput.prop('disabled', false).css('opacity', 1);
                    } else {
                        $emailInput.prop('disabled', true).css('opacity', 0.6).val('');
                    }
                }
                toggleEmailInput();
                $emailCheckbox.on('change', toggleEmailInput);

                //  Edit Location modal
                const $editModal = $('#grp-edit-place');

                $('.button-link-edit-place').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const placeId = $btn.data('place-id');

                    $('#update-location-response').text('').addClass('hidden').removeClass('error success');
                    $('#update-location-fields').show();
                    $btn.prop('disabled', true).text('<?php _e('Loading data', 'google-reviews-pro'); ?>...');

                    $.post(ajaxurl, {
                        action: 'grp_get_location_details',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId
                    }, function(res) {
                        if (res.success) {
                            const $data = res.data;
                            const $address = $data.address || '';
                            $('#edit-location-name').val($data.name || '');
                            $('#edit-location-address').text($address).val($address);
                            $('#edit-location-phone').val($data.phone || '');
                            $('input#edit-location-place-id').val($data.place_id || placeId);
                            $('#edit-reviews-rating').val($data.rating || '');
                            $('#edit-reviews-total-count').val($data.count || '');

                            // Populate working-hours inputs
                            $('.edit-location-hours').val('');
                            const periods = $data.periods || {}; // { monday: "09:00 - 18:00", … }
                            const mappedPeriods = {};

                            for (const [bgDay, hours] of Object.entries(periods)) {
                                const englishDay = localizedDays[bgDay];
                                mappedPeriods[englishDay] = hours;
                            }

                            const $workingDays = convertScheduleTo24h(Object.keys(mappedPeriods).length ? mappedPeriods : periods);

                            $.each($workingDays, function(day, range) {
                                const parts = range.split('-');
                                if (parts.length !== 2) {
                                    return;
                                }
                                const opens = parts[0].trim().replace(/[^\d:]/g, '');
                                const closes = parts[1].trim().replace(/[^\d:]/g, '');
                                const day_of_week = localizedDays[day] ?? day;
                                $('#edit-hours-open-' + day_of_week).val(opens);
                                $('#edit-hours-close-' + day_of_week).val(closes);
                            });

                            $('#grp-edit-loc-btn').prop('disabled', false);
                            $editModal.css('display', 'flex');
                            $btn.prop('disabled', false).text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                        } else {
                            $('#grp-edit-loc-btn').prop('disabled', true);
                            alert('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'));
                            $btn.prop('disabled', false).text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                        }
                    }).fail(function() {
                        alert('<?php _e('Server error', 'google-reviews-pro'); ?>.');
                        $btn.prop('disabled', false).text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                        $('#grp-edit-loc-btn').prop('disabled', true);
                    });
                });

                // Close Modal
                $('.grp-close-edit-location-modal').on('click', function(e) {
                    e.preventDefault();
                    $editModal.hide();
                    $editModal.find('input').val('');
                    $editModal.find('textarea').text('').val('');
                    $('.edit-location-hours').val('');
                    $('#update-location-fields').hide();
                });

                // Update Location
                $('#grp-edit-loc-btn').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const placeId = $('input#edit-location-place-id').val();
                    const location_name = $('#edit-location-name').val();
                    const location_address = $('#edit-location-address').val();
                    const location_phone = $('#edit-location-phone').val();
                    const location_rating = $('#edit-reviews-rating').val();
                    const location_total_reviews_count = $('#edit-reviews-total-count').val();

                    let working_hours = {};
                    let hours_valid = true;
                    const time_re = /^([01]\d|2[0-3]):[0-5]\d$/;
                    const days_list = Object.values(localizedDays);

                    $.each(days_list, function(_, day) {
                        const opens  = $('#edit-hours-open-'  + day).val().trim();
                        const closes = $('#edit-hours-close-' + day).val().trim();
                        // Both empty → Closed (skip)
                        if (opens === '' && closes === '') {
                            return;
                        }

                        // One filled, one empty → validation error
                        if (opens === '' || closes === '') {
                            alert('<?php _e('Working hours error: both open and close time are required for', 'google-reviews-pro'); ?> ' + day);
                            hours_valid = false;
                            return false;
                        }

                        // Format check
                        if (!time_re.test(opens) || !time_re.test(closes)) {
                            alert('<?php _e('Working hours error: invalid time format (use HH:MM) for', 'google-reviews-pro'); ?> ' + day);
                            hours_valid = false;
                            return false;
                        }

                        // Logical check: close must be after open
                        if (opens >= closes) {
                            alert('<?php _e('Working hours error: closing time must be after opening time for', 'google-reviews-pro'); ?> ' + day);
                            hours_valid = false;
                            return false;
                        }

                        working_hours[day] = opens + ' - ' + closes;
                    });

                    if (!hours_valid) {
                        $btn.prop('disabled', false).text('<?php _e('Update', 'google-reviews-pro'); ?>');
                        return;
                    }

                    $('#update-location-response').text('').addClass('hidden').removeClass('error success');
                    $btn.prop('disabled', true).text('<?php _e('Updating data', 'google-reviews-pro'); ?>...');

                    $.post(ajaxurl, {
                        action: 'grp_update_location',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId,
                        name: location_name,
                        address: location_address,
                        phone: location_phone,
                        rating: location_rating,
                        total_count: location_total_reviews_count,
                        working_hours: working_hours
                    }, function(res) {
                        if (res.success) {
                            $('#update-location-response').text(res.data.message).addClass('success').removeClass('hidden error');
                            $editModal.find('input').val('');
                            $editModal.find('textarea').text('').val('');
                            $editModal.css('display', 'flex'); // Flex to center
                            $('#update-location-fields').hide();
                            $btn.text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                        } else {
                            $('#update-location-response')
                                .text('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'))
                                .addClass('error').removeClass('hidden success');
                            $btn.prop('disabled', false).text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                        }
                    }).fail(function() {
                        $('#update-location-response').text('<?php _e('Server error', 'google-reviews-pro'); ?>.').removeClass('hidden success');
                        $btn.prop('disabled', false).text('<?php _e('Edit', 'google-reviews-pro'); ?>');
                    });
                });

                // Refresh location
                $('.grp-refresh-loc-btn').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const placeId = $btn.data('place-id');

                    $btn.prop('disabled', true).text('<?php _e('Refreshing...', 'google-reviews-pro'); ?>');
                    $syncStatus.text('');

                    $.post(ajaxurl, {
                        action: 'grp_refresh_location',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId
                    }, function(res) {
                        if (res.success) {
                            $syncStatus.css('color', 'green').text(res.data.message);
                        } else {
                            $syncStatus.css('color', 'red').text('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'));
                        }
                    }).fail(function() {
                        $syncStatus.css('color', 'red').text('<?php _e('Server error', 'google-reviews-pro'); ?>.');
                    }).always(function() {
                        $btn.prop('disabled', false).text('<?php _e('Refresh', 'google-reviews-pro'); ?>');
                    });
                });

                // Delete location
                $('.grp-delete-loc-btn').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const placeId = $btn.data('place-id');
                    if (!confirm('<?php _e('Are you sure you want to delete this location? All associated reviews and images will be permanently removed.', 'google-reviews-pro'); ?>')) {
                        return;
                    }

                    $btn.prop('disabled', true).text('...');

                    $.post(ajaxurl, {
                        action: 'grp_delete_location',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId
                    }, function(res) {
                        if (res.success) {
                            alert(res.data.message);
                            $btn.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'));
                            $btn.prop('disabled', false).text('<?php _e('Delete', 'google-reviews-pro'); ?>');
                        }
                    }).fail(function() {
                        alert('<?php _e('Server error', 'google-reviews-pro'); ?>.');
                        $btn.prop('disabled', false).text('<?php _e('Delete', 'google-reviews-pro'); ?>');
                    });
                });

                //  Debug data toggle
                $('input[name=debug_data]').on('click', function(e) {
                    e.preventDefault();
                    const placeId = $(this).data('place-id');
                    $('.debug-place').hide();
                    $('#debug-place-' + placeId).show();
                });

                // Raw data modal
                const $rawModal = $('#grp-raw-data-modal');
                const $rawContent = $('#grp-raw-content');

                // Open Modal & Fetch Data
                $('.grp-details-btn').on('click', function(e) {
                    e.preventDefault();
                    const placeId = $(this).data('place-id');
                    $rawContent.text('<?php _e('Loading data', 'google-reviews-pro'); ?>...');
                    $rawModal.css('display', 'flex');

                    $.post(ajaxurl, {
                        action: 'grp_get_location_details',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId
                    }, function(res) {
                        $rawContent.text(res.success ? JSON.stringify(res.data, null, 4) : '<?php _e('Error', 'google-reviews-pro'); ?>: ' + (res.data || '<?php _e('Unknown error', 'google-reviews-pro'); ?>'));
                    }).fail(function() {
                        $rawContent.text('<?php _e('Server connection failed', 'google-reviews-pro'); ?>.');
                    });
                });

                // Close Modal
                $('.grp-close-modal').on('click', function(e) {
                    e.preventDefault();
                    $rawModal.hide();
                });

                // Schema check modal
                const $schemaModal = $('#grp-schema-modal');
                const $schemaBody = $('#grp-schema-tbody');

                $('.grp-schema-btn, .grp-seo-data-btn').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const placeId = $btn.data('place-id');
                    const action = $btn.hasClass('grp-seo-data-btn') ? 'grp_get_seo_details' : 'grp_get_schema_details';

                    $schemaBody.html('<tr><td colspan="3"><?php _e('Analyzing...', 'google-reviews-pro'); ?></td></tr>');
                    $schemaModal.css('display', 'flex');

                    $.post(ajaxurl, {
                        action,
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        place_id: placeId
                    }, function(res) {
                        if (res.success) {
                            // Map friendly names
                            const labels = {
                                'place_id': 'Place ID',
                                'data_id': 'Data ID',
                                'name': 'Business Name',
                                'address': 'Address',
                                'phone': 'Telephone',
                                'latitude': 'Latitude',
                                'longitude': 'Longitude',
                                'priceRange': 'Price Range',
                                'openingHours': 'Opening Hours',
                                'maps_url': 'Has Map URL'
                            };
                            let rows = '';

                            for (const [key, info] of Object.entries(res.data)) {
                                const label = labels[key] || key;
                                const sourceColor = info.source.includes('SEO') ? '#46b450' : (info.source.includes('API') ? '#2271b1' : '#666');
                                rows += `<tr>
                                    <td><strong>${label}</strong></td>
                                    <td style="word-break:break-word;">${info.value}</td>
                                    <td><span style="color:${sourceColor}; font-weight:600;">${info.source}</span></td>
                                </tr>`;
                            }
                            $schemaBody.html(rows);
                        } else {
                            $schemaBody.html('<tr><td colspan="3" style="color:red;"><?php _e('Error loading data', 'google-reviews-pro'); ?>.</td></tr>');
                        }
                    }).fail(function() {
                        $schemaBody.html('<tr><td colspan="3" style="color:red;"><?php _e('Server error', 'google-reviews-pro'); ?>.</td></tr>');
                    });
                });

                $('.grp-close-schema').on('click', function() { $schemaModal.hide(); });

                // QR Card modal
                const $qrCodeModal = $('#grp-printable-qr-card-preview');

                $('.open-qr-preview-mode').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const qrImgSource = $btn.closest('td').find('img').prop('src');
                    const businessName = $btn.data('name');
                    const reviewUrl = $btn.data('url');
                    $('#grp-qr-code-img').prop('src', qrImgSource);
                    $('#grp-qr-code-business-name').text(businessName);
                    $('#grp-google-review-qr-img').prop('href', qrImgSource);
                    $qrCodeModal.find('input').val(reviewUrl);
                    $qrCodeModal.css('display', 'flex');
                });

                $('.grp-close-printable-qr-modal').on('click', function() {
                    $qrCodeModal.hide();
                    $('#grp-qr-code-img').prop('src', '');
                    $('#grp-qr-code-business-name').text('');
                    $('#grp-google-review-qr-img').prop('href', '');
                    $qrCodeModal.find('input').val('');
                });

                // Close modals on outside click
                $(window).on('click', function(e) {
                    if ($(e.target).is('#grp-raw-data-modal')) {
                        $rawModal.hide();
                    }

                    if ($(e.target).is('#grp-schema-modal')) {
                        $schemaModal.hide();
                    }

                    if ($(e.target).is('#grp-edit-place')) {
                        $editModal.hide();
                    }

                    if ($(e.target).is('#grp-printable-qr-card-preview')) {
                        $qrCodeModal.hide();
                        $('#grp-qr-code-img').prop('src', '');
                        $('#grp-qr-code-business-name').text('');
                        $('#grp-google-review-qr-img').prop('href', '');
                        $qrCodeModal.find('input').val('');
                    }
                });
            }); // end ready

            //  Utilities (outside ready so printCard() is globally accessible)
            function printCard() {
                const cardContent = document.getElementById('grp-print-card').outerHTML;
                const win = window.open('', '', 'height=600,width=800');
                win.document.write('<html lang="en"><head><title><?php _e('Print Card', 'google-reviews-pro'); ?></title></head>');
                win.document.write('<body style="display:flex; justify-content:center; align-items:center; height:100vh;">');
                win.document.write(cardContent);
                win.document.write('</body></html>');
                win.document.close();
                setTimeout(function() {
                    win.focus();
                    win.print();
                    win.close();
                }, 250);
            }

            function convertScheduleTo24h(schedule) {
                // Quick check: If no "AM" or "PM" is found anywhere in the object, we assume it's already in 24h format (or just full of "Closed")
                const hasAmPm = Object.values(schedule).some(val => /[ap]\.?m\.?/i.test(val));
                if (!hasAmPm) {
                    return schedule; // Return the exact original object
                }

                // Helper function to convert a single time string (e.g., "8:30 AM" or "5 PM")
                const to24h = (timeStr) => {
                    // Replace weird spaces (like narrow no-break space \u202F) with standard spaces
                    const cleanStr = timeStr.replace(/[\u202F\u00A0]/g, ' ').trim();
                    // Extract hours, optional minutes, and AM/PM
                    const match = cleanStr.match(/^(\d{1,2})(?::(\d{2}))?\s*([AP]M)$/i);

                    if (!match) {
                        return cleanStr; // If it doesn't match the pattern, return as-is
                    }

                    let [, hours, minutes, modifier] = match;
                    hours = parseInt(hours, 10);
                    minutes = minutes || '00'; // Default to '00' if minutes are missing (e.g., "5 PM")
                    modifier = modifier.toUpperCase();

                    // Convert to 24-hour logic
                    if (modifier === 'PM' && hours < 12) {
                        hours += 12;
                    } else if (modifier === 'AM' && hours === 12) {
                        hours = 0;
                    }

                    // Pad hours with a leading zero so "8" becomes "08"
                    return `${hours.toString().padStart(2, '0')}:${minutes}`;
                };

                const newSchedule = {};
                for (const [day, timeRange] of Object.entries(schedule)) {
                    if (timeRange.toLowerCase() === 'closed') {
                        newSchedule[day] = timeRange;
                        continue;
                    }
                    // Split by standard hyphen, en-dash, or em-dash, ignoring surrounding spaces
                    const parts = timeRange.split(/\s*[-–—]\s*/);
                    if (parts.length === 2) {
                        // Convert both parts and join with a standard hyphen
                        newSchedule[day] = `${to24h(parts[0])}-${to24h(parts[1])}`;
                    } else {
                        // Fallback if the string doesn't split into exactly two parts
                        newSchedule[day] = timeRange;
                    }
                }
                return newSchedule;
            }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Page renderer — tabbed layout
    // -------------------------------------------------------------------------

    public function render_page(): void
    {
        $last_sync = get_option('grp_last_sync_time');
        $display_time = $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync) : __('Never', 'google-reviews-pro');
        ?>
        <style>
            /*  Tab layout  */
            .grp-tab-panel { display: none; }
            .grp-tab-panel.grp-tab-active { display: block; }

            /*  Sticky save bar  */
            .grp-save-bar {
                position: sticky;
                top: 32px;           /* clears WP admin bar */
                z-index: 900;
                background: #f0f0f1;
                border-bottom: 1px solid #c3c4c7;
                padding: 10px 0 10px 2px;
                margin-bottom: 16px;
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .grp-save-bar .button-primary { margin: 0; }

            /*  Sync container (inside Sync tab)  */
            #grp-sync-container {
                margin-top: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
            }

            /*  Modal styles  */
            .form-table .modal-window table th { padding-left: 10px; padding-right: 10px; }
            .hidden { display: none; }
            .debug-place > .debug-place-container { display: flex; flex-direction: column; align-content: space-evenly; gap: 10px; }
            .debug-place, input[name=debug_data] { display: none; }
            input[name=debug_data]:checked + label { background-color: #b91c1c; color: #fff; }
            ul { list-style: none; }
            ul li { margin-bottom: 10px; }
            .modal-window { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:100000; align-items:center; justify-content:center; }
            .modal-wrapper { background:#fff; width:600px; max-width:90%; max-height:80vh; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.5); display:flex; flex-direction:column; }
            .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; }
            .modal-content { overflow-y:auto; flex:1; }
            .modal-content.darker-bg { background:#f5f5f5; padding:10px; border:1px solid #ddd; }
            .modal-footer { margin-top:15px; text-align:right; }
            #grp-raw-content { white-space:pre-wrap; word-wrap:break-word; font-size:12px; margin:0; }
            #update-location-response { padding:10px; border:1px solid; border-left-width:5px; border-radius:5px; }
            #update-location-response.success { border-color: green; }
            #update-location-response.error { border-color: red; }
            #grp-finder-box { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
            .printable-qr-card-preview { flex:1; }
            #grp-print-card { border:1px solid #ccc; background:white; width:300px; padding:30px; text-align:center; font-family:sans-serif; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
            .qr-code-rating { margin-top:20px; display:flex; align-items:center; justify-content:center; gap:5px; }
            .qr-code-rating > span { color:#fbbc04; font-size:20px; }
            .button span.dashicons { line-height:1.5; }
            .grp-export-options { background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; }
            #grp_preview_wrapper { display:none; margin-top:15px; border-top:1px solid #eee; padding-top:15px; }
            #grp_preview_wrapper > h4 { margin:0 0 10px 0; }
            .grp-preview-card { display:flex; background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); max-width:450px; }
            #grp_prev_icon { width:50px; height:50px; border-radius:4px; object-fit:cover; }
            #grp_prev_name { font-weight:500; font-size:16px; color:#202124; margin-bottom:4px; }
            #grp_prev_rating_wrapper { display:flex; align-items:center; margin-bottom:4px; font-size:13px; }
            #grp_prev_rating { font-weight:bold; color:#e7711b; margin-right:4px; }
            #grp_prev_rating_wrapper .grp-stars { color:#fbbc04; margin-right:6px; letter-spacing:1px; }
            #grp_prev_rating_wrapper .grp-stars + span { color:#70757a; }
            .grp-preview-card .grp_prev_prop { color:#70757a; font-size:12px; line-height:1.4; margin-bottom:4px; }
        </style>

        <?php
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && (get_option('grp_settings')['auto_sync'] ?? 0)) {
            echo '<div class="notice notice-warning"><p>';
            _e('Warning: WP_CRON is disabled in your wp-config.php. Auto-sync will not work unless you set up a system cron job.', 'google-reviews-pro');
            echo '</p></div>';
        }
        ?>

        <div class="wrap">
            <h1><?php _e('Google Reviews Pro', 'google-reviews-pro'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('grp_group'); ?>

                <div class="grp-save-bar">
                    <?php submit_button(__('Save Changes', 'google-reviews-pro'), 'primary', 'submit', false); ?>
                    <span id="grp-sync-status" style="font-weight:600;"></span>
                </div>

                <nav class="nav-tab-wrapper" style="margin-bottom:0;">
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="api-sources"><?php _e('API Sources', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="find-business"><?php _e('Find Business', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="sync"><?php _e('Sync', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="locations"><?php _e('Locations', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="filtering"><?php _e('Filtering', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="styling"><?php _e('Styling', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="seo"><?php _e('SEO', 'google-reviews-pro'); ?></a>
                    <a href="#" class="nav-tab grp-nav-tab" data-tab="advanced"><?php _e('Advanced', 'google-reviews-pro'); ?></a>
                </nav>

                <!-- API Sources -->
                <div id="grp-tab-api-sources" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Data Source Configuration', 'google-reviews-pro'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php _e('Select Source', 'google-reviews-pro'); ?></th><td><?php $this->source_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Google Places API Key', 'google-reviews-pro'); ?></th><td><?php $this->google_key_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('SerpApi API Key', 'google-reviews-pro'); ?></th><td><?php $this->serpapi_key_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('ScrapingDog API Key', 'google-reviews-pro'); ?></th><td><?php $this->scrapingdog_key_html(); ?></td></tr>
                        <tr><th scope="row"><?php echo sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Place ID', 'google-reviews-pro')); ?></th><td><?php $this->place_id_html(); ?></td></tr>
                        <tr><th scope="row"><?php echo sprintf('%s %s', __('Fallback', 'google-reviews-pro'), __('Data ID', 'google-reviews-pro')); ?></th><td><?php $this->serpapi_data_id_html(); ?></td></tr>
                    </table>
                </div>

                <!-- Find Business  -->
                <div id="grp-tab-find-business" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Find Your Business', 'google-reviews-pro'); ?></h2>
                    <?php $this->finder_html(); ?>
                </div>

                <!-- Sync & Pagination -->
                <div id="grp-tab-sync" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Sync & Pagination', 'google-reviews-pro'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php _e('Max Pagination Pages', 'google-reviews-pro'); ?></th><td><?php $this->serpapi_pages_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Enable Auto-Sync', 'google-reviews-pro'); ?></th><td><?php $this->auto_sync_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Sync Frequency', 'google-reviews-pro'); ?></th><td><?php $this->sync_frequency_html(); ?></td></tr>
                    </table>

                    <div id="grp-sync-container">
                        <h3><?php _e('Manual Synchronization', 'google-reviews-pro'); ?></h3>
                        <p><strong><?php _e('Last Synced:', 'google-reviews-pro'); ?></strong> <span id="grp-last-sync-time"><?php echo esc_html($display_time); ?></span></p>
                        <button id="grp-sync-btn" class="button button-secondary"><?php _e('Sync Reviews Now', 'google-reviews-pro'); ?></button>
                    </div>
                </div>

                <!-- Locations -->
                <div id="grp-tab-locations" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Multi-Location Reference', 'google-reviews-pro'); ?></h2>
                    <?php $this->locations_section_desc(); ?>
                    <?php $this->stored_locations_html(); ?>
                </div>

                <!-- Filtering -->
                <div id="grp-tab-filtering" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Filtering & Moderation', 'google-reviews-pro'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php _e('Hide reviews without text (Star-only ratings)', 'google-reviews-pro'); ?></th><td><?php $this->hide_empty_reviews_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Minimum Rating', 'google-reviews-pro'); ?></th><td><?php $this->min_rating_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Sort Order', 'google-reviews-pro'); ?></th><td><?php $this->sort_order_html(); ?></td></tr>
                    </table>
                </div>

                <!-- Styling -->
                <div id="grp-tab-styling" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Styling Configuration', 'google-reviews-pro'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php _e('Initial Reviews Limit', 'google-reviews-pro'); ?></th><td><?php $this->limit_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Layout Style', 'google-reviews-pro'); ?></th><td><?php $this->layout_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Text Color', 'google-reviews-pro'); ?></th><td><?php $this->text_color_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Card Background', 'google-reviews-pro'); ?></th><td><?php $this->bg_color_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Links & Buttons', 'google-reviews-pro'); ?></th><td><?php $this->accent_color_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Button Text Color', 'google-reviews-pro'); ?></th><td><?php $this->btn_text_color_html(); ?></td></tr>
                    </table>
                </div>

                <!-- SEO -->
                <div id="grp-tab-seo" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('SEO & Business Schema', 'google-reviews-pro')); ?></h2>
                    <?php $this->seo_section_desc(); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Business Name', 'google-reviews-pro')); ?></th><td><?php $this->business_name_html(); ?></td></tr>
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Latitude', 'google-reviews-pro')); ?></th><td><?php $this->latitude_html(); ?></td></tr>
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Longitude', 'google-reviews-pro')); ?></th><td><?php $this->longitude_html(); ?></td></tr>
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Business Address', 'google-reviews-pro')); ?></th><td><?php $this->address_html(); ?></td></tr>
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Telephone', 'google-reviews-pro')); ?></th><td><?php $this->phone_html(); ?></td></tr>
                        <tr><th scope="row"><?php printf('%s %s', __('Fallback', 'google-reviews-pro'), __('Price Range', 'google-reviews-pro')); ?></th><td><?php $this->price_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('SEO Plugin Integration', 'google-reviews-pro'); ?></th><td><?php $this->disable_seo_integration_html(); ?></td></tr>
                    </table>
                </div>

                <!-- Advanced -->
                <div id="grp-tab-advanced" class="grp-tab-panel" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                    <h2><?php _e('Advanced Settings', 'google-reviews-pro'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php _e('Email Notifications', 'google-reviews-pro'); ?></th><td><?php $this->email_alerts_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Notification Email', 'google-reviews-pro'); ?></th><td><?php $this->notification_email_html(); ?></td></tr>
                        <tr><th scope="row"><?php _e('Uninstall Cleanup', 'google-reviews-pro'); ?></th><td><?php $this->wipe_html(); ?></td></tr>
                    </table>
                </div>
            </form><!-- end settings form -->

            <hr style="margin: 2em 0">

            <!-- Backup & Migration -->
            <div id="grp-tab-backup" style="background:#fff; border:1px solid #c3c4c7; border-top:none; padding:20px;">
                <h2><?php _e('Backup & Migration', 'google-reviews-pro'); ?></h2>
                <?php $this->backup_section_html(); ?>
            </div>

            <!-- Support box -->
            <div class="grp-support-box" style="margin-top:20px; padding:10px; background:#fff; border-left:4px solid #7289da;">
                <p><strong><?php _e('Enjoying Google Reviews Pro?', 'google-reviews-pro'); ?></strong>
                    <?php echo sprintf(__('You can support the developer via <a href="%s" target="_blank">Revolut</a>.', 'google-reviews-pro'), 'https://revolut.me/velizaaj0s'); ?>
                </p>
            </div>
        </div><!-- .wrap -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function is_seo_integration_active(): bool
    {
        $disabled = (bool) (get_option('grp_settings')['grp_disable_seo_integration'] ?? false);
        if ($disabled) {
            return false;
        }
        return (bool) $this->seo->get_active_provider();
    }
}
