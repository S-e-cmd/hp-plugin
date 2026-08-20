<?php
/**
 * Shared source for current theme/mobile slider settings.
 *
 * Uses the existing dp_options / mlm_options contracts and, only during an
 * authenticated mobile preview, overlays the current user's unsaved drafts.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Legacy_Slider_Source {
    public static function read() {
        $theme  = get_option('dp_options', []);
        $mobile = get_option('mlm_options', []);

        $theme  = is_array($theme) ? $theme : [];
        $mobile = is_array($mobile) ? $mobile : [];

        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $theme_draft = get_user_meta($user_id, '_gos_slider_preview_theme', true);
            $mobile_draft = get_user_meta($user_id, '_mlm_preview_draft', true);
            if (is_array($theme_draft)) $theme = array_replace_recursive($theme, $theme_draft);
            if (is_array($mobile_draft)) $mobile = array_replace_recursive($mobile, $mobile_draft);
        }

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
