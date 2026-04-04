<?php

declare(strict_types=1);

namespace GRP\Utils;

class DateUtils
{
    /**
     * @return string[]
     */
    public static function get_days_of_week_l10n(): array
    {
        return [
            __('monday', 'google-reviews-pro') => 'monday',
            __('tuesday', 'google-reviews-pro') => 'tuesday',
            __('wednesday', 'google-reviews-pro') => 'wednesday',
            __('thursday', 'google-reviews-pro') => 'thursday',
            __('friday', 'google-reviews-pro') => 'friday',
            __('saturday', 'google-reviews-pro') => 'saturday',
            __('sunday', 'google-reviews-pro') => 'sunday',
        ];
    }

    /**
     * Converts a 12-hour format schedule data into a strict 24-hour format.
     *
     * @param string[]|null $periods
     * @return string[]
     */
    public static function convert_periods_to_24hour_format(?array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        foreach ($periods as $day => $timeRange) {
            $timeRange = trim($timeRange);

            if (strtolower($timeRange) === 'closed') {
                continue;
            }

            $standardizedDash = preg_replace('/[\p{Pd}]/u', '-', $timeRange);
            $cleanRange = preg_replace('/[^\d:a-zA-Z-]/', '', $standardizedDash);

            // Split the string into start and end times
            $times = explode('-', $cleanRange);

            // --- SMART AM/PM INFERENCE ---
            if (count($times) === 2) {
                $start = $times[0];
                $end = $times[1];

                // If start time lacks AM/PM (no letters) BUT end time has it
                if (!preg_match('/[a-zA-Z]/', $start) && preg_match('/[a-zA-Z]/', $end)) {

                    // Extract just the hour numbers to compare them
                    $startHour = (int) explode(':', $start)[0];
                    $endHour = (int) explode(':', $end)[0];

                    // Extract the exact modifier used at the end (e.g., 'PM')
                    $endModifier = preg_replace('/[^a-zA-Z]/', '', $end);

                    // Business Logic:
                    // 1. If start hour < end hour (e.g., 1 < 5), they share the PM -> 1 PM.
                    // 2. If start hour is 12, it usually means Noon -> 12 PM.
                    // 3. Otherwise (e.g., 9 is not < 5), the start is morning -> 9 AM.
                    if ($startHour < $endHour || $startHour === 12) {
                        $times[0] = $start . $endModifier;
                    } else {
                        $times[0] = $start . 'AM';
                    }
                }
            }

            $formattedTimes = array_map(function($time) {
                $timestamp = strtotime($time);
                return $timestamp !== false ? date('H:i', $timestamp) : $time;
            }, $times);

            $periods[$day] = implode('-', $formattedTimes);
        }

        return $periods;
    }

    /**
     * Converts an associative array of hours to Schema.org OpeningHoursSpecification.
     *
     * @param string[] $hours e.g. ['monday' => '9:00 AM - 5:00 PM']
     * @return array<string[]> The structured data structure.
     */
    public static function convert_periods_to_schema(array $hours): array
    {
        $structured_data = [];
        $days_l10n = static::get_days_of_week_l10n();

        foreach ($hours as $day => $time_range) {
            // The input can contain Narrow No-Break Space (U+202F), Thin Space (U+2009),
            // and En-dash (U+2013). We normalize them to standard ASCII.
            $clean_range = str_replace(
                ["\u{202F}", "\u{2009}", "\u{2013}", "–"],
                [" ", " ", "-", "-"],
                $time_range
            );

            // Handle "Closed" status
            // Schema.org best practice is to simply omit closed days.
            if (str_contains(strtolower($clean_range), 'closed')) {
                continue;
            }

            // Separate Start and End times
            $parts = explode('-', $clean_range);
            if (!$parts || count($parts) !== 2) {
                continue; // Skip malformed entries
            }

            // Convert to 24-hour format (required by Schema.org) - e.g., "9:00 AM" -> "09:00"
            $opens = date("H:i", strtotime(preg_replace("/[^0-9APM:\s]/", "", trim($parts[0]))));
            $closes = date("H:i", strtotime(preg_replace("/[^0-9APM:\s]/", "", trim($parts[1]))));

            // Capitalize the array key (e.g., "monday" -> "Monday")
            $structured_data[] = [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => "https://schema.org/" . ucfirst($days_l10n[$day] ?? $day),
                "opens" => $opens,
                "closes" => $closes
            ];
        }

        return $structured_data;
    }
}