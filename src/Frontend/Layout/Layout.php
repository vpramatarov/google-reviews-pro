<?php

declare(strict_types=1);

namespace GRP\Frontend\Layout;

abstract class Layout
{
    public function render_card(array $review): string
    {
        $text = esc_html($review['text']);
        $has_more = mb_strlen($text, 'UTF-8') > 150;
        $default_icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2NjYyI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCAtNC0xLjc5IDQtNHMtMS43OS00LTQtNC00IDEuNzktNCA0IDEuNzkgNCA0IDR6bTAgMmMtMi42NyAwLTggMS4zNC04IDR2MmgyMHYtMmMtMC0yLjY2LTUuMzMtNC04LTR6Ii8+PC9zdmc+';
        $photo_url = !empty($review['profile_photo_url']) ? esc_url($review['profile_photo_url']) : $default_icon;
        $rating = isset($review['rating']) ? (float)$review['rating'] : 5.0;

        $html = '<div class="grp-card">';
        $html .= '<div class="grp-card-header">';
        $html .= sprintf('<img src="%s" alt="%s" class="grp-profile-img" width="40" height="40" loading="lazy">', $photo_url, esc_attr__('User Avatar', 'google-reviews-pro'));
        $html .= '<div class="grp-header-info"><strong>'.esc_html($review['author_name']).'</strong>' . $this->render_stars($rating) . '</div>';
        $html .= '</div>'; // ./grp-card-header
        $html .= sprintf('<div class="grp-review-text">%s</div>', $text);

        if ($has_more) {
            $html .= '<span class="grp-read-more-btn" data-more="'.__('Read More', 'google-reviews-pro').'" data-less="'.__('Read Less', 'google-reviews-pro').'">'.__('Read More', 'google-reviews-pro').'</span>';
        }

        $html .= '</div>'; // ./grp-card
        return $html;
    }

    protected function render_load_more_button(int $limit, string $place_id, string $layout): string
    {
        $nonce = wp_create_nonce('grp_nonce');
        return sprintf(
            '<div class="grp-load-more-container">
                        <button class="grp-load-more-btn" data-offset="%d" data-limit="%d" data-nonce="%s" data-place-id="%s" data-layout="%s">%s</button>
                    </div>',
            $limit, $limit, $nonce, esc_attr($place_id), $layout, __('Load More', 'google-reviews-pro')
        );
    }

    protected function render_stars(float $rating): string
    {
        $rounded_rating = round($rating);
        $html = '<div class="grp-stars" aria-label="'.sprintf(__('Rated %s out of 5', 'google-reviews-pro'), $rating).'">';

        for ($i = 1; $i <= 5; $i++) {
            $class = ($i <= $rounded_rating) ? 'filled' : '';
            $html .= '<span class="grp-star ' . $class . '">★</span>';
        }

        $html .= '</div>';
        return $html;
    }

    protected function generate_footer_actions(string $place_id): string
    {
        $write_url = "https://search.google.com/local/writereview?placeid=" . esc_attr($place_id);
        $view_url = "https://search.google.com/local/reviews?placeid=" . esc_attr($place_id);

        $html = '<div class="grp-footer-actions">';

        $html .= sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="grp-write-btn">%s</a>',
            esc_url($write_url),
            esc_html__('Write a Review', 'google-reviews-pro')
        );

        $html .= sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="grp-view-all-link">%s</a>',
            esc_url($view_url),
            esc_html__('View all reviews on Google', 'google-reviews-pro')
        );

        $html .= '</div>';

        return $html;
    }
}
