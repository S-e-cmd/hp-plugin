<?php
/**
 * Top-slider integration preparation bootstrap.
 *
 * Keep the preparation seam deliberately small: read the two existing sources,
 * normalize them once, and expose one stable read-only entry point.
 * No hooks, writes, migration, or frontend takeover live here.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-gos-legacy-slider-source.php';
require_once __DIR__ . '/class-gos-slider-integration-state.php';
require_once __DIR__ . '/class-gos-slider-integration.php';
