<?php
/**
 * Read-only facade for top-slider integration preparation.
 *
 * This class provides one stable entry point for future admin UI and migration
 * code. It does not register hooks, write options, or change frontend output.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration {
    /**
     * Return the complete preparation snapshot.
     *
     * @return array
     */
    public static function snapshot() {
        return [
            'guard' => GOS_Slider_Integration_Guard::status(),
            'state' => GOS_Slider_Integration_State::read(),
            'diagnostics' => GOS_Slider_Integration_Diagnostics::inspect(),
        ];
    }

    /**
     * Preparation phase is intentionally read-only.
     *
     * @return bool
     */
    public static function is_read_only() {
        return GOS_Slider_Integration_Guard::is_read_only();
    }
}
