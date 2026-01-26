<?php

declare(strict_types=1);

namespace GRP\Core;

final class CronManager
{
    private array $options;

    /**
     * @param array{
     *     "data_source": string,
     *     "google_api_key": string,
     *     "place_id": string,
     *     "serpapi_key": string,
     *     "serpapi_data_id": string,
     *     "scrapingdog_api_key": string,
     *     "grp_business_name": string,
     *     "grp_address": string,
     *     "grp_phone": string,
     *     "grp_latitude": float|string,
     *     "grp_longitude": float|string,
     *     "grp_price": string,
     *     "grp_review_limit": int,
     *     "grp_text_color": string,
     *     "grp_bg_color": string,
     *     "grp_accent_color": string,
     *     "grp_btn_text_color": string,
     *     "grp_layout": string,
     *     "grp_min_rating": int,
     *     "grp_sort_order": string,
     *     "email_alerts": bool|int,
     *     "notification_email": string,
     *     "serpapi_pages": int,
     *     "auto_sync": bool|int,
     *     "sync_frequency": string,
     *     "grp_hide_empty": bool|int
     * }|array{} $options
     */
    public function __construct(array $options) {
        $this->options = $options;
    }

    public function manage_cron(): void
    {
        $is_enabled = !empty($this->options['auto_sync']);
        $frequency = $this->options['sync_frequency'] ?? 'weekly';

        // Remove old hook, to be sure there's no duplication
        // or old schedules when changing frequency.
        wp_clear_scheduled_hook('grp_daily_sync');

        if ($is_enabled) {
            wp_schedule_event(time(), $frequency, 'grp_daily_sync');
        }
    }

    public function add_custom_cron_schedules(array $schedules): array
    {
        $schedules['monthly'] = [
            'interval' => 2592000, // 30 days in seconds
            'display'  => __('Once Monthly', 'google-reviews-pro')
        ];
        return $schedules;
    }
}