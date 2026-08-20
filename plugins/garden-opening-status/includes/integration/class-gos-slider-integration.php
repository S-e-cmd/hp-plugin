<?php
/**
 * Top-slider integration entry point.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration {
    private static $registered = false;

    public static function register() {
        if (self::$registered) return;
        self::$registered = true;
        GOS_Slider_Admin::register_hooks();
    }

    public static function read() {
        return GOS_Slider_Integration_State::read();
    }
}
