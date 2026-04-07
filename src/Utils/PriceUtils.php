<?php

declare(strict_types=1);

namespace GRP\Utils;

class PriceUtils
{
    /**
     * Normalizes the price to a number from 0 to 4.
     * Supports formats: 2, "$$", "$1-10", "€€€"
     */
    public static function normalize_price(?string $price): ?int
    {
        if (empty($price)) {
            return null;
        }

        // if it's a number (Google API style)
        if (is_numeric($price)) {
            return (int)$price;
        }

        // If it's a string, count the currency symbols
        // Ex: "$$" -> 2, "$1-10" -> 1, "€€€" -> 3
        preg_match_all('/[\$\€\£\¥\₩]/', $price, $matches);
        $count = count($matches[0]);

        if ($count > 0) {
            // Limit to 4 (Google max)
            return min(4, $count);
        }

        return null;
    }
}