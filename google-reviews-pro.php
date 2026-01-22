<?php
/**
 * Plugin Name: Google Reviews Pro
 * Version: 1.0.0
 * Text Domain: google-reviews-pro
 * Author: Velizar Pramatarov <velizarpramatrov@yahoo.com>
 * Author URI: https://vpramatarov.eu
 * Domain Path: /languages
 * Requires PHP: 8.3
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use GRP\Api\Handler as ApiHandler;
use GRP\Core\SeoIntegrator;

final class GoogleReviewsPro
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'load_textdomain']);

        register_activation_hook(__FILE__, function() {
            if (version_compare(PHP_VERSION, '8.3.0', '<')) {
                wp_die(
                    __('This plugin requires PHP version 8.3 or higher. Please update the PHP version on your server.', 'google-reviews-pro'),
                    __('Incompatible PHP version', 'google-reviews-pro'),
                    ['back_link' => true]
                );
            }
        });

        $this->init_components();

        register_deactivation_hook(__FILE__, function() {
            wp_clear_scheduled_hook('grp_daily_sync');
        });
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('google-reviews-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function init_components(): void
    {
        $api = new ApiHandler();
        $seo = new SeoIntegrator();

        /**
         * @note: cron_schedules must be loaded before plugins_loaded
         */
        add_filter('cron_schedules', function(array $schedules) use ($api) {
            return $api->add_custom_cron_schedules($schedules);
        });

        $display = new GRP\Frontend\Display($api, $seo);
        new GRP\Admin\Settings($seo, $api);
        new GRP\Ajax\Handler($api, $display);
        new GRP\Core\PostType();

        add_action('update_option_grp_settings', function() use ($api) {
            $api->manage_cron();
        }, 10, 0);

        add_action('grp_daily_sync', function() use ($api) {
            $api->sync_reviews();
        });
    }
}

add_action('plugins_loaded', ['GoogleReviewsPro', 'get_instance']);