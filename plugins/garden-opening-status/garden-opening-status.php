<?php
/**
 * Plugin Name: 開催情報・開催状況管理
 * Description: 春・秋・冬の開催概要を一元管理し、各会期ページとトップページの開催状況へ共通出力します。
 * Version: 3.3.2
 * Author: Site Admin
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/garden-opening-status-core.php';
require_once __DIR__ . '/includes/integration/bootstrap.php';

GOS_Slider_Integration::register();
