<?php
/**
 * Read-only diagnostics for slider integration preparation.
 *
 * No options are written and no frontend hooks are registered here.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_Diagnostics {
    /**
     * Inspect whether the legacy/theme sources are readable and internally sane.
     *
     * @return array
     */
    public static function inspect() {
        $state = GOS_Slider_Integration_State::read();
        $slides = isset($state['slides']) && is_array($state['slides']) ? $state['slides'] : [];

        $issues = [];
        $renderable = 0;
        $mobile_overrides = 0;

        for ($slot = 1; $slot <= 3; $slot++) {
            $slide = isset($slides[$slot]) && is_array($slides[$slot]) ? $slides[$slot] : [];
            $pc = isset($slide['pc']) && is_array($slide['pc']) ? $slide['pc'] : [];
            $mobile = isset($slide['mobile']) && is_array($slide['mobile']) ? $slide['mobile'] : [];

            $pc_id = absint($pc['image_id'] ?? 0);
            $mobile_id = absint($mobile['image_id'] ?? 0);
            $is_renderable = !empty($slide['renderable']);

            if ($is_renderable) $renderable++;
            if ($mobile_id) $mobile_overrides++;

            if ($mobile_id && !$pc_id) {
                $issues[] = [
                    'code' => 'mobile_without_pc',
                    'slot' => $slot,
                    'message' => 'スマホ画像は設定されていますが、テーマ側PC画像が未設定のため現行テーマではスライドとして生成されません。',
                ];
            }

            foreach (['position_x', 'position_y'] as $key) {
                $value = isset($mobile[$key]) ? (int)$mobile[$key] : 50;
                if ($value < 0 || $value > 100) {
                    $issues[] = [
                        'code' => 'position_out_of_range',
                        'slot' => $slot,
                        'field' => $key,
                        'message' => 'スマホ画像位置が0〜100の範囲外です。',
                    ];
                }
            }
        }

        return [
            'ok' => empty($issues),
            'writes_allowed' => GOS_Slider_Integration_Guard::writes_allowed(),
            'frontend_takeover_allowed' => GOS_Slider_Integration_Guard::frontend_takeover_allowed(),
            'migration_allowed' => GOS_Slider_Integration_Guard::migration_allowed(),
            'top_enabled' => !empty($state['top_enabled']),
            'breakpoint' => (int)($state['breakpoint'] ?? 767),
            'renderable_slides' => $renderable,
            'mobile_overrides' => $mobile_overrides,
            'issues' => $issues,
        ];
    }
}
