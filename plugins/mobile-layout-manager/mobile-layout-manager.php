<?php
/**
 * Plugin Name: モバイル表示管理
 * Description: 既存テーマを変更せず、トップスライダーと投稿・固定ページのスマホ表示を上書きします。
 * Version: 1.6.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/trait-mlm-config.php';
require_once __DIR__ . '/includes/trait-mlm-admin.php';
require_once __DIR__ . '/includes/trait-mlm-frontend.php';

final class MLM_Mobile_Layout_Manager {
    const OPTION = 'mlm_options';
    const NONCE  = 'mlm_save_options';
    const VERSION = '1.6.2';

    use MLM_Config_Trait;
    use MLM_Admin_Trait;
    use MLM_Frontend_Trait;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'save_options']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_ajax_mlm_save_preview_draft', [__CLASS__, 'ajax_save_preview_draft']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_post_meta']);
        add_action('wp_head', [__CLASS__, 'frontend_css'], 99);
        add_action('wp_footer', [__CLASS__, 'frontend_slider_js'], 99);
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_preview'], 90);
        add_filter('body_class', [__CLASS__, 'body_classes']);
        add_action('send_headers', [__CLASS__, 'preview_no_cache']);
    }
}

MLM_Mobile_Layout_Manager::init();
