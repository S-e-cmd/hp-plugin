<?php
/**
 * Read-only boundary for the current theme/mobile slider settings.
 *
 * Integration preparation only. This file is intentionally not loaded by the
 * plugin bootstrap yet, so adding it does not change the current runtime.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Legacy_Slider_Source {
    /**
     * Return the current top-slider state without modifying either source.
     *
     * PC images remain owned by the theme's dp_options.
     * Mobile images/positions remain owned by mobile-layout-manager's
     * mlm_options until an explicit migration is implemented.
     *
     * @return array
     */
    public static function read() {
        $theme  = get_option('dp_options', []);
        $mobile = get_option('mlm_options', []);

        $theme  = is_array($theme) ? $theme : [];
        $mobile = is_array($mobile) ? $mobile : [];

        $mobile_slider = isset($mobile['slider']) && is_array($mobile['slider'])
            ? $mobile['slider']
            : [];

        $slides = [];
        $render_index = 0;

        for ($slot = 1; $slot <= 3; $slot++) {
            $pc_image_id = absint($theme['slider_image' . $slot] ?? 0);
            if ($pc_image_id) $render_index++;

            $mobile_row = isset($mobile_slider[$slot]) && is_array($mobile_slider[$slot])
                ? $mobile_slider[$slot]
                : [];

            $slides[$slot] = [
                'slot'            => $slot,
                'render_index'    => $pc_image_id ? $render_index : 0,
                'pc_image_id'     => $pc_image_id,
                'mobile_image_id' => absint($mobile_row['image_id'] ?? 0),
                'position_x'      => self::bounded_int($mobile_row['position_x'] ?? 50, 0, 100),
                'position_y'      => self::bounded_int($mobile_row['position_y'] ?? 50, 0, 100),
            ];
        }

        return [
            'theme_option'  => 'dp_options',
            'mobile_option' => 'mlm_options',
            'top_enabled'   => empty($mobile['top_enabled']) ? 0 : 1,
            'breakpoint'    => self::bounded_int($mobile['breakpoint'] ?? 767, 480, 1200),
            'slides'        => $slides,
        ];
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
