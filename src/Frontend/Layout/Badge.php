<?php

declare(strict_types=1);

namespace GRP\Frontend\Layout;

class Badge extends Layout implements LayoutRender
{
    private const string LAYOUT_NAME = 'badge';

    public function render(array $reviews, array $stats, int $limit, string $place_id = '', string $source = 'cpt'): string
    {
        $rating = $stats['ratingValue'];
        $total = $stats['reviewCount'];
        // Google G Icon
//        $g_icon = 'https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg';

        $html = '<div class="grp-badge-trigger">';
//        $html .= '<img src="'.$g_icon.'" class="grp-badge-icon">';
        $html .= '<span><strong>'.$rating.'</strong> ★</span>';
        $html .= '</div>';

        $html .= '<div class="grp-badge-modal">';
        $html .= '<div class="grp-container">';
        $html .= '<span class="grp-badge-close">×</span>';
        $html .= '<div class="grp-list-view">';
        foreach ($reviews as $review) {
            $html .= $this->render_card($review);
        }
        $html .= '</div>'; // /.grp-list-view

        if ($total > $limit) {
            $html .= $this->render_load_more_button($limit, $place_id, self::LAYOUT_NAME);
        }

        $html .= '</div>'; // /.grp-container
        $html .= '</div>'; // /.grp-badge-modal

        return $html;
    }

    public function supports(string $layout): bool
    {
        return self::LAYOUT_NAME === $layout;
    }
}
