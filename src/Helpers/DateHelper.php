<?php

namespace Dumbo\Helpers;

use DateTimeZone;

class DateHelper
{
    /**
     * Get the current timestamp
     *
     * @param string|null $timezone
     * @return int
     */
    public static function now(?string $timezone = null): int
    {
        if ($timezone === null) {
            return time();
        }

        $date = new \DateTime("now", new DateTimeZone($timezone));
        return $date->getTimestamp();
    }

    /**
     * Generate a timezone select dropdown
     *
     * @param string $class
     * @param string $default
     * @param int $what
     * @param string|null $country
     * @return string
     */
    public static function timezoneSelect(
        string $class = "",
        string $default = "",
        int $what = DateTimeZone::ALL,
        ?string $country = null
    ): string {
        $timezones = DateTimeZone::listIdentifiers($what, $country);

        $select = '<select name="timezone" class="' . $class . '">';
        foreach ($timezones as $timezone) {
            $selected = $timezone === $default ? " selected" : "";
            $select .=
                '<option value="' .
                $timezone .
                '"' .
                $selected .
                ">" .
                $timezone .
                "</option>";
        }
        $select .= "</select>";

        return $select;
    }

    /**
     * Format a date
     *
     * @param string $format
     * @param int|string $time
     * @return string
     */
    public static function format(string $format, $time = "now"): string
    {
        if (is_string($time)) {
            $time = strtotime($time);
        }
        return date($format, $time);
    }

    /**
     * Get a list of month names
     *
     * @param bool $abbreviated
     * @return array
     */
    public static function getMonthNames(bool $abbreviated = false): array
    {
        $format = $abbreviated ? "M" : "F";
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = date($format, mktime(0, 0, 0, $i, 1));
        }
        return $months;
    }

    /**
     * Get a list of day names
     *
     * @param bool $abbreviated
     * @return array
     */
    public static function getDayNames(bool $abbreviated = false): array
    {
        $format = $abbreviated ? "D" : "l";
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = date($format, strtotime("Sunday +{$i} days"));
        }
        return $days;
    }
}
