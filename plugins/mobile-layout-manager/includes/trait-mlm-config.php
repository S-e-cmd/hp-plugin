<?php
/**
 * Settings, validation and theme-slider lookup.
 *
 * @package MLM_Mobile_Layout_Manager
 */

if (!defined('ABSPATH')) exit;

trait MLM_Config_Trait {
    private static function defaults() {
        return [
            'enabled' => 0,
            'top_enabled' => 0,
            'breakpoint' => 767,
            'global_font_scale' => 100,
            'global_heading_scale' => 100,
            'global_side_padding' => 20,
            'button_min_height' => 44,
            'slider' => [
                1 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
                2 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
                3 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
            ],
        ];
    }

    private static function options() {
        $saved = get_option(self::OPTION, []);
        $o = array_replace_recursive(self::defaults(), is_array($saved) ? $saved : []);
        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) {
            $draft = get_user_meta(get_current_user_id(), '_mlm_preview_draft', true);
            if (is_array($draft)) $o = array_replace_recursive($o, $draft);
        }
        return $o;
    }

    private static function sanitize_options_input($input) {
        $out = self::defaults();
        $out['enabled'] = empty($input['enabled']) ? 0 : 1;
        $out['top_enabled'] = empty($input['top_enabled']) ? 0 : 1;
        $out['breakpoint'] = self::bounded_int($input['breakpoint'] ?? 767, 480, 1200);
        $out['global_font_scale'] = self::bounded_int($input['global_font_scale'] ?? 100, 70, 160);
        $out['global_heading_scale'] = self::bounded_int($input['global_heading_scale'] ?? 100, 70, 180);
        $out['global_side_padding'] = self::bounded_int($input['global_side_padding'] ?? 20, 0, 60);
        $out['button_min_height'] = self::bounded_int($input['button_min_height'] ?? 44, 32, 80);

        for ($i = 1; $i <= 3; $i++) {
            $src = $input['slider'][$i] ?? [];
            $out['slider'][$i] = [
                'image_id' => absint($src['image_id'] ?? 0),
                'position_x' => self::bounded_int($src['position_x'] ?? 50, 0, 100),
                'position_y' => self::bounded_int($src['position_y'] ?? 50, 0, 100),
            ];
        }

        return $out;
    }

    public static function save_options() {
        if (!is_admin() || empty($_POST['mlm_action'])) return;
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);

        $out = self::sanitize_options_input(wp_unslash($_POST));
        update_option(self::OPTION, $out, false);
        delete_user_meta(get_current_user_id(), '_mlm_preview_draft');
        wp_safe_redirect(add_query_arg(['page' => 'mobile-layout-manager', 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    public static function ajax_save_preview_draft() {
        check_ajax_referer('mlm_preview_draft', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => '権限がありません。'], 403);

        $draft = self::sanitize_options_input(wp_unslash($_POST));
        update_user_meta(get_current_user_id(), '_mlm_preview_draft', $draft);
        wp_send_json_success(['message' => 'プレビューへ一時反映しました。']);
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }

    private static function original_slider_data() {
        $dp = get_option('dp_options', []);
        $rows = [];
        $render_index = 0;
        for ($slot = 1; $slot <= 3; $slot++) {
            $original_id = absint($dp['slider_image' . $slot] ?? 0);
            if ($original_id) $render_index++;
            $rows[$slot] = [
                'original_id' => $original_id,
                'render_index' => $original_id ? $render_index : 0,
                'original_url' => $original_id ? wp_get_attachment_image_url($original_id, 'medium') : '',
            ];
        }
        return $rows;
    }
}
