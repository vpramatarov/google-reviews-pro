<?php
/**
 * Fired when the plugin is deleted.
 *
 * @package Google_Reviews_Pro
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = get_option('grp_settings');

if (!empty($options['wipe_on_uninstall'])) {
    $reviews = get_posts([
        'post_type'   => 'grp_review',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);

    foreach ($reviews as $review) {
        $thumbnail_id = get_post_thumbnail_id($review->ID);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
        }

        wp_delete_post($review->ID, true);
    }

    delete_option('grp_settings');
    delete_option('grp_last_sync_time');
    wp_clear_scheduled_hook('grp_daily_sync');
}