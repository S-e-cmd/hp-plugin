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
     * Return the snapshot together with contract validation.
     *
     * This remains read-only and is intended for the first integration UI so it
     * can refuse to proceed when the expected legacy/theme shape has drifted.
     *
     * @return array
     */
    public static function validated_snapshot() {
        $snapshot = self::snapshot();
        $snapshot['contract'] = GOS_Slider_Integration_Contract::schema();
        $snapshot['validation'] = GOS_Slider_Integration_Contract::validate($snapshot);
        return $snapshot;
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
