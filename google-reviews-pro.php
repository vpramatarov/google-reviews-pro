<?php
/**
 * Plugin Name: Google Reviews Pro
 * Plugin URI: https://github.com/vpramatarov/google-reviews-pro
 * Version: 1.1.2
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

const GRP_VERSION = '1.1.2';
const GRP_MAX_REVIEW_LIMIT = 5;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use GRP\Api\Handler as ApiHandler;
use GRP\Core\CronManager;
use GRP\Core\SeoIntegrator;
use GRP\Frontend\Display;

final class GoogleReviewsPro
{
    private static ?self $instance = null;

    private Display $display;

    private ApiHandler $apiHandler;

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

            if (!extension_loaded('zip') || !extension_loaded('mbstring')) {
                wp_die(__('This plugin requires PHP Zip and Mbstring extensions.', 'google-reviews-pro'));
            }
        });

        $this->init_components();

        register_deactivation_hook(__FILE__, function() {
            wp_clear_scheduled_hook('grp_daily_sync');
        });
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize Singleton.');
    }

    public function get_display(): Display
    {
        return $this->display;
    }

    public function get_api_handler(): ApiHandler
    {
        return $this->apiHandler;
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('google-reviews-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function init_components(): void
    {
        $this->apiHandler = new ApiHandler();
        $seo = new SeoIntegrator();
        $cronManager = new CronManager($this->apiHandler->get_api_options());

        /**
         * @note: cron_schedules must be loaded before plugins_loaded
         */
        add_filter('cron_schedules', function(array $schedules) use ($cronManager) {
            return $cronManager->add_custom_cron_schedules($schedules);
        });

        $exporter = new GRP\Core\ReviewExporter();
        $importer = new GRP\Core\ReviewImporter();
        $this->display = new GRP\Frontend\Display($this->apiHandler, $seo);
        new GRP\Admin\Settings($seo, $this->apiHandler, $exporter, $importer);
        new GRP\Ajax\Handler($this->apiHandler, $this->display);
        new GRP\Core\PostType();
        new GRP\Core\Blocks($this->apiHandler, $this->display);
        new GRP\Integrations\Manager();

        add_action('update_option_grp_settings', function() use ($cronManager) {
            $cronManager->manage_cron();
        }, 10, 0);

        add_action('grp_daily_sync', function() {
            $this->apiHandler->sync_reviews();
        });
    }
}

add_action('plugins_loaded', ['GoogleReviewsPro', 'get_instance']);