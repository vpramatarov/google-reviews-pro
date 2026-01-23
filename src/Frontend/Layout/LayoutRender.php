<?php

declare(strict_types=1);

namespace GRP\Frontend\Layout;

interface LayoutRender
{
    public function render(array $reviews, array $stats, int $limit, string $place_id = '', string $source = 'cpt'): string;

    public function render_card(array $review): string;


    public function supports(string $layout): bool;
}
