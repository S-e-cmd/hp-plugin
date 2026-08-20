<?php
/**
 * Stable read-only contract for the future top-slider integration.
 *
 * Preparation only. This class declares the snapshot shape and validates data
 * returned by the integration facade. It registers no hooks and writes no data.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_Contract {
    const VERSION = 1;

    /**
     * Return the public contract metadata used by future UI/migration code.
     *
     * @return array
     */
    public static function schema() {
        return [
            'contract_version' => self::VERSION,
            'slots' => [1, 2, 3],
            'sources' => [
                'pc' => 'dp_options',
                'mobile' => 'mlm_options',
            ],
            'read_only' => true,
            'required_sections' => [
                'guard',
                'state',
                'diagnostics',
            ],
        ];
    }

    /**
     * Validate a facade snapshot without mutating it.
     *
     * @param array $snapshot
     * @return array
     */
    public static function validate($snapshot) {
        $errors = [];
        $snapshot = is_array($snapshot) ? $snapshot : [];

        foreach (self::schema()['required_sections'] as $section) {
            if (!isset($snapshot[$section]) || !is_array($snapshot[$section])) {
                $errors[] = 'missing_section:' . $section;
            }
        }

        $guard = isset($snapshot['guard']) && is_array($snapshot['guard'])
            ? $snapshot['guard']
            : [];

        if (!empty($guard['writes_allowed'])) {
            $errors[] = 'writes_must_remain_disabled';
        }
        if (!empty($guard['frontend_takeover_allowed'])) {
            $errors[] = 'frontend_takeover_must_remain_disabled';
        }
        if (!empty($guard['migration_allowed'])) {
            $errors[] = 'migration_must_remain_disabled';
        }

        $state = isset($snapshot['state']) && is_array($snapshot['state'])
            ? $snapshot['state']
            : [];
        $slides = isset($state['slides']) && is_array($state['slides'])
            ? $state['slides']
            : [];

        foreach ([1, 2, 3] as $slot) {
            if (!isset($slides[$slot]) || !is_array($slides[$slot])) {
                $errors[] = 'missing_slide:' . $slot;
                continue;
            }

            $slide = $slides[$slot];
            $pc = isset($slide['pc']) && is_array($slide['pc']) ? $slide['pc'] : [];
            $mobile = isset($slide['mobile']) && is_array($slide['mobile']) ? $slide['mobile'] : [];

            if (($pc['source'] ?? '') !== 'theme_dp_options') {
                $errors[] = 'unexpected_pc_source:' . $slot;
            }
            if (($mobile['source'] ?? '') !== 'mlm_options') {
                $errors[] = 'unexpected_mobile_source:' . $slot;
            }
            if (!empty($pc['editable']) || !empty($mobile['editable'])) {
                $errors[] = 'editing_must_remain_disabled:' . $slot;
            }
        }

        return [
            'ok' => empty($errors),
            'contract_version' => self::VERSION,
            'errors' => $errors,
        ];
    }
}
