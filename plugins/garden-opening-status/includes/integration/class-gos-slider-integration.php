<?php
/**
 * Stable read-only entry point for the future top-slider integration.
 *
 * Preparation only. Existing theme/mobile options remain the sources of truth.
 * This class does not register hooks, write options, migrate data, or change
 * frontend output.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration {
    /**
     * Return the normalized current slider state.
     *
     * Future integration code should read through this method instead of
     * reaching into dp_options or mlm_options directly.
     *
     * @return array
     */
    public static function read() {
        return GOS_Slider_Integration_State::read();
    }

    /**
     * Preparation remains read-only until the actual ownership switch.
     *
     * @return bool
     */
    public static function is_read_only() {
        return true;
    }
}
