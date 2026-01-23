<?php

declare(strict_types=1);

namespace GRP\Integrations;

use GRP\Integrations\Elementor\ReviewWidget;

class Manager
{
    public function __construct()
    {
        add_action('init', [$this, 'init']);
    }

    public function init(): void
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Register our category for better organization
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);

        // Register widget
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    public function register_category($elements_manager): void
    {
        $elements_manager->add_category(
            'grp-category',
            [
                'title' => __('Google Reviews Pro', 'google-reviews-pro'),
                'icon'  => 'fa fa-star',
            ]
        );
    }

    public function register_widgets($widgets_manager): void
    {
        require_once __DIR__ . '/Elementor/ReviewWidget.php';

        $widgets_manager->register(new ReviewWidget());
    }
}