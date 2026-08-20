<?php
/**
 * Integration preparation bootstrap.
 *
 * This file only loads the read-only slider integration classes. It is not yet
 * required by the plugin main file, so current runtime behavior is unchanged.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-gos-legacy-slider-source.php';
require_once __DIR__ . '/class-gos-slider-integration-state.php';
require_once __DIR__ . '/class-gos-slider-integration-guard.php';
require_once __DIR__ . '/class-gos-slider-integration-diagnostics.php';
require_once __DIR__ . '/class-gos-slider-integration.php';
