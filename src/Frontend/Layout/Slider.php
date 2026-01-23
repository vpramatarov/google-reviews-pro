<?php

declare(strict_types=1);

namespace GRP\Frontend\Layout;

class Slider extends Layout implements LayoutRender
{
    private const string LAYOUT_NAME = 'slider';

    public function render(array $reviews, array $stats, int $limit, string $place_id = '', string $source = 'cpt'): string
    {
        $rating = $stats['ratingValue'];
        $total = $stats['reviewCount'];
        $html = '<div class="grp-wrapper">';
        $html .= '<div class="grp-container">';
        $html .= sprintf(
            '<div class="grp-rating-stats">%s: <span><strong>%s</strong> ★</span> %s %s %s</div>',
            __('Rating', 'google-reviews-pro'),
            $rating,
            __('from', 'google-reviews-pro'),
            $total,
            __('reviews', 'google-reviews-pro')
        );
        $html .= '<div class="grp-slider-wrapper">';
        $html .= '<button class="grp-slider-arrow prev">&lsaquo;</button>';
        $html .= '<div class="grp-slider-track">';

        foreach ($reviews as $review) {
            $html .= $this->render_card($review);
        }

        $html .= '</div>'; // ./grp-slider-track
        $html .= '<button class="grp-slider-arrow next">&rsaquo;</button>';
        $html .= '</div>'; // ./grp-slider-wrapper

        if ($total > $limit) {
            $html .= '<div class="grp-load-more-container">';
            $html .= $this->render_load_more_button($limit, $place_id, self::LAYOUT_NAME);
            $html .= '</div>';
        }

        if ($source !== 'cpt' && !empty($place_id)) {
            $html .= $this->generate_footer_actions($place_id);
        }

        $html .= '</div>'; // ./grp-container
        $html .= '</div>'; // ./grp-wrapper
        return $html;
    }

    public function supports(string $layout): bool
    {
        return self::LAYOUT_NAME === $layout;
    }
}
