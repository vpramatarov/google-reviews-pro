<?php

declare(strict_types=1);

namespace GRP\Core;

use GRP\Api\Handler;
use GRP\Frontend\Display;

final readonly class Blocks
{
    public function __construct(private Handler $api, private Display $display)
    {
        add_action('init', [$this, 'register_blocks']);
    }

    public function register_blocks(): void
    {
        $locations = $this->api->get_stored_locations();

        $location_options = [
            ['label' => __('Default (Global)', 'google-reviews-pro'), 'value' => '']
        ];

        foreach ($locations as $loc) {
            $location_options[] = [
                'label' => sprintf('%s (%d %s)', $loc['name'], $loc['count'], __('reviews', 'google-reviews-pro')),
                'value' => $loc['place_id']
            ];
        }

        wp_register_script(
            'grp-block-editor-js',
            plugin_dir_url(dirname(__DIR__)) . 'blocks/reviews/index.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'],
            GRP_VERSION,
            ['in_footer' => true, 'strategy' => 'async']
        );

        wp_localize_script('grp-block-editor-js', 'grpData', [
            'locations' => $location_options
        ]);

        register_block_type(dirname(dirname(__DIR__)) . '/blocks/reviews', [
            'editor_script'   => 'grp-block-editor-js',
            'render_callback' => [$this, 'render_dynamic_block']
        ]);
    }

    public function render_dynamic_block(array $attributes): string
    {
        return $this->display->render_shortcode($attributes);
    }
}