<?php

declare(strict_types=1);

namespace GRP\Integrations\Elementor;

use \Elementor\Widget_Base;
use \Elementor\Controls_Manager;
use GRP\Core\SeoIntegrator;
use GRP\Frontend\Display;
use GRP\Api\Handler as ApiHandler;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ReviewWidget extends Widget_Base {

    private Display $display;

    private ApiHandler $api;

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        $this->api = new ApiHandler();
        $seo = new SeoIntegrator();
        $this->display = new Display($this->api, $seo);
    }

    // Специален конструктор за Dependency Injection (Elementor съвместим)
    // Тъй като parent::__construct не позволява лесно DI, ние го подаваме при register() в Manager.php
    // и го присвояваме тук.
    public function __construct_with_dependency(Display $display): void
    {
        $this->display = $display;
    }

    public function get_name(): string
    {
        return 'grp_reviews_widget';
    }

    public function get_title(): ?string
    {
        return __('Google Reviews', 'google-reviews-pro');
    }


    public function get_icon(): string
    {
        return 'eicon-star';
    }

    /**
     * @return string[]
     */
    public function get_categories(): array
    {
        return ['grp-category', 'general'];
    }

    protected function register_controls(): void
    {
        $locations = $this->api->get_stored_locations();
        $options = ['' => __('Default (Global)', 'google-reviews-pro')];
        foreach ($locations as $loc) {
            $options[$loc['place_id']] = sprintf('%s (%d)', $loc['name'], $loc['count']);
        }

        // SECTION: Content
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Settings', 'google-reviews-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'place_id',
            [
                'label' => __('Select Location', 'google-reviews-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => $options,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout Style', 'google-reviews-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    ''       => __('Default (From Settings)', 'google-reviews-pro'),
                    'grid'   => __('Grid', 'google-reviews-pro'),
                    'list'   => __('List', 'google-reviews-pro'),
                    'slider' => __('Slider', 'google-reviews-pro'),
                    'badge'  => __('Badge', 'google-reviews-pro'),
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $atts = [
            'place_id' => $settings['place_id'],
            'layout' => $settings['layout']
        ];

        echo $this->display->render_shortcode($atts);
    }
}