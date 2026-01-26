<?php

declare(strict_types=1);

namespace GRP\Frontend\Layout;

class Grid extends Layout implements LayoutRender
{

    private const string LAYOUT_NAME = 'grid';

    public function render(array $reviews, array $stats, int $limit, string $place_id = '', string $source = 'cpt'): string
    {
        $rating = $stats['ratingValue'];
        $total = $stats['reviewCount'];
        $place_meta = get_option('grp_locations_db', [])[$place_id] ?? null;

        $html = '<div class="grp-wrapper">';
        $html .= '<div class="grp-container">';

        if ($place_meta) {
            $html .= sprintf('<div class="grp-business-name"><h2>%s</h2></div>', $place_meta['name']);
        }

        $html .= sprintf(
            '<div class="grp-rating-stats">%s: <span><strong>%s</strong> ★</span> %s %s %s</div>',
            __('Rating', 'google-reviews-pro'),
            $rating,
            __('from', 'google-reviews-pro'),
            $total,
            __('reviews', 'google-reviews-pro')
        );
        $html .= '<div class="grp-grid">';

        foreach ($reviews as $review) {
            $html .= $this->render_card($review);
        }

        $html .= '</div>'; // ./grp-grid

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
