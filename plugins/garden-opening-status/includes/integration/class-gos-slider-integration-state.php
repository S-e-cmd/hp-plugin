<?php
/**
 * Normalized top-slider state shared by the integrated admin screen.
 *
 * Existing option names remain unchanged during the handoff.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_State {
    public static function read() {
        if (!class_exists('GOS_Legacy_Slider_Source')) {
            return ['available' => false, 'slides' => []];
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
                    'source' => 'dp_options',
                ],
                'mobile' => [
                    'image_id' => absint($row['mobile_image_id'] ?? 0),
                    'position_x' => self::bounded_int($row['position_x'] ?? 50, 0, 100),
                    'position_y' => self::bounded_int($row['position_y'] ?? 50, 0, 100),
                    'source' => 'mlm_options',
                ],
                'render_index' => absint($row['render_index'] ?? 0),
                'renderable' => !empty($row['pc_image_id']),
            ];
        }

        return [
            'available' => true,
            'mobile_enabled' => empty($legacy['top_enabled']) ? 0 : 1,
            'breakpoint' => self::bounded_int($legacy['breakpoint'] ?? 767, 480, 1200),
            'slides' => $slides,
        ];
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
