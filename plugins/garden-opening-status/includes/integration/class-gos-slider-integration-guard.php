<?php
/**
 * Guard conditions for the future top-slider integration.
 *
 * Preparation only. This class has no hooks and performs no writes.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_Guard {
    /**
     * Report whether the current legacy sources are safe to read.
     *
     * @return array
     */
    public static function status() {
        $theme = get_option('dp_options', null);
        $mobile = get_option('mlm_options', null);

        return [
            'theme_option_present' => is_array($theme),
            'mobile_option_present' => is_array($mobile),
            'mobile_plugin_active' => class_exists('MLM_Mobile_Layout_Manager', false),
            'writes_allowed' => false,
            'frontend_takeover_allowed' => false,
            'migration_allowed' => false,
        ];
    }

    /**
     * Integration must remain read-only during the preparation phase.
     *
     * @return bool
     */
    public static function is_read_only() {
        return true;
    }

    /** @return bool */
    public static function writes_allowed() {
        return false;
    }

    /** @return bool */
    public static function frontend_takeover_allowed() {
        return false;
    }

    /** @return bool */
    public static function migration_allowed() {
        return false;
    }
}
