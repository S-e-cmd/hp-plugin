<?php
/**
 * Plugin Name: 開催情報・開催状況管理
 * Description: 春・秋・冬の開催概要を一元管理し、各会期ページとトップページの開催状況へ共通出力します。
 * Version: 3.2.23
 * Author: Site Admin
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Garden_Opening_Status_V3 {
    const OPTION = 'garden_opening_status_options';
    const VERSION_OPTION = 'garden_opening_status_version';
    const VERSION = '3.2.23';
    const NONCE = 'gos_v3_save';
    const PREVIEW_NONCE = 'gos_v3_preview';
    const LAYOUTS_OPTION = 'gos_v3_layout_templates';
    const DEFAULT_LAYOUT_OPTION = 'gos_v3_default_layout_template';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_migrate']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_notices', [__CLASS__, 'page_sync_admin_notice']);
        add_action('wp_ajax_gos_v3_preview_save', [__CLASS__, 'ajax_preview_save']);
        add_action('wp_ajax_gos_v3_layout_templates_save', [__CLASS__, 'ajax_layout_templates_save']);
        add_action('admin_post_gos_v3_preview_post', [__CLASS__, 'preview_post']);
        add_action('admin_post_gos_v3_mobile_preview', [__CLASS__, 'mobile_preview_shell']);
        add_filter('wp_is_mobile', [__CLASS__, 'force_mobile_preview']);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_filter('the_content', [__CLASS__, 'event_page_preview_content'], 20);
        add_action('wp_footer', [__CLASS__, 'event_page_preview_fallback'], 100);
        add_action('wp_head', [__CLASS__, 'boot_hide'], 0);
        add_action('wp_head', [__CLASS__, 'front_styles'], 99);
        add_action('wp_footer', [__CLASS__, 'front_script'], 99);
        add_shortcode('garden_opening_status', [__CLASS__, 'shortcode_status']);
        add_shortcode('garden_event', [__CLASS__, 'shortcode_event']);
        add_shortcode('garden_event_info', [__CLASS__, 'shortcode_event_info']);
        add_shortcode('garden_event_overview', [__CLASS__, 'shortcode_event_info']);
    }

    private static function state_keys() {
        return ['temporary_closed', 'before_open', 'open', 'after_close', 'event_ended', 'closed', 'next_notice'];
    }

    private static function state_labels() {
        return [
            'temporary_closed' => '臨時閉苑',
            'before_open' => '開苑前',
            'open' => '開苑中',
            'after_close' => '本日終了',
            'event_ended' => '会期終了',
            'closed' => '閉苑中',
            'next_notice' => '次回予告',
        ];
    }

    private static function event_keys() {
        return ['spring', 'autumn', 'winter'];
    }

    private static function default_device_design($device = 'desktop') {
        $mobile = $device === 'mobile';
        return [
            'layout' => 'circle',
            'width' => $mobile ? 340 : 420,
            'height' => $mobile ? 340 : 420,
            'radius' => $mobile ? 170 : 210,
            'offset_x' => 0,
            'offset_y' => 0,
            'padding_x' => $mobile ? 20 : 26,
            'padding_y' => $mobile ? 20 : 26,
            'background_color' => '#ffffff',
            'background_opacity' => 94,
            'shadow_strength' => 24,
            'text_color' => '#303030',
            'muted_color' => '#666666',
            'text_align' => 'center',
            'eyebrow_size' => $mobile ? 13 : 15,
            'title_size' => $mobile ? 22 : 25,
            'event_size' => $mobile ? 30 : 34,
            'detail_size' => $mobile ? 14 : 15,
            'price_size' => $mobile ? 14 : 15,
            'button_size' => 13,
            'title_weight' => 400,
            'event_weight' => 700,
            'eyebrow_line_height' => 130,
            'title_line_height' => 118,
            'event_line_height' => 112,
            'detail_line_height' => 125,
            'price_line_height' => 125,
            'eyebrow_margin' => 6,
            'detail_margin' => 6,
            'price_margin' => 5,
            'actions_margin' => 15,
            'button_min_width' => $mobile ? 112 : 120,
            'button_radius' => 999,
            'button_background' => '#ffffff',
            'button_text_color' => '#303030',
            'button_border_color' => '#555555',
            'eyebrow_x' => 0, 'eyebrow_y' => 0, 'eyebrow_align' => 'center',
            'title_before_x' => 0, 'title_before_y' => 0, 'title_before_align' => 'center',
            'event_x' => 0, 'event_y' => 0, 'event_align' => 'center',
            'title_after_x' => 0, 'title_after_y' => 0, 'title_after_align' => 'center',
            'detail_x' => 0, 'detail_y' => 0, 'detail_align' => 'center',
            'price_x' => 0, 'price_y' => 0, 'price_align' => 'center',
            'actions_x' => 0, 'actions_y' => 0, 'actions_align' => 'center',
        ];
    }

    private static function defaults() {
        $texts = [
            'temporary_closed' => ['eyebrow' => '{event}', 'title' => '本日は臨時閉苑いたしました', 'detail' => ''],
            'before_open' => ['eyebrow' => '{event}', 'title' => '本日は{open_time}から開苑いたします', 'detail' => '{open_time}～{close_time}'],
            'open' => ['eyebrow' => '{event}', 'title' => '本日開苑しています', 'detail' => '{open_time}～{close_time}'],
            'after_close' => ['eyebrow' => '{event}', 'title' => '本日は閉苑いたしました', 'detail' => '{open_time}～{close_time}'],
            'event_ended' => ['eyebrow' => '{event}', 'title' => '会期は終了いたしました', 'detail' => 'ご来苑ありがとうございました'],
            'closed' => ['eyebrow' => '現在は閉苑中です', 'title' => '次回の開催情報は決まり次第お知らせいたします', 'detail' => ''],
            'next_notice' => ['eyebrow' => '現在は閉苑しております', 'title' => "次回は\n{event}\nを予定しております", 'detail' => '{date_range}'],
        ];
        $state_options = [];
        $designs = [];
        foreach (self::state_keys() as $state) {
            $state_options[$state] = [
                'show_price' => in_array($state, ['before_open', 'open', 'after_close'], true) ? 1 : 0,
                'show_detail_button' => $state !== 'closed' ? 1 : 0,
                'show_access_button' => 1,
            ];
        }
        foreach (self::event_keys() as $event_key) {
            $designs[$event_key] = [];
            foreach (self::state_keys() as $state) {
                $designs[$event_key][$state] = [
                    'desktop' => self::default_device_design('desktop'),
                    'mobile' => self::default_device_design('mobile'),
                ];
            }
        }
        return [
            'schema_version' => 3,
            'enabled' => 0,
            'next_mode' => 'auto',
            'state_mode' => 'manual',
            'manual_state' => 'closed',
            'manual_event' => 'spring',
            'temporary_closed_date' => '',
            'access_url' => home_url('/access/'),
            'detail_button' => '会期・料金',
            'access_button' => 'アクセス',
            'aria_label' => '現在の開催状況',
            'events' => [
                'spring' => self::default_event('春の催し'),
                'autumn' => self::default_event('秋の催し'),
                'winter' => self::default_event('冬の催し'),
            ],
            'texts' => $texts,
            'state_options' => $state_options,
            'designs' => $designs,
            'layout_templates' => [],
            'default_layout_template' => '',
        ];
    }

    private static function default_event($label) {
        return [
            'enabled' => 1,
            'label' => $label,
            'usual_period' => '',
            'start' => '',
            'end' => '',
            'open_time' => '09:00',
            'close_time' => '17:00',
            'price' => '',
            'overview_enabled' => 1,
            'overview_heading' => '会期情報',
            'date_label' => '開苑期間',
            'date_display_mode' => 'auto',
            'time_label' => '開苑時間',
            'close_time_label' => '入苑締切',
            'time_note' => '',
            'admission_label' => '入苑料',
            'price_details' => '',
            'price_note' => '',
            'overview_note' => '',
            'detail_url' => '',
            'publish_mode' => 'immediate',
            'publish_at' => '',
            'manual_published' => 0,
            'post_end_days' => 30,
        ];
    }

    private static function maybe_migrate_layout_options() {
        $existing = get_option(self::LAYOUTS_OPTION, null);
        if ($existing === null) {
            $main = get_option(self::OPTION, []);
            $legacy = is_array($main) ? self::sanitize_layout_templates($main['layout_templates'] ?? []) : [];
            add_option(self::LAYOUTS_OPTION, $legacy, '', false);
        }
        $existing_default = get_option(self::DEFAULT_LAYOUT_OPTION, null);
        if ($existing_default === null) {
            $main = get_option(self::OPTION, []);
            $legacy_default = is_array($main) ? sanitize_key((string)($main['default_layout_template'] ?? '')) : '';
            $templates = self::sanitize_layout_templates(get_option(self::LAYOUTS_OPTION, []));
            if ($legacy_default !== '' && !isset($templates[$legacy_default])) $legacy_default = '';
            add_option(self::DEFAULT_LAYOUT_OPTION, $legacy_default, '', false);
        }
    }

    private static function stored_layout_templates() {
        return self::sanitize_layout_templates(get_option(self::LAYOUTS_OPTION, []));
    }

    private static function stored_default_layout_template($templates = null) {
        if (!is_array($templates)) $templates = self::stored_layout_templates();
        $id = sanitize_key((string)get_option(self::DEFAULT_LAYOUT_OPTION, ''));
        return ($id !== '' && isset($templates[$id])) ? $id : '';
    }

    public static function maybe_migrate() {
        self::maybe_migrate_layout_options();
        $saved = get_option(self::OPTION, []);
        if (is_array($saved) && (int)($saved['schema_version'] ?? 0) < 3) {
            $merged = self::merge(self::defaults(), $saved);
            $merged['schema_version'] = 3;
            update_option(self::OPTION, $merged, false);
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
    }

    private static function merge($defaults, $saved) {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $saved)) continue;
            if (is_array($value) && is_array($saved[$key])) $defaults[$key] = self::merge($value, $saved[$key]);
            else $defaults[$key] = $saved[$key];
        }
        return $defaults;
    }

    private static function options() {
        $saved = get_option(self::OPTION, []);
        return self::merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    private static function esc_multiline($value) {
        return esc_html(str_replace(["\r\n", "\r"], "\n", (string)$value));
    }

    private static function now() {
        return current_time('timestamp');
    }

    private static function parse_local_datetime($value) {
        $value = trim((string)$value);
        if ($value === '') return 0;
        $timezone = wp_timezone();
        $dt = date_create_immutable($value, $timezone);
        return $dt ? $dt->getTimestamp() : 0;
    }

    private static function event_released($event, $now = null) {
        if ($now === null) $now = self::now();
        $mode = sanitize_key((string)($event['publish_mode'] ?? 'immediate'));
        if ($mode === 'manual') return !empty($event['manual_published']);
        if ($mode === 'scheduled') {
            $at = self::parse_local_datetime($event['publish_at'] ?? '');
            return $at > 0 && $now >= $at;
        }
        return true;
    }

    private static function event_overview_date_text($event) {
        $mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));

        if ($mode === 'hidden' || $mode === 'none') {
            return '';
        }

        if ($mode === 'confirmed') {
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));

            if ($start !== '' && $end !== '') {
                return self::format_date_range($start, $end);
            }
            if ($start !== '') return self::format_date_range($start, $start);
            if ($end !== '') return self::format_date_range($end, $end);

            return '';
        }

        return trim((string)($event['usual_period'] ?? ''));
    }

    private static function event_date_text($event, $now = null) {
        if ($now === null) $now = self::now();
        if (self::event_released($event, $now) && !empty($event['start']) && !empty($event['end'])) {
            return self::format_date_range($event['start'], $event['end']);
        }
        return trim((string)($event['usual_period'] ?? ''));
    }

    private static function format_date_range($start, $end) {
        $start = trim((string)$start);
        $end = trim((string)$end);
        if ($start === '' && $end === '') return '';
        if ($start !== '' && $end !== '') {
            return self::format_date_ja_with_weekday($start) . '～' . self::format_date_ja_with_weekday($end);
        }
        return self::format_date_ja_with_weekday($start !== '' ? $start : $end);
    }

    private static function format_date_ja_with_weekday($date) {
        $date = trim((string)$date);
        if ($date === '') return '';
        $ts = strtotime($date . ' 00:00:00');
        if (!$ts) return $date;
        $week = ['日', '月', '火', '水', '木', '金', '土'];
        return wp_date('n月j日', $ts) . '（' . $week[(int)wp_date('w', $ts)] . '）';
    }

    private static function format_time($time) {
        $time = trim((string)$time);
        if ($time === '') return '';
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) return ((int)$m[1]) . ':' . $m[2];
        return $time;
    }

    private static function sanitize_multiline($value) {
        return sanitize_textarea_field((string)$value);
    }

    private static function sanitize_event($event, $default) {
        $event = is_array($event) ? $event : [];
        $clean = $default;
        $clean['enabled'] = !empty($event['enabled']) ? 1 : 0;
        foreach (['label','usual_period','start','end','open_time','close_time','price','overview_heading','date_label','time_label','close_time_label','admission_label','detail_url','publish_at'] as $field) {
            if (isset($event[$field])) $clean[$field] = sanitize_text_field($event[$field]);
        }
        foreach (['time_note','price_details','price_note','overview_note'] as $field) {
            if (isset($event[$field])) $clean[$field] = self::sanitize_multiline($event[$field]);
        }
        $clean['date_display_mode'] = in_array(($event['date_display_mode'] ?? 'usual'), ['usual','confirmed','hidden'], true) ? $event['date_display_mode'] : 'usual';
        $publish_mode = sanitize_key((string)($event['publish_mode'] ?? 'immediate'));
        $clean['publish_mode'] = in_array($publish_mode, ['immediate','manual','scheduled'], true) ? $publish_mode : 'immediate';
        $clean['manual_published'] = !empty($event['manual_published']) ? 1 : 0;
        $clean['post_end_days'] = max(0, min(365, (int)($event['post_end_days'] ?? 30)));
        return $clean;
    }

    private static function sanitize_payload($input) {
        $defaults = self::defaults();
        $clean = $defaults;
        $clean['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $clean['next_mode'] = sanitize_key((string)($input['next_mode'] ?? 'auto'));
        $clean['state_mode'] = sanitize_key((string)($input['state_mode'] ?? 'manual'));
        $clean['manual_state'] = sanitize_key((string)($input['manual_state'] ?? 'closed'));
        $clean['manual_event'] = sanitize_key((string)($input['manual_event'] ?? 'spring'));
        $clean['temporary_closed_date'] = sanitize_text_field((string)($input['temporary_closed_date'] ?? ''));
        $clean['access_url'] = esc_url_raw((string)($input['access_url'] ?? ''));
        $clean['detail_button'] = sanitize_text_field((string)($input['detail_button'] ?? ''));
        $clean['access_button'] = sanitize_text_field((string)($input['access_button'] ?? ''));
        $clean['aria_label'] = sanitize_text_field((string)($input['aria_label'] ?? ''));
        foreach (self::event_keys() as $key) {
            $clean['events'][$key] = self::sanitize_event($input['events'][$key] ?? [], $defaults['events'][$key]);
        }
        foreach (self::state_keys() as $state) {
            $text = is_array($input['texts'][$state] ?? null) ? $input['texts'][$state] : [];
            foreach (['eyebrow','title','detail'] as $field) {
                $clean['texts'][$state][$field] = self::sanitize_multiline($text[$field] ?? $defaults['texts'][$state][$field]);
            }
            $state_opt = is_array($input['state_options'][$state] ?? null) ? $input['state_options'][$state] : [];
            foreach (['show_price','show_detail_button','show_access_button'] as $field) {
                $clean['state_options'][$state][$field] = !empty($state_opt[$field]) ? 1 : 0;
            }
        }
        $clean['designs'] = self::sanitize_designs($input['designs'] ?? [], $defaults['designs']);
        return $clean;
    }

    private static function sanitize_designs($input, $defaults) {
        $clean = $defaults;
        foreach (self::event_keys() as $event) {
            foreach (self::state_keys() as $state) {
                foreach (['desktop','mobile'] as $device) {
                    $source = is_array($input[$event][$state][$device] ?? null) ? $input[$event][$state][$device] : [];
                    $clean[$event][$state][$device] = self::sanitize_device_design($source, $defaults[$event][$state][$device]);
                }
            }
        }
        return $clean;
    }

    private static function sanitize_device_design($source, $default) {
        $clean = $default;
        $layout = sanitize_key((string)($source['layout'] ?? $default['layout']));
        $clean['layout'] = in_array($layout, ['circle','rectangle'], true) ? $layout : 'circle';
        $ranges = [
            'width'=>[180,900], 'height'=>[180,900], 'radius'=>[0,450],
            'offset_x'=>[-800,800], 'offset_y'=>[-800,800],
            'padding_x'=>[0,160], 'padding_y'=>[0,160], 'background_opacity'=>[0,100], 'shadow_strength'=>[0,100],
            'eyebrow_size'=>[8,70], 'title_size'=>[8,90], 'event_size'=>[8,100], 'detail_size'=>[8,70], 'price_size'=>[8,70], 'button_size'=>[8,50],
            'title_weight'=>[100,900], 'event_weight'=>[100,900],
            'eyebrow_line_height'=>[80,300], 'title_line_height'=>[80,300], 'event_line_height'=>[80,300], 'detail_line_height'=>[80,300], 'price_line_height'=>[80,300],
            'eyebrow_margin'=>[0,100], 'detail_margin'=>[0,100], 'price_margin'=>[0,100], 'actions_margin'=>[0,100], 'button_min_width'=>[40,400], 'button_radius'=>[0,999],
            'eyebrow_x'=>[-600,600], 'eyebrow_y'=>[-600,600], 'title_before_x'=>[-600,600], 'title_before_y'=>[-600,600],
            'event_x'=>[-600,600], 'event_y'=>[-600,600], 'title_after_x'=>[-600,600], 'title_after_y'=>[-600,600],
            'detail_x'=>[-600,600], 'detail_y'=>[-600,600], 'price_x'=>[-600,600], 'price_y'=>[-600,600],
            'actions_x'=>[-600,600], 'actions_y'=>[-600,600],
        ];
        foreach ($ranges as $field => $range) {
            $value = isset($source[$field]) ? (int)$source[$field] : (int)$default[$field];
            $clean[$field] = max($range[0], min($range[1], $value));
        }
        foreach (['background_color','text_color','muted_color','button_background','button_text_color','button_border_color'] as $field) {
            $color = sanitize_hex_color((string)($source[$field] ?? $default[$field]));
            $clean[$field] = $color ?: $default[$field];
        }
        foreach (['text_align','eyebrow_align','title_before_align','event_align','title_after_align','detail_align','price_align','actions_align'] as $field) {
            $value = sanitize_key((string)($source[$field] ?? $default[$field]));
            $clean[$field] = in_array($value, ['left','center','right'], true) ? $value : 'center';
        }
        return $clean;
    }

    private static function sanitize_layout_templates($input) {
        if (!is_array($input)) return [];
        $defaults = self::defaults();
        $clean = [];
        foreach ($input as $id => $template) {
            if (!is_array($template)) continue;
            $safe_id = sanitize_key((string)$id);
            if ($safe_id === '') continue;
            $name = sanitize_text_field((string)($template['name'] ?? $safe_id));
            $clean[$safe_id] = [
                'name' => $name !== '' ? $name : $safe_id,
                'updated_at' => sanitize_text_field((string)($template['updated_at'] ?? '')),
                'designs' => self::sanitize_designs($template['designs'] ?? [], $defaults['designs']),
            ];
        }
        return $clean;
    }

    public static function admin_menu() {
        add_menu_page('開催情報・開催状況', '開催情報', 'manage_options', 'garden-opening-status', [__CLASS__, 'admin_page'], 'dashicons-calendar-alt', 58);
    }

    public static function admin_assets($hook) {
        if ($hook !== 'toplevel_page_garden-opening-status') return;
        wp_enqueue_style('gos-v3-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
        wp_enqueue_script('gos-v3-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', [], self::VERSION, true);
        wp_localize_script('gos-v3-admin', 'GOS3', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'previewNonce' => wp_create_nonce(self::PREVIEW_NONCE),
            'previewPostUrl' => admin_url('admin-post.php?action=gos_v3_preview_post'),
            'mobilePreviewUrl' => admin_url('admin-post.php?action=gos_v3_mobile_preview'),
            'layoutTemplates' => self::stored_layout_templates(),
            'defaultLayoutTemplate' => self::stored_default_layout_template(),
            'layoutTemplateNonce' => wp_create_nonce('gos_v3_layout_templates'),
            'overviewPreviewUrls' => array_map(function($event) { return (string)($event['detail_url'] ?? ''); }, self::options()['events']),
        ]);
    }

    public static function handle_save() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        if (empty($_POST['gos_v3_action']) || $_POST['gos_v3_action'] !== 'save') return;
        check_admin_referer(self::NONCE);
        $clean = self::sanitize_payload(wp_unslash($_POST));
        update_option(self::OPTION, $clean, false);
        update_option(self::VERSION_OPTION, self::VERSION, false);

        $sync_result = self::sync_existing_event_pages($clean);
        set_transient('gos_v3_page_sync_result_' . get_current_user_id(), $sync_result, 5 * MINUTE_IN_SECONDS);

        self::purge_public_caches($clean);
        wp_safe_redirect(add_query_arg(['page' => 'garden-opening-status', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function resolve_event_page_id($event, $season) {
        // まず管理画面の詳細ページURLを優先する。
        $url = trim((string)($event['detail_url'] ?? ''));
        if ($url !== '') {
            $post_id = url_to_postid($url);
            if ($post_id) return (int)$post_id;

            $path = trim((string)wp_parse_url($url, PHP_URL_PATH), '/');
            if ($path !== '') {
                $page = get_page_by_path($path, OBJECT, 'page');
                if ($page) return (int)$page->ID;
            }
        }

        // 「会期情報」配下の各会期ページをタイトルで特定する。
        $title_candidates = [
            'spring' => ['春のぼたん祭'],
            'autumn' => ['ダリア綾なす秋の園', 'ダリア綾なす秋の庭'],
            'winter' => ['上野・東照宮 冬ぼたん', '上野・東照宮 冬のぼたん'],
        ];

        $parent = get_page_by_title('会期情報', OBJECT, 'page');
        foreach (($title_candidates[$season] ?? []) as $title) {
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => ['publish', 'private', 'draft'],
                'posts_per_page' => -1,
                'title' => $title,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => false,
            ]);
            foreach ($pages as $page) {
                if (!$parent || (int)$page->post_parent === (int)$parent->ID) {
                    return (int)$page->ID;
                }
            }
        }

        // 現在のサイト構成に対する最終フォールバック。
        // タイトルが一致することを確認してから使用する。
        $known_ids = ['spring' => 19, 'autumn' => 47, 'winter' => 49];
        $known_id = (int)($known_ids[$season] ?? 0);
        if ($known_id) {
            $page = get_post($known_id);
            if ($page && $page->post_type === 'page') {
                foreach (($title_candidates[$season] ?? []) as $title) {
                    if (trim(wp_strip_all_tags($page->post_title)) === $title) {
                        return $known_id;
                    }
                }
            }
        }

        return 0;
    }

    private static function event_page_managed_block($event, $season) {
        $date_text = self::event_overview_date_text($event);
        $open_text = self::format_time($event['open_time'] ?? '');
        $close_text = self::format_time($event['close_time'] ?? '');
        $time_text = trim($open_text . (($open_text && $close_text) ? '～' : '') . $close_text);
        $close_label = trim((string)($event['close_time_label'] ?? ''));
        if ($time_text !== '' && $close_label !== '') $time_text .= '（' . $close_label . '）';

        $date_label = trim((string)($event['date_label'] ?? '開苑期間')) ?: '開苑期間';
        $time_label = trim((string)($event['time_label'] ?? '開苑時間')) ?: '開苑時間';
        $price_label = trim((string)($event['admission_label'] ?? '入苑料')) ?: '入苑料';
        $price = trim((string)($event['price_details'] ?? ''));
        if ($price === '') $price = trim((string)($event['price'] ?? ''));

        $parts = [];
        $parts[] = '<!-- gos:event-info:start season=' . esc_attr($season) . ' -->';

        if ($date_text !== '') {
            $parts[] = '<!-- wp:paragraph -->';
            $parts[] = '<p><strong>' . esc_html($date_label) . '：</strong>' . esc_html($date_text) . '</p>';
            $parts[] = '<!-- /wp:paragraph -->';
        }
        if ($time_text !== '') {
            $parts[] = '<!-- wp:paragraph -->';
            $parts[] = '<p><strong>' . esc_html($time_label) . '：</strong>' . esc_html($time_text) . '</p>';
            $parts[] = '<!-- /wp:paragraph -->';
        }
        if ($price !== '') {
            $parts[] = '<!-- wp:paragraph -->';
            $parts[] = '<p><strong>' . esc_html($price_label) . '：</strong>' . nl2br(esc_html($price)) . '</p>';
            $parts[] = '<!-- /wp:paragraph -->';
        }

        $price_note = trim((string)($event['price_note'] ?? ''));
        if ($price_note !== '') {
            $parts[] = '<!-- wp:paragraph -->';
            $parts[] = '<p>' . nl2br(esc_html($price_note)) . '</p>';
            $parts[] = '<!-- /wp:paragraph -->';
        }

        $overview_note = trim((string)($event['overview_note'] ?? ''));
        if ($overview_note !== '') {
            $parts[] = '<!-- wp:paragraph -->';
            $parts[] = '<p>' . nl2br(esc_html($overview_note)) . '</p>';
            $parts[] = '<!-- /wp:paragraph -->';
        }

        $parts[] = '<!-- gos:event-info:end -->';
        return implode("\n", $parts);
    }

    private static function replace_existing_event_section($content, $block, $season) {
        $marker_pattern = '~<!--\s*gos:event-info:start\s+season=' . preg_quote($season, '~') . '\s*-->.*?<!--\s*gos:event-info:end\s*-->~su';
        if (preg_match($marker_pattern, $content)) {
            return preg_replace($marker_pattern, $block, $content, 1);
        }

        // 旧ショートコードが残っている場合は、その位置を直接置換。
        $shortcode_pattern = '~\[garden_event_(?:info|overview)\s+[^\]]*season=["\\\']?' . preg_quote($season, '~') . '["\\\']?[^\]]*\]~iu';
        if (preg_match($shortcode_pattern, $content)) {
            return preg_replace($shortcode_pattern, $block, $content, 1);
        }

        // 現行ページの静的な「会期情報」欄を特定する。
        $labels = ['開苑期間', '開催期間'];
        $label_pos = false;
        foreach ($labels as $label) {
            $pos = mb_strpos($content, $label);
            if ($pos !== false && ($label_pos === false || $pos < $label_pos)) $label_pos = $pos;
        }
        if ($label_pos === false) return new WP_Error('event_section_not_found', '既存ページ内に「開苑期間」または「開催期間」が見つかりません。');

        $byte_label_pos = strlen(mb_substr($content, 0, $label_pos));

        // 対象ラベル直前の「会期情報」見出しブロックから開始。
        $prefix = substr($content, 0, $byte_label_pos);
        $start = false;
        if (preg_match_all('~<!--\s*wp:heading\b.*?-->.*?<h[1-6][^>]*>\s*会期情報\s*</h[1-6]>.*?<!--\s*/wp:heading\s*-->~su', $prefix, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            $start = (int)$last[1];
        }
        if ($start === false && preg_match_all('~<h[1-6][^>]*>\s*会期情報\s*</h[1-6]>~su', $prefix, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            $start = (int)$last[1];
        }
        if ($start === false) {
            // 見出しが取れない場合は、該当段落ブロックから開始。
            $candidate = strrpos($prefix, '<!-- wp:paragraph');
            if ($candidate !== false) $start = $candidate;
        }
        if ($start === false) return new WP_Error('event_section_start_not_found', '会期情報欄の開始位置を特定できません。');

        // 次の画像・見出し・ギャラリー等の主要ブロック直前までを既存会期情報欄とする。
        $tail = substr($content, $byte_label_pos);
        $end_rel = false;
        $boundaries = [
            '~<!--\s*wp:(?:image|gallery|heading|columns|media-text|cover)\b~u',
            '~<h[1-6][^>]*>\s*見どころ\s*</h[1-6]>~u',
        ];
        foreach ($boundaries as $pattern) {
            if (preg_match($pattern, $tail, $m, PREG_OFFSET_CAPTURE)) {
                $candidate = (int)$m[0][1];
                if ($candidate > 0 && ($end_rel === false || $candidate < $end_rel)) $end_rel = $candidate;
            }
        }
        if ($end_rel === false) {
            // 最低限、料金段落の終了までは置換する。
            if (preg_match('~入苑料.*?</p>(?:\s*<!--\s*/wp:paragraph\s*-->)?~su', $tail, $m, PREG_OFFSET_CAPTURE)) {
                $end_rel = (int)$m[0][1] + strlen($m[0][0]);
            }
        }
        if ($end_rel === false) return new WP_Error('event_section_end_not_found', '会期情報欄の終了位置を特定できません。');

        $end = $byte_label_pos + $end_rel;
        return substr($content, 0, $start) . $block . "\n\n" . substr($content, $end);
    }

    private static function sync_existing_event_pages($options) {
        $result = ['updated' => [], 'errors' => []];
        foreach (self::event_keys() as $season) {
            $event = is_array($options['events'][$season] ?? null) ? $options['events'][$season] : [];
            $post_id = self::resolve_event_page_id($event, $season);
            if (!$post_id) {
                $result['errors'][$season] = '詳細ページURLから固定ページを特定できません。';
                continue;
            }

            $post = get_post($post_id);
            if (!$post || !in_array($post->post_type, ['page', 'post'], true)) {
                $result['errors'][$season] = '更新対象が固定ページまたは投稿ではありません。';
                continue;
            }

            // 各会期ページは専用ページなので、本文全体を管理画面の内容で上書きする。
            $new_content = self::event_page_managed_block($event, $season);
            $updated = wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_slash($new_content),
            ], true);
            if (is_wp_error($updated)) {
                $result['errors'][$season] = $updated->get_error_message();
                continue;
            }

            clean_post_cache($post_id);
            $result['updated'][$season] = [
                'post_id' => $post_id,
                'title' => get_the_title($post_id),
            ];
        }
        return $result;
    }

    public static function page_sync_admin_notice() {
        if (!current_user_can('manage_options')) return;
        $key = 'gos_v3_page_sync_result_' . get_current_user_id();
        $result = get_transient($key);
        if (!is_array($result)) return;
        delete_transient($key);

        $labels = ['spring' => '春', 'autumn' => '秋', 'winter' => '冬'];
        foreach (($result['updated'] ?? []) as $season => $item) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html(($labels[$season] ?? $season) . 'の既存ページ「' . ($item['title'] ?? '') . '」を更新しました。')
                . '</p></div>';
        }
        foreach (($result['errors'] ?? []) as $season => $message) {
            echo '<div class="notice notice-error"><p>'
                . esc_html(($labels[$season] ?? $season) . 'の既存ページを更新できませんでした：' . $message)
                . '</p></div>';
        }

    }

    private static function purge_public_caches($options) {
        $urls = [home_url('/')];
        foreach (self::event_keys() as $key) {
            $url = trim((string)($options['events'][$key]['detail_url'] ?? ''));
            if ($url !== '') $urls[] = $url;
        }
        foreach (array_unique($urls) as $url) {
            $post_id = url_to_postid($url);
            if ($post_id) clean_post_cache($post_id);
            if (has_action('litespeed_purge_url')) do_action('litespeed_purge_url', $url);
        }

        // URL設定に依存せず、ショートコードを含む固定ページを必ず対象にする。
        $shortcode_pages = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            's' => '[garden_event_info',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);
        foreach ($shortcode_pages as $page_id) {
            $content = (string)get_post_field('post_content', $page_id);
            if (strpos($content, '[garden_event_info') === false) continue;
            clean_post_cache($page_id);
            $page_url = get_permalink($page_id);
            if ($page_url && has_action('litespeed_purge_url')) {
                do_action('litespeed_purge_url', $page_url);
            }
            if ($page_url && function_exists('rocket_clean_files')) {
                rocket_clean_files([$page_url]);
            }
        }

        wp_cache_delete(self::OPTION, 'options');
        wp_cache_delete('alloptions', 'options');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
        if (function_exists('w3tc_flush_all')) w3tc_flush_all();
        if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        if (function_exists('opcache_reset')) @opcache_reset();

        do_action('garden_opening_status_saved', $options);
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options();
        $labels = self::state_labels();
        ?>
        <div class="wrap gos3-wrap">
            <h1>開催情報・開催状況管理</h1>
            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>保存しました。</p></div><?php endif; ?>
            <p class="gos-admin-intro">開催概要、トップ画面の開催状況、会期ページ表示をこの画面でまとめて管理します。</p>
            <form method="post" id="gos3-form">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="gos_v3_action" value="save">

                <section class="gos3-card">
                    <h2>開催概要</h2>
                    <p>春・秋・冬それぞれの会期、時間、料金、会期ページ表示を設定します。</p>
                    <div class="gos3-event-tabs">
                        <?php foreach (self::event_keys() as $i => $key): $e = $o['events'][$key]; ?>
                        <button type="button" class="button gos3-event-tab<?php echo $i===0?' active':''; ?>" data-event-tab="<?php echo esc_attr($key); ?>"><?php echo esc_html($e['label']); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach (self::event_keys() as $i => $key): $e = $o['events'][$key]; ?>
                    <div class="gos3-event-panel<?php echo $i===0?' active':''; ?>" data-event-panel="<?php echo esc_attr($key); ?>">
                        <div class="gos3-fields">
                            <label class="gos3-check"><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($e['enabled']); ?>> トップ画面の開催判定に使う</label>
                            <label>表示名<input type="text" name="events[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($e['label']); ?>"></label>
                            <label>例年の開催時期<input type="text" name="events[<?php echo esc_attr($key); ?>][usual_period]" value="<?php echo esc_attr($e['usual_period']); ?>" placeholder="4月上旬～5月上旬"></label>
                            
                                <label>期間の表示
                                    <select name="events[<?php echo esc_attr($key); ?>][date_display_mode]">
                                        <option value="usual" <?php selected(($e['date_display_mode'] ?? 'usual'), 'usual'); ?>>例年時期を表示</option>
                                        <option value="confirmed" <?php selected(($e['date_display_mode'] ?? 'usual'), 'confirmed'); ?>>確定日を表示</option>
                                        <option value="hidden" <?php selected(($e['date_display_mode'] ?? 'usual'), 'hidden'); ?>>表示しない</option>
                                    </select>
                                </label>

                                <label>確定開始日<input type="date" name="events[<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($e['start']); ?>"></label>
                            <label>確定終了日<input type="date" name="events[<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($e['end']); ?>"></label>
                            <label>開苑時間<input type="time" name="events[<?php echo esc_attr($key); ?>][open_time]" value="<?php echo esc_attr($e['open_time']); ?>"></label>
                            <label>閉苑時間<input type="time" name="events[<?php echo esc_attr($key); ?>][close_time]" value="<?php echo esc_attr($e['close_time']); ?>"></label>
                            <label>トップ表示用料金<input type="text" name="events[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($e['price']); ?>"></label>
                            <label>詳細ページURL<input type="url" name="events[<?php echo esc_attr($key); ?>][detail_url]" value="<?php echo esc_attr($e['detail_url']); ?>"></label>
                            <label>情報公開
                                <select name="events[<?php echo esc_attr($key); ?>][publish_mode]">
                                    <option value="immediate" <?php selected($e['publish_mode'], 'immediate'); ?>>保存後すぐ公開</option>
                                    <option value="manual" <?php selected($e['publish_mode'], 'manual'); ?>>手動公開</option>
                                    <option value="scheduled" <?php selected($e['publish_mode'], 'scheduled'); ?>>日時指定</option>
                                </select>
                            </label>
                            <label>公開日時<input type="datetime-local" name="events[<?php echo esc_attr($key); ?>][publish_at]" value="<?php echo esc_attr($e['publish_at']); ?>"></label>
                            <label class="gos3-check"><input type="hidden" name="events[<?php echo esc_attr($key); ?>][manual_published]" value="0"><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][manual_published]" value="1" <?php checked($e['manual_published']); ?>> 手動公開をON</label>
                            <label>終了後も開催情報を表示する日数<input type="number" min="0" max="365" name="events[<?php echo esc_attr($key); ?>][post_end_days]" value="<?php echo esc_attr($e['post_end_days']); ?>"></label>
                        </div>

                        <div class="gos3-event-overview-fields">
                            <h3>会期ページに表示する内容</h3>
                            <p>保存すると、「会期情報」配下の春・秋・冬の各固定ページ本文を直接上書きします。</p>
                            <div class="gos3-fields">
                                <label>見出し<input type="text" name="events[<?php echo esc_attr($key); ?>][overview_heading]" value="<?php echo esc_attr($e['overview_heading']); ?>"></label>
                                <label>期間ラベル<input type="text" name="events[<?php echo esc_attr($key); ?>][date_label]" value="<?php echo esc_attr($e['date_label']); ?>"></label>
                                <label>時間ラベル<input type="text" name="events[<?php echo esc_attr($key); ?>][time_label]" value="<?php echo esc_attr($e['time_label']); ?>"></label>
                                <label>閉苑時間の補足<input type="text" name="events[<?php echo esc_attr($key); ?>][close_time_label]" value="<?php echo esc_attr($e['close_time_label']); ?>"></label>
                                <label>料金ラベル<input type="text" name="events[<?php echo esc_attr($key); ?>][admission_label]" value="<?php echo esc_attr($e['admission_label']); ?>"></label>
                                <label class="gos3-wide">時間補足<textarea name="events[<?php echo esc_attr($key); ?>][time_note]" rows="2"><?php echo self::esc_multiline($e['time_note']); ?></textarea></label>
                                <label class="gos3-wide">料金詳細<textarea name="events[<?php echo esc_attr($key); ?>][price_details]" rows="4"><?php echo self::esc_multiline($e['price_details']); ?></textarea></label>
                                <label class="gos3-wide">料金補足<textarea name="events[<?php echo esc_attr($key); ?>][price_note]" rows="3"><?php echo self::esc_multiline($e['price_note']); ?></textarea></label>
                                <label class="gos3-wide">開催概要の補足<textarea name="events[<?php echo esc_attr($key); ?>][overview_note]" rows="3"><?php echo self::esc_multiline($e['overview_note']); ?></textarea></label>
                            </div>
                            <div class="gos3-event-info-preview" data-overview-preview="<?php echo esc_attr($key); ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </section>

                <section class="gos3-card">
                    <h2>トップ画面編集エリア</h2>
                    <p>トップページに表示する開催状況の状態、文言、レイアウトを設定します。</p>
                    <div class="gos3-fields">
                        <label class="gos3-check"><input type="checkbox" name="enabled" value="1" <?php checked($o['enabled']); ?>> トップページへ開催状況を表示</label>
                        <label>状態の決め方
                            <select name="state_mode" id="gos3-state-mode">
                                <option value="manual" <?php selected($o['state_mode'], 'manual'); ?>>手動</option>
                                <option value="auto" <?php selected($o['state_mode'], 'auto'); ?>>日時から自動</option>
                            </select>
                        </label>
                        <label>手動状態
                            <select name="manual_state" id="gos3-manual-state">
                                <?php foreach ($labels as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['manual_state'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>手動イベント
                            <select name="manual_event" id="gos3-manual-event">
                                <?php foreach (self::event_keys() as $key): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['manual_event'], $key); ?>><?php echo esc_html($o['events'][$key]['label']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>次回予告
                            <select name="next_mode">
                                <option value="auto" <?php selected($o['next_mode'], 'auto'); ?>>自動</option>
                                <option value="off" <?php selected($o['next_mode'], 'off'); ?>>表示しない</option>
                            </select>
                        </label>
                        <label>臨時閉苑日<input type="date" name="temporary_closed_date" value="<?php echo esc_attr($o['temporary_closed_date']); ?>"></label>
                        <label>アクセスURL<input type="url" name="access_url" value="<?php echo esc_attr($o['access_url']); ?>"></label>
                        <label>詳細ボタン文言<input type="text" name="detail_button" value="<?php echo esc_attr($o['detail_button']); ?>"></label>
                        <label>アクセスボタン文言<input type="text" name="access_button" value="<?php echo esc_attr($o['access_button']); ?>"></label>
                        <label>読み上げラベル<input type="text" name="aria_label" value="<?php echo esc_attr($o['aria_label']); ?>"></label>
                    </div>
                </section>

                <section class="gos3-card">
                    <h2>状態ごとの文言と表示項目</h2>
                    <div class="gos3-text-tabs">
                        <?php foreach ($labels as $key => $label): ?><button type="button" class="button gos3-text-tab<?php echo $key==='closed'?' active':''; ?>" data-text-tab="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button><?php endforeach; ?>
                    </div>
                    <?php foreach ($labels as $key => $label): $t=$o['texts'][$key]; $so=$o['state_options'][$key]; ?>
                    <div class="gos3-text-panel<?php echo $key==='closed'?' active':''; ?>" data-text-panel="<?php echo esc_attr($key); ?>">
                        <div class="gos3-fields">
                            <label class="gos3-wide">上段<textarea rows="2" name="texts[<?php echo esc_attr($key); ?>][eyebrow]"><?php echo self::esc_multiline($t['eyebrow']); ?></textarea></label>
                            <label class="gos3-wide">主文<textarea rows="3" name="texts[<?php echo esc_attr($key); ?>][title]"><?php echo self::esc_multiline($t['title']); ?></textarea></label>
                            <label class="gos3-wide">詳細<textarea rows="2" name="texts[<?php echo esc_attr($key); ?>][detail]"><?php echo self::esc_multiline($t['detail']); ?></textarea></label>
                            <label class="gos3-check"><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_price]" value="1" <?php checked($so['show_price']); ?>> 料金を表示</label>
                            <label class="gos3-check"><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_detail_button]" value="1" <?php checked($so['show_detail_button']); ?>> 詳細ボタンを表示</label>
                            <label class="gos3-check"><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_access_button]" value="1" <?php checked($so['show_access_button']); ?>> アクセスボタンを表示</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <p><small>利用できる置換：<code>{event}</code> <code>{open_time}</code> <code>{close_time}</code> <code>{date_range}</code></small></p>
                </section>

                <div class="gos3-grid">
                    <section class="gos3-card">
                        <h2>トップ画面レイアウト設定</h2>
                        <div class="gos3-preview-mode-row">
                            <div class="gos3-segment"><button type="button" class="button active" data-device="desktop">PC</button><button type="button" class="button" data-device="mobile">スマホ</button></div>
                            <div class="gos3-presets"><span>プリセット</span><button type="button" class="button" data-preset="circle">円形</button><button type="button" class="button" data-preset="soft">やわらかい円</button><button type="button" class="button" data-preset="rect">長方形</button><button type="button" class="button" data-preset="minimal">シンプル</button><small>選択中の会期・状態・端末だけに適用</small></div>
                        </div>
                        <p class="gos3-design-scope">編集対象：<strong id="gos3-design-scope">春の催し / 閉苑中 / PC</strong></p>
                        <div class="gos3-event-tabs gos3-design-event-tabs">
                            <?php foreach (self::event_keys() as $i => $key): ?><button type="button" class="button gos3-design-event-tab<?php echo $i===0?' active':''; ?>" data-design-event="<?php echo esc_attr($key); ?>"><?php echo esc_html($o['events'][$key]['label']); ?></button><?php endforeach; ?>
                        </div>
                        <div class="gos3-text-tabs gos3-design-state-tabs">
                            <?php foreach ($labels as $key => $label): ?><button type="button" class="button gos3-design-state-tab<?php echo $key==='closed'?' active':''; ?>" data-design-state="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button><?php endforeach; ?>
                        </div>
                        <details class="gos3-layout-tools">
                            <summary>レイアウト保存・呼び出し</summary>
                            <p>現在の全会期・全状態・PC/スマホのレイアウトを名前付きで保存します。</p>
                            <div class="gos3-layout-save-row">
                                <label>保存名<input type="text" id="gos3-layout-name" placeholder="例：トップ標準レイアウト"></label>
                                <button type="button" class="button button-primary" id="gos3-layout-save">現在のレイアウトを保存</button>
                            </div>
                            <div class="gos3-layout-manage-row">
                                <label>保存済み<select id="gos3-layout-select"></select></label>
                                <button type="button" class="button" id="gos3-layout-apply">呼び出す</button>
                                <button type="button" class="button" id="gos3-layout-overwrite">上書き更新</button>
                                <button type="button" class="button" id="gos3-layout-rename">名前変更</button>
                                <button type="button" class="button button-link-delete" id="gos3-layout-delete">削除</button>
                                <label class="gos3-inline-check"><input type="checkbox" id="gos3-layout-default"> 標準として使用</label>
                            </div>
                            <p class="gos3-layout-status" id="gos3-layout-status" aria-live="polite"></p>
                            <details class="gos3-layout-copy-tools">
                                <summary>会期・状態・端末を選んで一部コピー</summary>
                                <div class="gos3-copy-grid">
                                    <fieldset>
                                        <legend>コピー元</legend>
                                        <label>会期<select id="gos3-copy-source-event"><?php foreach (self::event_keys() as $key): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($o['events'][$key]['label']); ?></option><?php endforeach; ?></select></label>
                                        <label>状態<select id="gos3-copy-source-state"><?php foreach ($labels as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                                        <label>端末<select id="gos3-copy-source-device"><option value="desktop">PC</option><option value="mobile">スマホ</option></select></label>
                                    </fieldset>
                                    <fieldset>
                                        <legend>コピー先</legend>
                                        <div><?php foreach (self::event_keys() as $key): ?><label><input type="checkbox" name="gos3-copy-target-event" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($o['events'][$key]['label']); ?></label><?php endforeach; ?></div>
                                        <div><?php foreach ($labels as $key => $label): ?><label><input type="checkbox" name="gos3-copy-target-state" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label><?php endforeach; ?></div>
                                        <div><label><input type="checkbox" name="gos3-copy-target-device" value="desktop"> PC</label><label><input type="checkbox" name="gos3-copy-target-device" value="mobile"> スマホ</label></div>
                                        <button type="button" class="button" id="gos3-layout-copy">選択先へコピー</button>
                                    </fieldset>
                                    <fieldset>
                                        <legend>初期値</legend>
                                        <button type="button" class="button" id="gos3-layout-reset-current">選択中を初期値へ戻す</button>
                                    </fieldset>
                                </div>
                            </details>
                        </details>
                        <div id="gos3-design-fields">
                            <?php foreach (self::event_keys() as $event_key): foreach ($labels as $state => $state_label): foreach (['desktop','mobile'] as $device): $d=$o['designs'][$event_key][$state][$device]; ?>
                            <div class="gos3-design-set" data-design-set="<?php echo esc_attr("$event_key:$state:$device"); ?>">
                                <div class="gos3-fields">
                                    <label>形状<select name="designs[<?php echo "$event_key][$state][$device"; ?>][layout]"><option value="circle" <?php selected($d['layout'],'circle'); ?>>円形</option><option value="rectangle" <?php selected($d['layout'],'rectangle'); ?>>長方形</option></select></label>
                                    <?php self::number_input('幅','width',$d,$event_key,$state,$device,180,900); ?>
                                    <?php self::number_input('高さ','height',$d,$event_key,$state,$device,180,900); ?>
                                    <?php self::number_input('角丸','radius',$d,$event_key,$state,$device,0,450); ?>
                                    <?php self::number_input('横位置','offset_x',$d,$event_key,$state,$device,-800,800); ?>
                                    <?php self::number_input('縦位置','offset_y',$d,$event_key,$state,$device,-800,800); ?>
                                    <?php self::number_input('左右余白','padding_x',$d,$event_key,$state,$device,0,160); ?>
                                    <?php self::number_input('上下余白','padding_y',$d,$event_key,$state,$device,0,160); ?>
                                    <?php self::color_input('背景色','background_color',$d,$event_key,$state,$device); ?>
                                    <?php self::number_input('背景透明度','background_opacity',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::number_input('影の強さ','shadow_strength',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::color_input('文字色','text_color',$d,$event_key,$state,$device); ?>
                                    <?php self::color_input('補助文字色','muted_color',$d,$event_key,$state,$device); ?>
                                    <label>基準揃え<select name="designs[<?php echo "$event_key][$state][$device"; ?>][text_align]"><?php foreach(['left'=>'左','center'=>'中央','right'=>'右'] as $ak=>$al): ?><option value="<?php echo esc_attr($ak); ?>" <?php selected($d['text_align'],$ak); ?>><?php echo esc_html($al); ?></option><?php endforeach; ?></select></label>
                                    <?php self::number_input('上段サイズ','eyebrow_size',$d,$event_key,$state,$device,8,70); ?>
                                    <?php self::number_input('主文サイズ','title_size',$d,$event_key,$state,$device,8,90); ?>
                                    <?php self::number_input('イベント名サイズ','event_size',$d,$event_key,$state,$device,8,100); ?>
                                    <?php self::number_input('詳細サイズ','detail_size',$d,$event_key,$state,$device,8,70); ?>
                                    <?php self::number_input('料金サイズ','price_size',$d,$event_key,$state,$device,8,70); ?>
                                    <?php self::number_input('ボタンサイズ','button_size',$d,$event_key,$state,$device,8,50); ?>
                                    <?php self::number_input('主文の太さ','title_weight',$d,$event_key,$state,$device,100,900,100); ?>
                                    <?php self::number_input('イベント名の太さ','event_weight',$d,$event_key,$state,$device,100,900,100); ?>
                                    <?php self::number_input('上段行間','eyebrow_line_height',$d,$event_key,$state,$device,80,300); ?>
                                    <?php self::number_input('主文行間','title_line_height',$d,$event_key,$state,$device,80,300); ?>
                                    <?php self::number_input('イベント名行間','event_line_height',$d,$event_key,$state,$device,80,300); ?>
                                    <?php self::number_input('詳細行間','detail_line_height',$d,$event_key,$state,$device,80,300); ?>
                                    <?php self::number_input('料金行間','price_line_height',$d,$event_key,$state,$device,80,300); ?>
                                    <?php self::number_input('上段の下余白','eyebrow_margin',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::number_input('詳細の下余白','detail_margin',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::number_input('料金の下余白','price_margin',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::number_input('ボタン上余白','actions_margin',$d,$event_key,$state,$device,0,100); ?>
                                    <?php self::number_input('ボタン最小幅','button_min_width',$d,$event_key,$state,$device,40,400); ?>
                                    <?php self::number_input('ボタン角丸','button_radius',$d,$event_key,$state,$device,0,999); ?>
                                    <?php self::color_input('ボタン背景','button_background',$d,$event_key,$state,$device); ?>
                                    <?php self::color_input('ボタン文字','button_text_color',$d,$event_key,$state,$device); ?>
                                    <?php self::color_input('ボタン枠','button_border_color',$d,$event_key,$state,$device); ?>
                                </div>
                                <div class="gos3-element-table">
                                    <h3>要素ごとの位置・揃え</h3>
                                    <?php foreach ([
                                        'eyebrow'=>'上段','title_before'=>'主文（前）','event'=>'イベント名','title_after'=>'主文（後）','detail'=>'詳細','price'=>'料金','actions'=>'ボタン'
                                    ] as $element=>$element_label): ?>
                                    <div class="gos3-element-row">
                                        <strong><?php echo esc_html($element_label); ?></strong>
                                        <?php self::number_input('横',$element.'_x',$d,$event_key,$state,$device,-600,600); ?>
                                        <?php self::number_input('縦',$element.'_y',$d,$event_key,$state,$device,-600,600); ?>
                                        <label>揃え<select name="designs[<?php echo "$event_key][$state][$device"; ?>][<?php echo esc_attr($element.'_align'); ?>]"><?php foreach(['left'=>'左','center'=>'中央','right'=>'右'] as $ak=>$al): ?><option value="<?php echo esc_attr($ak); ?>" <?php selected($d[$element.'_align'],$ak); ?>><?php echo esc_html($al); ?></option><?php endforeach; ?></select></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; endforeach; endforeach; ?>
                        </div>
                    </section>

                    <section class="gos3-card gos3-preview-card">
                        <div class="gos3-preview-head"><h2>プレビュー</h2><small id="gos3-preview-label"></small></div>
                        <div class="gos3-preview-targets">
                            <span class="gos3-preview-targets-label">対象</span>
                            <div class="gos3-segment">
                                <button type="button" class="button active" data-preview-target="status">開催状況</button>
                                <button type="button" class="button" data-preview-target="overview">会期ページ</button>
                            </div>
                        </div>
                        <div class="gos3-preview-contexts">
                            <div class="gos3-preview-context" data-preview-context="status"><small>トップ画面に表示する開催状況パネルです。</small></div>
                            <div class="gos3-preview-context" data-preview-context="overview" hidden><small>選択中の春・秋・冬の会期ページです。</small></div>
                        </div>
                        <div class="gos3-preview-actions">
                            <button type="button" class="button" id="gos3-preview-pc">開催状況をPC実画面で開く</button>
                            <button type="button" class="button" id="gos3-preview-mobile">開催状況をスマホ実画面で開く</button>
                            <button type="button" class="button" id="gos3-preview-reload">実画面プレビューを再読込</button>
                        </div>
                        <div class="gos3-preview-actions" data-overview-preview-actions hidden>
                            <button type="button" class="button" id="gos3-overview-preview-pc">会期ページをPC実画面で開く</button>
                            <button type="button" class="button" id="gos3-overview-preview-mobile">会期ページをスマホ実画面で開く</button>
                        </div>
                        <details class="gos3-direct-editor">
                            <summary><strong>プレビュー内で位置を調整</strong><small>ドラッグ・方向キー・中央吸着</small></summary>
                            <div class="gos3-direct-editor-body">
                            <div class="gos3-direct-elements">
                                <button type="button" class="button active" data-direct-element="panel">パネル</button>
                                <button type="button" class="button" data-direct-element="eyebrow">上段</button>
                                <button type="button" class="button" data-direct-element="title_before">主文（前）</button>
                                <button type="button" class="button" data-direct-element="event">イベント名</button>
                                <button type="button" class="button" data-direct-element="title_after">主文（後）</button>
                                <button type="button" class="button" data-direct-element="detail">詳細</button>
                                <button type="button" class="button" data-direct-element="price">料金</button>
                                <button type="button" class="button" data-direct-element="actions">ボタン</button>
                            </div>
                            <div class="gos3-direct-actions">
                                <button type="button" class="button" id="gos3-direct-center-x">横中央</button>
                                <button type="button" class="button" id="gos3-direct-center-y">縦中央</button>
                                <button type="button" class="button" id="gos3-direct-center-both">中央へ</button>
                                <label class="gos3-snap-toggle"><input type="checkbox" id="gos3-snap-enabled" checked> 中央吸着</label>
                                <span class="dashicons dashicons-editor-help gos3-snap-help" title="要素やパネルを中央線の近くへ動かすと、自動で中央に吸着します。"></span>
                            </div>
                            </div>
                        </details>
                        <iframe id="gos3-preview-frame" class="gos3-preview-frame desktop" title="開催状況プレビュー"></iframe>
                        <div id="gos3-overview-preview-frame" class="gos3-overview-preview desktop" title="会期ページプレビュー" hidden>
                            <div class="gos3-overview-page"><h3 class="gos3-overview-page-title" id="gos3-overview-page-title"></h3><div id="gos3-overview-preview-content"></div></div>
                        </div>
                    </section>
                </div>

                <p class="submit"><button type="submit" class="button button-primary button-large">保存</button></p>
            </form>
        </div>
        <?php
    }

    private static function number_input($label,$field,$d,$event,$state,$device,$min,$max,$step=1) {
        printf('<label>%s<input type="number" min="%d" max="%d" step="%d" name="designs[%s][%s][%s][%s]" value="%d"></label>', esc_html($label),$min,$max,$step,esc_attr($event),esc_attr($state),esc_attr($device),esc_attr($field),(int)$d[$field]);
    }
    private static function color_input($label,$field,$d,$event,$state,$device) {
        printf('<label>%s<input type="color" name="designs[%s][%s][%s][%s]" value="%s"></label>',esc_html($label),esc_attr($event),esc_attr($state),esc_attr($device),esc_attr($field),esc_attr($d[$field]));
    }

    public static function ajax_preview_save() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'権限がありません。'],403);
        check_ajax_referer(self::PREVIEW_NONCE,'nonce');
        $input=[];
        parse_str((string)($_POST['form'] ?? ''),$input);
        $token=wp_generate_password(24,false,false);
        set_transient('gos_v3_preview_'.$token,self::sanitize_payload($input),10*MINUTE_IN_SECONDS);
        wp_send_json_success(['token'=>$token]);
    }

    public static function ajax_layout_templates_save() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'権限がありません。'],403);
        check_ajax_referer('gos_v3_layout_templates','nonce');
        $templates_raw = json_decode(wp_unslash((string)($_POST['templates'] ?? '[]')), true);
        $templates = self::sanitize_layout_templates($templates_raw);
        $default_id = sanitize_key((string)($_POST['default_id'] ?? ''));
        if ($default_id !== '' && !isset($templates[$default_id])) $default_id = '';
        update_option(self::LAYOUTS_OPTION, $templates, false);
        update_option(self::DEFAULT_LAYOUT_OPTION, $default_id, false);
        wp_send_json_success(['templates'=>$templates, 'default_id'=>$default_id]);
    }

    private static function preview_token_options() {
        $token=sanitize_text_field((string)($_GET['gos_preview'] ?? ''));
        if ($token==='') return null;
        $data=get_transient('gos_v3_preview_'.$token);
        return is_array($data)?$data:null;
    }

    private static function event_page_preview_context() {
        $options = self::preview_token_options();
        if (!is_array($options)) return null;
        $event_key = sanitize_key((string)($_GET['gos_event_info_preview'] ?? ''));
        if (!in_array($event_key, self::event_keys(), true)) return null;
        $event = $options['events'][$event_key] ?? null;
        if (!is_array($event)) return null;
        return ['options' => $options, 'event_key' => $event_key, 'event' => $event];
    }

    public static function event_page_preview_content($content) {
        $context = self::event_page_preview_context();
        if (!$context || !is_singular()) return $content;
        $event = $context['event'];
        $replacement = self::render_event_overview($event, false);
        if ($replacement === '') return $content;

        $patterns = [
            '~(<p[^>]*>\s*<(?:strong|b)[^>]*>\s*(?:開苑期間|開催期間)\s*[：:]?\s*</(?:strong|b)>.*?</p>)~isu',
            '~(<p[^>]*>.*?(?:開苑期間|開催期間)\s*[：:].*?</p>)~isu',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) continue;
            $offset = $match[0][1];
            $prefix = substr($content, 0, $offset);
            $headPos = strripos($prefix, '<h2');
            $head3Pos = strripos($prefix, '<h3');
            $headingOffset = max($headPos === false ? -1 : $headPos, $head3Pos === false ? -1 : $head3Pos);
            if ($headingOffset >= 0) {
                $headingEnd = strpos($content, '>', $headingOffset);
                $headingClose = $headingEnd === false ? false : preg_match('~</h[23]>~i', $content, $hm, PREG_OFFSET_CAPTURE, $headingEnd);
                if ($headingClose) {
                    $closeOffset = $hm[0][1] + strlen($hm[0][0]);
                    $before = substr($content, 0, $closeOffset);
                    $afterStart = $offset;
                    $tail = substr($content, $afterStart);
                    $nextHeading = null;
                    if (preg_match('~<h[1-6][^>]*>~i', $tail, $nh, PREG_OFFSET_CAPTURE)) $nextHeading = $nh[0][1];
                    if ($nextHeading !== null && $nextHeading > 0) return $before . $replacement . substr($tail, $nextHeading);
                    return $before . $replacement;
                }
            }
            return substr($content, 0, $offset) . $replacement . substr($content, $offset + strlen($match[0][0]));
        }
        return $replacement . $content;
    }

    public static function event_page_preview_fallback() {
        $context = self::event_page_preview_context();
        if (!$context) return;
        echo '<div id="gos-event-page-preview-fallback" style="display:none">' . self::render_event_overview($context['event'], false) . '</div>';
    }

    public static function boot_hide() {
        if (!self::preview_token_options()) return;
        echo '<style id="gos-v3-boot-hide">body{visibility:hidden!important}</style>';
    }

    public static function front_script() {
        $context = self::event_page_preview_context();
        if (!$context) return;
        $html = self::render_event_overview($context['event'], false);
        ?>
        <script>
        (function(){
          var html=<?php echo wp_json_encode($html); ?>;
          function norm(s){return String(s||'').replace(/\s+/g,'').replace(/:/g,'：');}
          function isPeriodText(text){var n=norm(text);return n.indexOf('開苑期間')>=0||n.indexOf('開催期間')>=0;}
          function isFooterish(el){return !!(el&&el.closest&&el.closest('footer,#footer,.site-footer,.footer,.widget-area,.footer-widget-area'))}
          function hideOld(root){
            var all=(root||document).querySelectorAll('p,div,li,dt,dd,tr');
            for(var i=0;i<all.length;i++){
              var el=all[i]; if(isFooterish(el))continue;
              if(isPeriodText(el.textContent)){
                var parent=el.parentElement;
                el.style.display='none';
                for(var j=0;j<5;j++){
                  if(!parent||isFooterish(parent))break;
                  var t=norm(parent.textContent);
                  if((t.indexOf('開苑時間')>=0||t.indexOf('開催時間')>=0||t.indexOf('入苑料')>=0)&&parent.children.length<=10){parent.style.display='none';break;}
                  parent=parent.parentElement;
                }
                return el;
              }
            }
            return null;
          }
          function ensure(){
            var existing=document.getElementById('gos-event-page-preview-injected');
            if(existing){hideOld(document);return existing}
            var old=hideOld(document);
            var wrap=document.createElement('div');wrap.id='gos-event-page-preview-injected';wrap.innerHTML=html;
            if(old&&old.parentNode){
              var anchor=old;
              var p=old.parentElement;
              for(var i=0;i<4&&p;i++){
                if(isFooterish(p))break;
                var t=norm(p.textContent);
                if((t.indexOf('開苑時間')>=0||t.indexOf('開催時間')>=0||t.indexOf('入苑料')>=0)&&p.children.length<=10){anchor=p;break;}
                p=p.parentElement;
              }
              anchor.parentNode.insertBefore(wrap,anchor);
            }else{
              var content=document.querySelector('main article .entry-content,main .entry-content,article .entry-content,.entry-content,main article,main');
              if(content)content.insertBefore(wrap,content.firstChild);else document.body.insertBefore(wrap,document.body.firstChild);
            }
            return wrap;
          }
          function reveal(){var x=document.getElementById('gos-v3-boot-hide');if(x)x.remove();document.body.style.visibility='visible'}
          function run(){ensure();reveal()}
          if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
          var busy=false;
          var obs=new MutationObserver(function(){if(busy)return;busy=true;setTimeout(function(){ensure();busy=false},80)});
          obs.observe(document.documentElement,{childList:true,subtree:true});
          setInterval(ensure,1200);
          setTimeout(reveal,2500);
        })();
        </script>
        <?php
    }

    public static function preview_post() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        $token=sanitize_text_field((string)($_GET['gos_preview'] ?? ''));
        $data=get_transient('gos_v3_preview_'.$token);
        if (!is_array($data)) wp_die('プレビューの有効期限が切れました。');
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        self::front_styles(true);
        echo '</head><body style="margin:0;background:#f2f2f2;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">';
        echo self::render($data,true);
        echo '</body></html>';
        exit;
    }

    public static function mobile_preview_shell() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        $target=esc_url_raw((string)($_GET['target'] ?? home_url('/')));
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>スマホ実画面プレビュー</title><style>body{margin:0;background:#222;font-family:sans-serif}.bar{padding:10px;color:#fff;text-align:center}.phone{width:390px;max-width:calc(100vw - 20px);height:calc(100vh - 52px);margin:auto;background:#fff;border-radius:22px 22px 0 0;overflow:hidden}.phone iframe{width:100%;height:100%;border:0}</style></head><body><div class="bar">スマホ実画面プレビュー</div><div class="phone"><iframe src="'.esc_url($target).'&gos_force_mobile=1"></iframe></div></body></html>';
        exit;
    }

    public static function force_mobile_preview($is_mobile) {
        return !empty($_GET['gos_force_mobile']) ? true : $is_mobile;
    }

    public static function body_class($classes) {
        if (self::preview_token_options()) $classes[]='gos-v3-preview-mode';
        if (!empty($_GET['gos_force_mobile'])) $classes[]='gos-v3-force-mobile';
        return $classes;
    }

    private static function render_event_overview($event, $include_wrapper = true) {
        $date_text = self::event_overview_date_text($event);
        $open = self::format_time($event['open_time'] ?? '');
        $close = self::format_time($event['close_time'] ?? '');
        $time_text = trim($open . (($open && $close) ? '～' : '') . $close);
        $close_label = trim((string)($event['close_time_label'] ?? ''));
        if ($time_text !== '' && $close_label !== '') $time_text .= '（' . $close_label . '）';
        $price = trim((string)($event['price_details'] ?? ''));
        if ($price === '') $price = trim((string)($event['price'] ?? ''));
        $rows = [];
        if ($date_text !== '') $rows[] = [trim((string)($event['date_label'] ?? '開苑期間')), $date_text, false];
        if ($time_text !== '') $rows[] = [trim((string)($event['time_label'] ?? '開苑時間')), $time_text, false];
        if ($price !== '') $rows[] = [trim((string)($event['admission_label'] ?? '入苑料')), $price, true];
        if (!$rows && trim((string)($event['time_note'] ?? '')) === '' && trim((string)($event['price_note'] ?? '')) === '' && trim((string)($event['overview_note'] ?? '')) === '') return '';
        ob_start();
        if ($include_wrapper) echo '<div class="gos-event-info-wrap">';
        echo '<section class="gos-event-info">';
        foreach ($rows as $row) {
            echo '<div class="gos-event-info__row"><div class="gos-event-info__label">' . esc_html($row[0]) . '：</div><div class="gos-event-info__value">';
            if ($row[2]) {
                foreach (preg_split('/\r\n|\r|\n/', $row[1]) as $line) echo '<p>' . esc_html($line) . '</p>';
            } else echo esc_html($row[1]);
            echo '</div></div>';
        }
        foreach (['time_note','price_note','overview_note'] as $field) {
            $note=trim((string)($event[$field] ?? ''));
            if ($note!=='') echo '<p class="gos-event-info__note">' . nl2br(esc_html($note)) . '</p>';
        }
        echo '</section>';
        if ($include_wrapper) echo '</div>';
        return ob_get_clean();
    }

    public static function shortcode_event_info($atts) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
        if (!defined('DONOTMINIFY')) define('DONOTMINIFY', true);

        $atts = shortcode_atts([
            'season' => 'spring',
        ], $atts, 'garden_event_info');
        $season = sanitize_key((string)$atts['season']);
        if (!in_array($season, self::event_keys(), true)) return '';
        $o = self::options();
        $event = $o['events'][$season] ?? null;
        if (!is_array($event)) return '';
        return self::render_event_overview($event, true);
    }

    public static function shortcode_status($atts=[]) { return self::render(self::options(),false); }
    public static function shortcode_event($atts=[]) {
        $atts=shortcode_atts(['season'=>'spring','field'=>'label','fallback'=>''],$atts,'garden_event');
        $season=sanitize_key($atts['season']); $field=sanitize_key($atts['field']);
        $o=self::options(); if(!isset($o['events'][$season])) return esc_html($atts['fallback']);
        $e=$o['events'][$season]; $value='';
        if($field==='date_range') $value=self::event_date_text($e);
        elseif($field==='open_time') $value=self::format_time($e['open_time']);
        elseif($field==='close_time') $value=self::format_time($e['close_time']);
        elseif(array_key_exists($field,$e)) $value=$e[$field];
        return esc_html($value!==''?$value:$atts['fallback']);
    }

    private static function resolve($o,$now) {
        $today=wp_date('Y-m-d',$now); $events=[];
        foreach(self::event_keys() as $key){$e=$o['events'][$key];if(empty($e['enabled']))continue;$s=strtotime($e['start'].' 00:00:00');$en=strtotime($e['end'].' 23:59:59');if($s&&$en)$events[$key]=[$s,$en];}
        uasort($events,fn($a,$b)=>$a[0]<=>$b[0]);
        if($o['state_mode']==='manual') return ['state'=>$o['manual_state'],'event'=>$o['manual_event']];
        if($o['temporary_closed_date']===$today) return ['state'=>'temporary_closed','event'=>$o['manual_event']];
        foreach($events as $key=>$r){[$s,$en]=$r;if($now<$s)break;if($now>$en){$days=(int)$o['events'][$key]['post_end_days'];if($now<=$en+$days*DAY_IN_SECONDS)return['state'=>'event_ended','event'=>$key];continue;}$e=$o['events'][$key];$open=strtotime($today.' '.$e['open_time']);$close=strtotime($today.' '.$e['close_time']);if($now<$open)return['state'=>'before_open','event'=>$key];if($now<=$close)return['state'=>'open','event'=>$key];return['state'=>'after_close','event'=>$key];}
        if($o['next_mode']==='auto'){foreach($events as $key=>$r)if($now<$r[0])return['state'=>'next_notice','event'=>$key];}
        return['state'=>'closed','event'=>$o['manual_event']];
    }

    private static function replace_tokens($text,$e) {
        return strtr((string)$text,['{event}'=>$e['label'],'{open_time}'=>self::format_time($e['open_time']),'{close_time}'=>self::format_time($e['close_time']),'{date_range}'=>self::event_date_text($e)]);
    }

    private static function split_title($title,$event_label) {
        $pos=mb_strpos($title,$event_label); if($pos===false)return[$title,'',''];
        return[mb_substr($title,0,$pos),$event_label,mb_substr($title,$pos+mb_strlen($event_label))];
    }

    private static function design_css($d) {
        $hex=ltrim($d['background_color'],'#');
        $r=hexdec(substr($hex,0,2));$g=hexdec(substr($hex,2,2));$b=hexdec(substr($hex,4,2));$a=$d['background_opacity']/100;
        $shadow=round(0.45*$d['shadow_strength']/100,3);$radius=$d['layout']==='circle'?'50%':$d['radius'].'px';
        $vars=[];
        foreach(['eyebrow','title_before','event','title_after','detail','price','actions'] as $el){$align=$d[$el.'_align'];$origin=$align==='left'?'left':($align==='right'?'right':'center');$vars[]="--gos-{$el}-x:{$d[$el.'_x']}px;--gos-{$el}-y:{$d[$el.'_y']}px;--gos-{$el}-align:{$align};--gos-{$el}-origin:{$origin}";}
        return "--gos-width:{$d['width']}px;--gos-height:{$d['height']}px;--gos-radius:$radius;--gos-offset-x:{$d['offset_x']}px;--gos-offset-y:{$d['offset_y']}px;--gos-padding-x:{$d['padding_x']}px;--gos-padding-y:{$d['padding_y']}px;--gos-bg:rgba($r,$g,$b,$a);--gos-shadow:0 16px 40px rgba(0,0,0,$shadow);--gos-text:{$d['text_color']};--gos-muted:{$d['muted_color']};--gos-align:{$d['text_align']};--gos-eyebrow-size:{$d['eyebrow_size']}px;--gos-title-size:{$d['title_size']}px;--gos-event-size:{$d['event_size']}px;--gos-detail-size:{$d['detail_size']}px;--gos-price-size:{$d['price_size']}px;--gos-button-size:{$d['button_size']}px;--gos-title-weight:{$d['title_weight']};--gos-event-weight:{$d['event_weight']};--gos-eyebrow-lh:".($d['eyebrow_line_height']/100).";--gos-title-lh:".($d['title_line_height']/100).";--gos-event-lh:".($d['event_line_height']/100).";--gos-detail-lh:".($d['detail_line_height']/100).";--gos-price-lh:".($d['price_line_height']/100).";--gos-eyebrow-m:{$d['eyebrow_margin']}px;--gos-detail-m:{$d['detail_margin']}px;--gos-price-m:{$d['price_margin']}px;--gos-actions-m:{$d['actions_margin']}px;--gos-button-min:{$d['button_min_width']}px;--gos-button-radius:{$d['button_radius']}px;--gos-button-bg:{$d['button_background']};--gos-button-text:{$d['button_text_color']};--gos-button-border:{$d['button_border_color']};".implode(';',$vars);
    }

    private static function render($o,$preview=false) {
        if(!$preview&&empty($o['enabled']))return'';$resolved=self::resolve($o,self::now());$state=$resolved['state'];$event_key=$resolved['event'];
        if(!isset($o['events'][$event_key]))$event_key='spring';$e=$o['events'][$event_key];$t=$o['texts'][$state];$so=$o['state_options'][$state];
        $eyebrow=self::replace_tokens($t['eyebrow'],$e);$title=self::replace_tokens($t['title'],$e);$detail=self::replace_tokens($t['detail'],$e);[$before,$event,$after]=self::split_title($title,$e['label']);
        $desktop=self::design_css($o['designs'][$event_key][$state]['desktop']);$mobile=self::design_css($o['designs'][$event_key][$state]['mobile']);
        ob_start(); ?><section class="gos-opening" aria-label="<?php echo esc_attr($o['aria_label']); ?>" data-state="<?php echo esc_attr($state); ?>" data-event="<?php echo esc_attr($event_key); ?>" style="--gos-desktop:1;<?php echo esc_attr($desktop); ?>" data-desktop-style="<?php echo esc_attr($desktop); ?>" data-mobile-style="<?php echo esc_attr($mobile); ?>"><div class="gos-opening__stage"><div class="gos-opening__panel"><div class="gos-opening__eyebrow"><?php echo nl2br(esc_html($eyebrow)); ?></div><div class="gos-opening__title"><span class="gos-opening__title-before"><?php echo nl2br(esc_html($before)); ?></span><?php if($event!==''): ?><span class="gos-opening__event"><?php echo esc_html($event); ?></span><?php endif; ?><span class="gos-opening__title-after"><?php echo nl2br(esc_html($after)); ?></span></div><?php if($detail!==''): ?><div class="gos-opening__detail"><?php echo nl2br(esc_html($detail)); ?></div><?php endif; ?><?php if(!empty($so['show_price'])&&$e['price']!==''): ?><div class="gos-opening__price"><?php echo esc_html($e['price']); ?></div><?php endif; ?><div class="gos-opening__actions"><?php if(!empty($so['show_detail_button'])&&$e['detail_url']!==''): ?><a href="<?php echo esc_url($e['detail_url']); ?>"><?php echo esc_html($o['detail_button']); ?></a><?php endif; ?><?php if(!empty($so['show_access_button'])&&$o['access_url']!==''): ?><a href="<?php echo esc_url($o['access_url']); ?>"><?php echo esc_html($o['access_button']); ?></a><?php endif; ?></div></div></div></section><?php return ob_get_clean();
    }

    public static function front_styles($echo=false) {
        $css='<style>.gos-opening{position:relative;display:block;width:100%;min-height:var(--gos-height);color:var(--gos-text)}.gos-opening__stage{position:relative;min-height:var(--gos-height);display:flex;align-items:center;justify-content:center;overflow:visible}.gos-opening__panel{box-sizing:border-box;width:min(var(--gos-width),calc(100vw - 24px));height:var(--gos-height);border-radius:var(--gos-radius);background:var(--gos-bg);box-shadow:var(--gos-shadow);padding:var(--gos-padding-y) var(--gos-padding-x);display:flex;flex-direction:column;justify-content:center;text-align:var(--gos-align);transform:translate(var(--gos-offset-x),var(--gos-offset-y));overflow:hidden}.gos-opening__eyebrow{font-size:var(--gos-eyebrow-size);line-height:var(--gos-eyebrow-lh);color:var(--gos-muted);margin-bottom:var(--gos-eyebrow-m);transform:translate(var(--gos-eyebrow-x),var(--gos-eyebrow-y));text-align:var(--gos-eyebrow-align);transform-origin:var(--gos-eyebrow-origin)}.gos-opening__title{display:flex;flex-direction:column;font-size:var(--gos-title-size);font-weight:var(--gos-title-weight);line-height:var(--gos-title-lh)}.gos-opening__title-before{transform:translate(var(--gos-title_before-x),var(--gos-title_before-y));text-align:var(--gos-title_before-align);transform-origin:var(--gos-title_before-origin)}.gos-opening__event{font-size:var(--gos-event-size);font-weight:var(--gos-event-weight);line-height:var(--gos-event-lh);transform:translate(var(--gos-event-x),var(--gos-event-y));text-align:var(--gos-event-align);transform-origin:var(--gos-event-origin)}.gos-opening__title-after{transform:translate(var(--gos-title_after-x),var(--gos-title_after-y));text-align:var(--gos-title_after-align);transform-origin:var(--gos-title_after-origin)}.gos-opening__detail{font-size:var(--gos-detail-size);line-height:var(--gos-detail-lh);color:var(--gos-muted);margin-bottom:var(--gos-detail-m);transform:translate(var(--gos-detail-x),var(--gos-detail-y));text-align:var(--gos-detail-align);transform-origin:var(--gos-detail-origin)}.gos-opening__price{font-size:var(--gos-price-size);line-height:var(--gos-price-lh);margin-bottom:var(--gos-price-m);transform:translate(var(--gos-price-x),var(--gos-price-y));text-align:var(--gos-price-align);transform-origin:var(--gos-price-origin)}.gos-opening__actions{display:flex;gap:10px;justify-content:var(--gos-actions-align);margin-top:var(--gos-actions-m);transform:translate(var(--gos-actions-x),var(--gos-actions-y));transform-origin:var(--gos-actions-origin)}.gos-opening__actions a{box-sizing:border-box;min-width:var(--gos-button-min);padding:9px 14px;border:1px solid var(--gos-button-border);border-radius:var(--gos-button-radius);background:var(--gos-button-bg);color:var(--gos-button-text);font-size:var(--gos-button-size);text-decoration:none;text-align:center}.gos-event-info-wrap{margin:1.5em 0}.gos-event-info{font-size:14px;line-height:1.9}.gos-event-info__row{display:grid;grid-template-columns:7.1em minmax(0,1fr);gap:.5em;margin:.15em 0}.gos-event-info__label{white-space:nowrap;text-align:right}.gos-event-info__value{min-width:0}.gos-event-info__value p{margin:0;line-height:inherit}.gos-event-info__note{margin:.7em 0 0;white-space:normal}@media(max-width:600px){.gos-event-info{font-size:12px;line-height:1.85}.gos-event-info__row{grid-template-columns:6.8em minmax(0,1fr);gap:.4em}.gos-event-info__label{text-align:right}}@media(max-width:600px){.gos-opening{'.'}.gos-opening__panel{width:min(var(--gos-width),calc(100vw - 16px))}}body.gos-v3-force-mobile .gos-opening{}</style>';
        if($echo)echo$css;else echo$css;
    }
}
Garden_Opening_Status_V3::init();
