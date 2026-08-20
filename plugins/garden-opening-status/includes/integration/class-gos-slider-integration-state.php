<?php
/**
 * Normalized read-only state for the future top-slider integration.
 *
 * Preparation only. This class does not save options, register hooks, render UI,
 * or disable the legacy mobile-layout-manager plugin.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_State {
    /**
     * Build the normalized integration state from the current legacy sources.
     *
     * @return array
     */
    public static function read() {
        if (!class_exists('GOS_Legacy_Slider_Source')) {
            return [
                'available' => false,
                'reason'    => 'legacy_source_not_loaded',
                'slides'    => [],
            ];
        }

        $legacy = GOS_Legacy_Slider_Source::read();
        $slides = [];

        foreach ((array)($legacy['slides'] ?? []) as $slot => $row) {
            $slot = (int)$slot;
            if ($slot < 1 || $slot > 3 || !is_array($row)) continue;

            $slides[$slot] = [
                'slot' => $slot,
                'pc' => [
                    'image_id' => absint($row['pc_image_id'] ?? 0),
                    'source'   => 'theme_dp_options',
                    'editable' => false,
                ],
                'mobile' => [
                    'image_id'   => absint($row['mobile_image_id'] ?? 0),
                    'position_x' => self::bounded_int($row['position_x'] ?? 50, 0, 100),
                    'position_y' => self::bounded_int($row['position_y'] ?? 50, 0, 100),
                    'source'     => 'mlm_options',
                    'editable'   => false,
                ],
                'render_index' => absint($row['render_index'] ?? 0),
                'renderable'   => !empty($row['pc_image_id']),
            ];
        }

        return [
            'available'        => true,
            'mode'             => 'legacy_read_only',
            'mobile_enabled'   => empty($legacy['top_enabled']) ? 0 : 1,
            'breakpoint'       => self::bounded_int($legacy['breakpoint'] ?? 767, 480, 1200),
            'pc_source'        => 'dp_options',
            'mobile_source'    => 'mlm_options',
            'writes_enabled'   => false,
            'legacy_required'  => true,
            'slides'           => $slides,
        ];
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
