<?php
/**
 * Top-slider settings boundary.
 *
 * PC images stay in the theme's dp_options. Mobile images/positions stay in
 * mlm_options while the legacy frontend is still responsible for rendering.
 * Only the exact slider keys are updated; unrelated theme/mobile settings are
 * preserved.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Settings {
    const THEME_OPTION = 'dp_options';
    const MOBILE_OPTION = 'mlm_options';

    public static function save($payload) {
        $payload = is_array($payload) ? $payload : [];
        $slides = isset($payload['slides']) && is_array($payload['slides']) ? $payload['slides'] : [];

        $theme = get_option(self::THEME_OPTION, []);
        $theme = is_array($theme) ? $theme : [];

        $mobile = get_option(self::MOBILE_OPTION, []);
        $mobile = is_array($mobile) ? $mobile : [];
        $mobile_slider = isset($mobile['slider']) && is_array($mobile['slider']) ? $mobile['slider'] : [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $row = isset($slides[$slot]) && is_array($slides[$slot]) ? $slides[$slot] : [];
            $theme['slider_image' . $slot] = absint($row['pc_image_id'] ?? 0);
            $mobile_slider[$slot] = [
                'image_id' => absint($row['mobile_image_id'] ?? 0),
                'position_x' => self::bounded_int($row['position_x'] ?? 50, 0, 100),
                'position_y' => self::bounded_int($row['position_y'] ?? 50, 0, 100),
            ];
        }

        $mobile['slider'] = $mobile_slider;
        $mobile['top_enabled'] = empty($payload['top_enabled']) ? 0 : 1;
        $mobile['breakpoint'] = self::bounded_int($payload['breakpoint'] ?? 767, 480, 1200);

        update_option(self::THEME_OPTION, $theme, false);
        update_option(self::MOBILE_OPTION, $mobile, false);
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
