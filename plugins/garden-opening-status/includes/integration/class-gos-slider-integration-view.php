<?php
/**
 * Read-only view model for the future top-slider admin UI.
 *
 * Preparation only. This class resolves attachment display data from the
 * validated integration snapshot. It does not register hooks or write options.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_View {
    /**
     * Build display-ready slider data without changing either legacy source.
     *
     * @return array
     */
    public static function read() {
        $snapshot = GOS_Slider_Integration::validated_snapshot();
        $state = isset($snapshot['state']) && is_array($snapshot['state']) ? $snapshot['state'] : [];
        $slides = isset($state['slides']) && is_array($state['slides']) ? $state['slides'] : [];
        $view_slides = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $slide = isset($slides[$slot]) && is_array($slides[$slot]) ? $slides[$slot] : [];
            $pc = isset($slide['pc']) && is_array($slide['pc']) ? $slide['pc'] : [];
            $mobile = isset($slide['mobile']) && is_array($slide['mobile']) ? $slide['mobile'] : [];

            $pc_id = absint($pc['image_id'] ?? 0);
            $mobile_id = absint($mobile['image_id'] ?? 0);

            $view_slides[$slot] = [
                'slot' => $slot,
                'renderable' => !empty($slide['renderable']),
                'render_index' => absint($slide['render_index'] ?? 0),
                'pc' => self::attachment_view($pc_id) + [
                    'source' => (string)($pc['source'] ?? ''),
                    'editable' => !empty($pc['editable']),
                ],
                'mobile' => self::attachment_view($mobile_id) + [
                    'position_x' => self::bounded_int($mobile['position_x'] ?? 50, 0, 100),
                    'position_y' => self::bounded_int($mobile['position_y'] ?? 50, 0, 100),
                    'source' => (string)($mobile['source'] ?? ''),
                    'editable' => !empty($mobile['editable']),
                ],
            ];
        }

        return [
            'read_only' => true,
            'contract_ok' => !empty($snapshot['contract']['ok']),
            'mobile_enabled' => !empty($state['mobile_enabled']),
            'breakpoint' => self::bounded_int($state['breakpoint'] ?? 767, 480, 1200),
            'slides' => $view_slides,
            'diagnostics' => isset($snapshot['diagnostics']) && is_array($snapshot['diagnostics'])
                ? $snapshot['diagnostics']
                : [],
        ];
    }

    private static function attachment_view($attachment_id) {
        $attachment_id = absint($attachment_id);
        $url = '';
        $thumbnail_url = '';
        $title = '';

        if ($attachment_id) {
            $full = wp_get_attachment_image_url($attachment_id, 'full');
            $thumb = wp_get_attachment_image_url($attachment_id, 'medium');
            $url = $full ? esc_url_raw($full) : '';
            $thumbnail_url = $thumb ? esc_url_raw($thumb) : $url;
            $title = sanitize_text_field((string)get_the_title($attachment_id));
        }

        return [
            'image_id' => $attachment_id,
            'exists' => $attachment_id > 0 && $url !== '',
            'url' => $url,
            'thumbnail_url' => $thumbnail_url,
            'title' => $title,
        ];
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
