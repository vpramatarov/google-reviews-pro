<?php

declare(strict_types=1);

namespace GRP\Admin;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use GRP\Api\Handler as ApiHandler;
use GRP\Core\SeoIntegrator;
use GRP\Core\ReviewExporter;
use GRP\Core\ReviewImporter;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

readonly class Settings
{
    public function __construct(
        private SeoIntegrator $seo,
        private ApiHandler $api,
        private ReviewExporter $exporter,
        private ReviewImporter $importer
    ) {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_export_request']);
        add_action('admin_init', [$this, 'handle_import_request']);
        add_action('admin_footer', [$this, 'render_admin_scripts']);
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

        // --- SECTION API CONFIG ---
        add_settings_section('grp_main', __('Data Source Configuration', 'google-reviews-pro'), null, 'grp-settings');
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
                __('SerpApi Key', 'google-reviews-pro'),
                [$this, 'serpapi_key_html'],
                'grp-settings',
                'grp_main'
        );

        add_settings_field(
            'business_finder',
            __('Find Your Business', 'google-reviews-pro'),
            [$this, 'finder_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'place_id',
            __('Place ID', 'google-reviews-pro'),
            [$this, 'place_id_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'serpapi_data_id',
            __('Data ID', 'google-reviews-pro'),
            [$this, 'serpapi_data_id_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'serpapi_pages',
            __('Max Pagination Pages', 'google-reviews-pro'),
            [$this, 'serpapi_pages_html'],
            'grp-settings',
            'grp_main'
        );

        add_settings_field(
            'grp_review_limit',
            __('Initial Reviews Limit', 'google-reviews-pro'),
            [$this, 'limit_html'],
            'grp-settings',
            'grp_styling'
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

        add_settings_section(
            'grp_locations',
            __('Multi-Location Reference', 'google-reviews-pro'),
            [$this, 'locations_section_desc'],
            'grp-settings'
        );

        add_settings_field(
            'grp_stored_locations',
            __('Stored Locations', 'google-reviews-pro'),
            [$this, 'stored_locations_html'],
            'grp-settings',
            'grp_locations'
        );

        // --- FILTERING & SORTING ---
        add_settings_section(
            'grp_filtering',
            __('Filtering & Moderation', 'google-reviews-pro'),
            [$this, 'filtering_section_html'],
            'grp-settings'
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

        // --- SECTION: STYLING ---
        add_settings_section(
                'grp_styling',
                __('Styling Configuration', 'google-reviews-pro'),
                null,
                'grp-settings'
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

        // --- SECTION SEO SCHEMA CONFIG ---
        add_settings_section(
            'grp_seo',
            __('SEO & Business Schema', 'google-reviews-pro'),
            [$this, 'seo_section_desc'],
            'grp-settings'
        );

        add_settings_field(
            'grp_business_name',
            __('Business Name', 'google-reviews-pro'),
            [$this, 'business_name_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_latitude',
            __('Latitude', 'google-reviews-pro'),
            [$this, 'latitude_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_longitude',
            __('Longitude','google-reviews-pro'),
            [$this, 'longitude_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_address',
            __('Business Address', 'google-reviews-pro'),
            [$this, 'address_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_phone',
            __('Telephone', 'google-reviews-pro'),
            [$this, 'phone_html'],
            'grp-settings',
            'grp_seo'
        );

        add_settings_field(
            'grp_price',
            __('Price Range', 'google-reviews-pro'),
            [$this, 'price_html'],
            'grp-settings',
            'grp_seo'
        );

        // --- REVIEW COLLECTION ---
        add_settings_section(
            'grp_collection',
            __('Review Collection Tools', 'google-reviews-pro'),
            [$this, 'collection_section_html'],
            'grp-settings'
        );

        // --- Backup & Migration ---
        add_settings_section(
            'grp_backup',
            __('Backup & Migration', 'google-reviews-pro'),
            [$this, 'backup_section_html'],
            'grp-settings'
        );

        // --- ADVANCED & NOTIFICATIONS ---
        add_settings_section(
            'grp_advanced',
            __('Advanced Settings', 'google-reviews-pro'),
            null,
            'grp-settings'
        );

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

    public function sanitize($input): array
    {
        $clean = [
            'data_source' => sanitize_text_field($input['data_source']),
            'google_api_key' => sanitize_text_field($input['google_api_key'] ?? ''),
            'place_id' => sanitize_text_field($input['place_id'] ?? ''),
            'serpapi_data_id' => sanitize_text_field($input['serpapi_data_id'] ?? ''),
            'serpapi_key' => sanitize_text_field($input['serpapi_key'] ?? ''),
            'serpapi_pages' => absint($input['serpapi_pages'] ?? 5),
            'grp_review_limit' => max(1, min(GRP_MAX_REVIEW_LIMIT, absint($input['grp_review_limit'] ?? 3))), // make sure it's between 1-5
            'auto_sync' => isset($input['auto_sync']) ? 1 : 0,
            'sync_frequency' => in_array($input['sync_frequency'], ['daily', 'weekly', 'monthly']) ? $input['sync_frequency'] : 'weekly',
            'grp_min_rating' => absint($input['grp_min_rating'] ?? 0),
            'grp_sort_order' => sanitize_text_field($input['grp_sort_order'] ?? 'date_desc'),
            'grp_business_name' => sanitize_text_field($input['grp_business_name'] ?? ''),
            'grp_latitude' => sanitize_text_field($input['grp_latitude'] ?? ''),
            'grp_longitude' => sanitize_text_field($input['grp_longitude'] ?? ''),
            'grp_address' => sanitize_textarea_field($input['grp_address'] ?? ''),
            'grp_phone' => sanitize_text_field($input['grp_phone'] ?? ''),
            'grp_price' => sanitize_text_field($input['grp_price'] ?? ''),
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
                add_settings_error(
                        'grp_settings',
                        'invalid_email',
                        __('The notification email provided is invalid. Saved with default admin email.', 'google-reviews-pro')
                );
                $clean['notification_email'] = '';
            }
        } else {
            $clean['notification_email'] = '';
        }

        return $clean;
    }

    public function source_html(): void
    {
        $val = get_option('grp_settings')['data_source'] ?? '';
        ?>
        <select name="grp_settings[data_source]" id="grp_data_source" required>
            <option value=""></option>
            <option value="google" <?php selected($val, 'google'); ?>>Google Places API (Official)</option>
            <option value="serpapi" <?php selected($val, 'serpapi'); ?>>SerpApi (Scraper)</option>
            <option value="cpt" <?php selected($val, 'cpt'); ?>>Manual Entry (Custom Post Type)</option>
        </select>
        <p class="description"><?php _e('Choose where to fetch reviews from.', 'google-reviews-pro'); ?></p>
        <?php
    }

    public function seo_section_desc(): void
    {
        $provider = $this->seo->get_active_provider();

        if ($provider) {
            $name = match($provider) {
                'rank_math' => 'Rank Math SEO',
                'aioseo' => 'All in One SEO',
                'seopress' => 'SEOPress',
                'tsf' => 'The SEO Framework',
                'yoast' => 'Yoast SEO',
                default => 'SEO Plugin'
            };

            echo '<div class="notice notice-info inline" style="margin-left:0; margin-bottom:15px;"><p>';
            printf(
                __('<strong>Integrated with %s:</strong> Business data is synced from your SEO plugin to prevent conflicts. Fields below are read-only.', 'google-reviews-pro'),
                $name
            );
            echo '</p></div>';
        } else {
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

        if (empty($google_api_key) && empty($serpapi_key)) {
            echo '<p class="description">' . __('Please enter and save an API Key first to use the Business Finder.', 'google-reviews-pro') . '</p>';
            return;
        }

        $business_name = esc_attr(get_option('grp_settings')['grp_business_name'] ?? '');
        printf('<div id="grp-finder-box" style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="grp_finder_query" class="regular-text" placeholder="%s" value="%s">
                <button type="button" id="grp-find-btn" class="button button-secondary">%s</button>
                <span class="spinner" id="grp-finder-spinner" style="float:none; margin:0;"></span>
            </div>
            <p class="description" id="grp-finder-msg"></p>',
            __('Enter business name (e.g. Pizza Mario New York)', 'google-reviews-pro'),
            $business_name,
            __('Search & Auto-fill', 'google-reviews-pro')
        );
    }

    public function place_id_html(): void
    {
        $placeId = esc_attr(get_option('grp_settings')['place_id'] ?? '');
        printf('<p><input type="text" id="place_id" name="grp_settings[place_id]" value="%s" class="regular-text" ></p>', $placeId);
        echo '<p class="description">Find your Place ID <a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">here</a>.</p>';
    }

    public function serpapi_key_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['serpapi_key'] ?? '');
        printf(
            '<input type="text" id="grp_serpapi_key" name="grp_settings[serpapi_key]" value="%s" class="regular-text">',
            $val
        );
    }

    public function serpapi_data_id_html(): void
    {
        $dataId = esc_attr(get_option('grp_settings')['serpapi_data_id'] ?? '');
        printf('<p><input type="text" id="serpapi_data_id" name="grp_settings[serpapi_data_id]" value="%s" class="regular-text" ></p>', $dataId);
    }

    public function serpapi_pages_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['serpapi_pages'] ?? 5);
        printf('<input type="number" name="grp_settings[serpapi_pages]" value="%d" min="1" max="50" class="small-text">', $val);
        echo '<p class="description">' . __('Limit the number of pages to fetch (1 page ≈ 10 reviews). Warning: High values increase sync time.', 'google-reviews-pro') . '</p>';
    }

    public function limit_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['grp_review_limit'] ?? 3);
        ?>
        <input type="number" name="grp_settings[grp_review_limit]" value="<?php echo $val; ?>" min="1" max="5" class="small-text">
        <p class="description">
            <?php _e('Number of reviews to show initially. For Grid layout, this also determines the number of columns (Max 5).', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    public function auto_sync_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['auto_sync'] ?? 0);
        printf(
            '<input type="checkbox" name="grp_settings[auto_sync]" value="1" %s>',
            checked(1, $val, false)
        );
        echo '<p class="description">' . __('Automatically fetch reviews once every 24 hours.', 'google-reviews-pro') . '</p>';
    }

    public function sync_frequency_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['sync_frequency'] ?? 'weekly');
        ?>
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

    public function filtering_section_html(): void
    {
        $options = get_option('grp_settings');
        $hide_empty = isset($options['grp_hide_empty']) && $options['grp_hide_empty'];
        $min_rating = $options['grp_min_rating'] ?? '1';
        ?>
        <fieldset>
            <label for="grp_hide_empty">
                <input type="checkbox" id="grp_hide_empty" name="grp_settings[grp_hide_empty]" value="1" <?php checked($hide_empty, true); ?>>
                <?php _e('Hide reviews without text (Star-only ratings)', 'google-reviews-pro'); ?>
            </label>
            <p class="description">
                <?php _e('Enable this to show only reviews that contain actual comments.', 'google-reviews-pro'); ?>
            </p>
        </fieldset>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="grp_min_rating">
                            <?php _e('Minimum Rating to Show:', 'google-reviews-pro'); ?>
                        </label>
                    </th>
                    <td>
                        <select name="grp_settings[grp_min_rating]" id="grp_min_rating">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($min_rating, $i); ?>><?php echo $i; ?> Stars</option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function stored_locations_html(): void
    {
        $locations = $this->api->get_stored_locations();
        $layout = esc_attr(get_option('grp_settings')['grp_layout'] ?? 'grid');

        if (empty($locations)) {
            echo '<p class="description">' . __('No locations found yet. Sync some reviews first.', 'google-reviews-pro') . '</p>';
            return;
        }

        ?>
        <table class="widefat fixed striped" style="max-width: 1200px;">
            <thead>
            <tr>
                <th style="width: 30%; padding-left: 10px;"><?php _e('Place ID', 'google-reviews-pro'); ?></th>
                <th style="width: 10%; padding-left: 10px;"><?php _e('Reviews Count', 'google-reviews-pro'); ?></th>
                <th style="width: 60%; padding-left: 10px;"><?php _e('Shortcode Snippet', 'google-reviews-pro'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($locations as $loc): ?>
                <tr>
                    <td><code><?php echo esc_html($loc['place_id']); ?></code></td>
                    <td><?php echo esc_html($loc['count']); ?></td>
                    <td>
                        <input type="text"
                               readonly
                               class="regular-text"
                               value='[google_reviews place_id="<?php echo esc_attr($loc['place_id']); ?>" layout="<?php echo $layout; ?>"]'
                               style="width: 100%; font-family: monospace; background: #f9f9f9; color: #555;"
                               onclick="this.select();">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function min_rating_html(): void
    {
        $val = absint(get_option('grp_settings')['grp_min_rating'] ?? 0);
        ?>
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
        $val = esc_attr( get_option('grp_settings')['grp_sort_order'] ?? 'date_desc');
        ?>
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
        $val = esc_attr(get_option('grp_settings')['grp_layout'] ?? 'grid');
        ?>
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
        $is_managed = !empty($local_data['name']);
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
        $is_managed = !empty($local['lat']);
        $val = $is_managed ? $local['lat'] : esc_attr(get_option('grp_settings')['grp_latitude'] ?? '');

        printf(
            '<input type="text" id="grp_latitude" name="grp_settings[grp_latitude]" value="%s" class="small-text" %s>',
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
        $is_managed = !empty($local['lng']);
        $val = $is_managed ? $local['lng'] : esc_attr(get_option('grp_settings')['grp_longitude'] ?? '');

        printf(
            '<input type="text" id="grp_longitude" name="grp_settings[grp_longitude]" value="%s" class="small-text" %s>',
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
        $is_managed = !empty($local['address']);
        $val = $is_managed ? $local['address'] : esc_textarea(get_option('grp_settings')['grp_address'] ?? '');

        printf(
            '<textarea name="grp_settings[grp_address]" class="large-text" rows="3" placeholder="123 Main St..." %s>%s</textarea>',
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
        $is_managed = !empty($local['phone']);
        $val = $is_managed ? $local['phone'] : esc_attr(get_option('grp_settings')['grp_phone'] ?? '');

        printf(
            '<input type="text" name="grp_settings[grp_phone]" value="%s" class="regular-text" placeholder="+359888888888" %s>',
            $val,
            $is_managed ? 'disabled style="background:#f0f0f1; color:#555; border-color:#ccc;"' : ''
        );

        if ($is_managed) {
            echo '<p class="description">' . __('Synced from SEO plugin.', 'google-reviews-pro') . '</p>';
        }
    }

    public function price_html(): void
    {
        $local = $this->seo->get_local_data();
        $is_managed = !empty($local['price_range']);
        $val = $is_managed ? $local['price_range'] : esc_attr(get_option('grp_settings')['grp_price'] ?? '$$');

        if ($is_managed) {
            printf(
                '<input type="text" value="%s" class="regular-text" disabled style="background:#f0f0f1; color:#555;">',
                $val
            );
            echo '<p class="description">'.__('Synced from SEO plugin.', 'google-reviews-pro').'</p>';
        } else { ?>
            <select name="grp_settings[grp_price]">
                <option value="$" <?php selected($val, '$'); ?>>$ (Cheap)</option>
                <option value="$$" <?php selected($val, '$$'); ?>>$$ (Moderate)</option>
                <option value="$$$" <?php selected($val, '$$$'); ?>>$$$ (Expensive)</option>
                <option value="$$$$" <?php selected($val, '$$$$'); ?>>$$$$ (Luxury)</option>
            </select>
            <?php
        }
    }

    public function collection_section_html(): void
    {
        $options = get_option('grp_settings');
        $place_id = esc_attr($options['place_id'] ?? '');
        $business_name = esc_attr($options['grp_business_name'] ?? __('Review Us', 'google-reviews-pro'));

        if (empty($place_id)) {
            echo '<div class="notice notice-warning inline"><p>' .
                    __('Please configure and save a Place ID in the "Data Source" section first.', 'google-reviews-pro') .
                    '</p></div>';
            return;
        }

        $review_url = "https://search.google.com/local/writereview?placeid=" . $place_id;

        try {
            $qr_options = new QROptions([
                'version'      => 5, // Balance between density and readability
                'outputType'   => QROutputInterface::GDIMAGE_PNG,
                'eccLevel'     => EccLevel::L, // Low error correction for cleaner code
                'scale'        => 20, // Pixel size
                'imageBase64'  => true, // returns data:image/png;base64...
            ]);

            $qrcode = new QRCode($qr_options);
            $qr_image_src = $qrcode->render($review_url);
        } catch (\Throwable $e) {
            $qr_image_src = '';
            echo '<div class="notice notice-error inline"><p>' . sprintf(__('Error generating QR code: %s', 'google-reviews-pro'), $e->getMessage()) . '</p></div>';
        }

        ?>
        <div class="grp-qr-wrapper" style="display: flex; gap: 40px; align-items: flex-start; margin-top: 20px;">

            <div style="flex: 1; max-width: 400px;">
                <p><?php _e('Scan this code to test the experience:', 'google-reviews-pro'); ?></p>

                <div id="grp-qr-code" style="background: #fff; padding: 20px; border: 1px solid #ddd; display: inline-block; border-radius: 8px;">
                    <?php if ($qr_image_src): ?>
                        <img src="<?php echo $qr_image_src; ?>" alt="QR Code" style="width: 150px; height: 150px; display: block;">
                    <?php endif; ?>
                </div>

                <p style="margin-top: 15px;">
                    <strong><?php _e('Direct Link:', 'google-reviews-pro'); ?></strong><br>
                    <input type="text" class="large-text" value="<?php echo esc_url($review_url); ?>" readonly onclick="this.select();">
                </p>
            </div>

            <div style="flex: 1;">
                <h3><?php _e('Printable Card Preview', 'google-reviews-pro'); ?></h3>
                <p class="description"><?php _e('Print this card and place it on your counter or tables.', 'google-reviews-pro'); ?></p>

                <div id="grp-print-card" style="
                    border: 1px solid #ccc;
                    background: white;
                    width: 300px;
                    padding: 30px;
                    text-align: center;
                    font-family: sans-serif;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                ">
                    <h2 style="color: #333; margin-top: 0;"><?php _e('Rate Us on Google', 'google-reviews-pro'); ?></h2>
                    <p style="color: #666;"><?php _e('Loving your experience at', 'google-reviews-pro'); ?> <br><strong><?php echo $business_name; ?></strong>?</p>

                    <div style="margin: 20px auto;">
                        <?php if ($qr_image_src): ?>
                            <img src="<?php echo $qr_image_src; ?>" alt="QR Code" style="width: 150px; height: 150px;">
                        <?php endif; ?>
                    </div>

                    <p style="font-size: 12px; color: #999;"><?php _e('Scan with your phone camera', 'google-reviews-pro'); ?></p>

                    <div style="margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <span style="color: #fbbc04; font-size: 20px;">★★★★★</span>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="button" class="button button-primary" onclick="printCard()">
                        <span class="dashicons dashicons-printer" style="line-height: 28px;"></span>
                        <?php _e('Print Card', 'google-reviews-pro'); ?>
                    </button>
                    <a href="<?php echo $qr_image_src; ?>" download="google-review-qr.png" class="button button-secondary">
                        <span class="dashicons dashicons-download" style="line-height: 28px;"></span>
                        <?php _e('Download QR Image', 'google-reviews-pro'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    public function backup_section_html(): void
    {
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <h3><?php _e('Export Reviews', 'google-reviews-pro'); ?></h3>
            <p><?php _e('Download a backup of your current reviews.', 'google-reviews-pro'); ?></p>
            <div style="display: flex; gap: 15px;">
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_json">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Export JSON', 'google-reviews-pro'); ?>
                    </button>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_csv">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Export CSV', 'google-reviews-pro'); ?>
                    </button>
                </form>
                <form method="post" action="">
                    <?php wp_nonce_field('grp_export_action', 'grp_export_nonce'); ?>
                    <input type="hidden" name="grp_action" value="export_zip">
                    <button class="button button-primary"><?php _e('Download Full Backup (.ZIP)', 'google-reviews-pro'); ?></button>
                </form>
            </div>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
            <h3><?php _e('Import Reviews', 'google-reviews-pro'); ?></h3>
            <p><?php _e('Upload a JSON or Zip file previously exported from this plugin.', 'google-reviews-pro'); ?></p>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('grp_import_action', 'grp_import_nonce'); ?>
                <input type="hidden" name="grp_action" value="import_file">

                <input type="file" name="grp_import_file" accept=".json,.zip,.csv" required>
                <br><br>
                <button type="submit" class="button button-primary">
                    <?php _e('Import Reviews', 'google-reviews-pro'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    public function handle_import_request(): void
    {
        if (!isset($_POST['grp_action']) || $_POST['grp_action'] !== 'import_file' || !isset($_FILES['grp_import_file'])) {
            return;
        }

        if (!check_admin_referer('grp_import_action', 'grp_import_nonce')) {
            wp_die(__('Security check failed', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'google-reviews-pro'));
        }

        $file = $_FILES['grp_import_file'];

        // check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('grp_settings', 'upload_error', __('File upload failed.', 'google-reviews-pro'));
            return;
        }

        // Check MIME type (basic)
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'json') {
            add_settings_error('grp_settings', 'invalid_type', __('Only JSON files are allowed.', 'google-reviews-pro'));
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if ($ext === 'zip') {
            $stats = $this->importer->import_zip($file['tmp_name']);
        } elseif ($ext === 'json') {
            $stats = $this->importer->import_json($file['tmp_name']);
        } elseif ($ext === 'csv') {
            $stats = $this->importer->import_csv($file['tmp_name']);
        } else {
            add_settings_error(
                'grp_settings',
                'invalid_type',
                __('Invalid file type. Allowed: .json, .csv, .zip', 'google-reviews-pro')
            );
            return;
        }

        if ($stats['errors'] > 0 && $stats['success'] === 0) {
            add_settings_error('grp_settings', 'import_fail', __('Failed to parse JSON file.', 'google-reviews-pro'));
        } else {
            add_settings_error(
                'grp_settings',
                'import_success',
                sprintf(
                    __('Import complete! Added: %d, Skipped (Duplicates): %d, Errors: %d', 'google-reviews-pro'),
                    $stats['success'],
                    $stats['skipped'],
                    $stats['errors']
                ),
                'success'
            );
        }
    }

    public function handle_export_request(): void
    {
        if (!isset($_POST['grp_action']) || !isset($_POST['grp_export_nonce'])) {
            return;
        }

        // Check Nonce + Permissions
        if (!wp_verify_nonce($_POST['grp_export_nonce'], 'grp_export_action')) {
            wp_die(__('Security check failed', 'google-reviews-pro'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'google-reviews-pro'));
        }

        if ($_POST['grp_action'] === 'export_zip') {
            $zip_path = $this->exporter->generate_backup_zip();

            if (file_exists($zip_path)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="grp-backup-full.zip"');
                header('Content-Length: ' . filesize($zip_path));
                readfile($zip_path);

                unlink($zip_path);
                exit;
            } else {
                wp_die('Error creating ZIP file.');
            }
        }

        $data = $this->exporter->get_raw_data();
        $filename = 'google-reviews-' . date('Y-m-d') . '-' . count($data);

        if ($_POST['grp_action'] === 'export_json') {
            $this->send_json_download($data, $filename . '.json');
        } elseif ($_POST['grp_action'] === 'export_csv') {
            $this->send_csv_download($data, $filename . '.csv');
        }
    }

    public function email_alerts_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['email_alerts'] ?? 0);
        ?>
        <label>
            <input type="checkbox" id="grp_email_alerts" name="grp_settings[email_alerts]" value="1" <?php checked(1, $val); ?>>
            <?php _e('Send email notifications when new reviews are imported.', 'google-reviews-pro'); ?>
        </label>
        <?php
    }

    public function notification_email_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['notification_email'] ?? '');
        $is_enabled = !empty(get_option('grp_settings')['email_alerts']);

        ?>
        <input type="email"
               id="grp_notification_email"
               name="grp_settings[notification_email]"
               value="<?php echo $val; ?>"
               class="regular-text"
                <?php echo $is_enabled ? '' : 'disabled'; ?>>

        <p class="description">
            <?php _e('Leave empty to use the WordPress Admin email.', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    public function wipe_html(): void
    {
        $val = esc_attr(get_option('grp_settings')['wipe_on_uninstall'] ?? 0);
        ?>
        <label>
            <input type="checkbox" name="grp_settings[wipe_on_uninstall]" value="1" <?php checked(1, $val); ?>>
            <?php _e('Delete all reviews, local images, and settings when deleting the plugin.', 'google-reviews-pro'); ?>
        </label>
        <p class="description" style="color: #d63638;">
            <?php _e('Warning: This action is irreversible. If checked, all your collected reviews will be lost upon uninstallation.', 'google-reviews-pro'); ?>
        </p>
        <?php
    }

    public function render_admin_scripts(): void
    {
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_grp-settings') {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
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

                const $serpPagesRow = $serpPages.closest('tr');
                const $autoSyncRow = $autoSync.closest('tr');
                const $googleKeyRow = $inputGoogleApiKey.closest('tr');
                const $placeIdRow = $inputPlaceId.closest('tr');
                const $serpApiDataIdRow = $inputSerpApiDataId.closest('tr');
                const $serpApiRow = $inputSerpApiKey.closest('tr');
                const $finderRow = $('#grp-finder-box').closest('tr');
                const $findBtn = $('#grp-find-btn');
                const $spinner = $('#grp-finder-spinner');
                const $msg = $('#grp-finder-msg');

                function toggleFields() {
                    const val = $sourceSelect.val();

                    $googleKeyRow.hide();
                    $placeIdRow.hide();
                    $serpApiRow.hide();
                    $serpApiDataIdRow.hide();
                    $syncBtn.hide();

                    $inputGoogleApiKey.prop('required', false);
                    $inputPlaceId.prop('required', false);
                    $inputSerpApiKey.prop('required', false);

                    if (val === 'google') {
                        $inputGoogleApiKey.prop('required', true);
                        $inputPlaceId.prop('required', true);
                        $googleKeyRow.show();
                        $syncBtn.show();
                    } else if (val === 'serpapi') {
                        $inputSerpApiKey.prop('required', true);
                        $inputSerpApiDataId.prop('required', true);
                        $serpApiRow.show();
                        $serpApiDataIdRow.show();
                        $syncBtn.show();
                    }

                    if (val === 'cpt') {
                        $autoSyncRow.hide();
                    } else {
                        $autoSyncRow.show();
                    }

                    $placeIdRow.toggle(val !== 'cpt');
                    $serpPagesRow.toggle(val === 'serpapi');
                    $finderRow.toggle(val !== 'cpt');
                }

                toggleFields();
                $sourceSelect.on('change', toggleFields);

                // AJAX Sync Logic
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
                            $syncStatus.css('color', 'red').text('Error: ' + response.data);
                        }
                    }).fail(function() {
                        $syncStatus.css('color', 'red').text('<?php _e('Server error occurred.', 'google-reviews-pro'); ?>');
                    }).always(function() {
                        $syncBtn.prop('disabled', false).text('<?php _e('Sync Reviews Now', 'google-reviews-pro'); ?>');
                    });
                });

                $findBtn.on('click', function(e) {
                    e.preventDefault();
                    const query = $('#grp_finder_query').val();
                    const source = $sourceSelect.val();
                    const apiKey = (source === 'google') ? $('#grp_google_key').val() : $('#grp_serpapi_key').val();

                    if(!query || !apiKey) {
                        alert('<?php _e('Please enter a business name and ensure your API key is filled in.', 'google-reviews-pro'); ?>');
                        return;
                    }

                    $spinner.addClass('is-active');
                    $findBtn.prop('disabled', true);
                    $msg.text('');

                    $.post(ajaxurl, {
                        action: 'grp_find_business',
                        nonce: '<?php echo wp_create_nonce("grp_nonce"); ?>',
                        query: query
                    }, function(res) {
                        $spinner.removeClass('is-active');
                        $findBtn.prop('disabled', false);

                        if(res.success) {
                            const data = res.data;
                            if (source === 'google') {
                                $('#place_id').val(data.place_id).css('background-color', '#e6fffa');
                            } else if (source === 'serpapi') {
                                $('#serpapi_data_id').val(data.data_id).css('background-color', '#e6fffa');
                                $('#place_id').val(data.place_id).css('background-color', '#e6fffa');
                            }

                            $('#grp_business_name').val(data.name).css('background-color', '#e6fffa');

                            if(data.lat) $('#grp_latitude').val(data.lat).css('background-color', '#e6fffa');
                            if(data.lng) $('#grp_longitude').val(data.lng).css('background-color', '#e6fffa');
                            if(data.address) $('#grp_address').val(data.address).css('background-color', '#e6fffa');

                            $msg.css('color', 'green').html('<?php _e('Business found! Fields autofilled. Please <strong>Save Changes</strong>.', 'google-reviews-pro'); ?>');
                        } else {
                            $msg.css('color', 'red').text('<?php _e('Error: ', 'google-reviews-pro'); ?>' + (res.data || '<?php _e('Not found', 'google-reviews-pro'); ?>'));
                        }
                    });
                });

                const $emailCheckbox = $('#grp_email_alerts');
                const $emailInput = $('#grp_notification_email');

                function toggleEmailInput() {
                    if ($emailCheckbox.is(':checked')) {
                        $emailInput.prop('disabled', false).css('opacity', 1);
                    } else {
                        $emailInput.prop('disabled', true).css('opacity', 0.6).val('');
                    }
                }

                // Init state
                toggleEmailInput();

                // On Change
                $emailCheckbox.on('change', toggleEmailInput);
            });

            function printCard() {
                const cardContent = document.getElementById('grp-print-card').outerHTML;
                const win = window.open('', '', 'height=600,width=800');
                win.document.write('<html><head><title>Print Card</title>');
                win.document.write('</head><body style="display:flex; justify-content:center; align-items:center; height:100vh;">');
                win.document.write(cardContent);
                win.document.write('</body></html>');
                win.document.close();
                // We wait a moment for the images to load in the new window before printing.
                setTimeout(function() {
                    win.focus();
                    win.print();
                    win.close();
                }, 250);
            }
        </script>
        <?php
    }

    public function render_page(): void
    {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && (get_option('grp_settings')['auto_sync'] ?? 0)) {
            echo '<div class="notice notice-warning"><p>';
            _e('Warning: WP_CRON is disabled in your wp-config.php. Auto-sync will not work unless you set up a system cron job.', 'google-reviews-pro');
            echo '</p></div>';
        }

        $last_sync = get_option('grp_last_sync_time');
        $display_time = $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync) : __('Never', 'google-reviews-pro');

        echo '<div class="wrap"><h1>'.__('Settings', 'google-reviews-pro').'</h1><form method="post" action="options.php">';
        settings_fields('grp_group');
        do_settings_sections('grp-settings');
        submit_button();

        echo '<hr><div id="grp-sync-container" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">';
        echo '<h3>' . __('Manual Synchronization', 'google-reviews-pro') . '</h3>';
        echo '<p><strong>' . __('Last Synced:', 'google-reviews-pro') . '</strong> <span id="grp-last-sync-time">' . esc_html($display_time) . '</span></p>';
        echo '<button id="grp-sync-btn" class="button button-secondary">' . __('Sync Reviews Now', 'google-reviews-pro') . '</button>';
        echo '<span id="grp-sync-status" style="margin-left: 10px;"></span>';
        echo '</div></form></div>';
    }

    private function send_json_download(array $data, string $filename): void
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function send_csv_download(array $data, string $filename): void
    {
        if (empty($data)) {
            wp_die(__('No reviews to export.', 'google-reviews-pro'));
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fputs($output, "\xEF\xBB\xBF");

        // Headers
        fputcsv($output, array_keys($data[0]));

        // Rows
        foreach ($data as $row) {
            // Formatting the date for CSV to be readable (in JSON we store it as a timestamp)
            $row['time'] = date('Y-m-d H:i:s', (int)$row['time']);
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
