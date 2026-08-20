<?php
/**
 * Read-only readiness check for the future top-slider integration.
 *
 * Preparation only. This class decides whether the current installation is
 * safe to move from preparation into the first read-only admin connection.
 * It registers no hooks and writes no data.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Integration_Readiness {
    /**
     * Evaluate whether Phase 1 can be connected without enabling writes.
     *
     * @return array
     */
    public static function inspect() {
        $snapshot = GOS_Slider_Integration::validated_snapshot();
        $validation = isset($snapshot['validation']) && is_array($snapshot['validation'])
            ? $snapshot['validation']
            : [];
        $guard = isset($snapshot['guard']) && is_array($snapshot['guard'])
            ? $snapshot['guard']
            : [];
        $diagnostics = isset($snapshot['diagnostics']) && is_array($snapshot['diagnostics'])
            ? $snapshot['diagnostics']
            : [];

        $blockers = [];

        if (empty($validation['ok'])) {
            $blockers[] = 'contract_validation_failed';
        }
        if (!empty($guard['writes_allowed'])) {
            $blockers[] = 'writes_enabled';
        }
        if (!empty($guard['frontend_takeover_allowed'])) {
            $blockers[] = 'frontend_takeover_enabled';
        }
        if (!empty($guard['migration_allowed'])) {
            $blockers[] = 'migration_enabled';
        }
        if (empty($guard['theme_option_present'])) {
            $blockers[] = 'theme_slider_source_missing';
        }

        // The legacy mobile option may legitimately be absent when no mobile
        // override has ever been saved, so that state is reported but does not
        // block a read-only Phase 1 connection.
        return [
            'phase' => 1,
            'ready' => empty($blockers),
            'read_only' => true,
            'theme_option_present' => !empty($guard['theme_option_present']),
            'mobile_option_present' => !empty($guard['mobile_option_present']),
            'mobile_plugin_active' => !empty($guard['mobile_plugin_active']),
            'diagnostics_ok' => !empty($diagnostics['ok']),
            'blockers' => $blockers,
        ];
    }
}
