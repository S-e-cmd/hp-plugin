<?php
/**
 * Plugin Name: 開催状況 自動表示
 * Description: 春・秋・冬の開催情報を一元管理し、トップページの開催状況を自動表示します。
 * Version: 3.0.18.4
 * Author: Site Admin
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Garden_Opening_Status_V3 {
    const OPTION = 'garden_opening_status_options';
    const VERSION_OPTION = 'garden_opening_status_version';
    const VERSION = '3.0.18.4';
    const NONCE = 'gos_v3_save';
    const PREVIEW_NONCE = 'gos_v3_preview';
    const LAYOUTS_OPTION = 'gos_v3_layout_templates';
    const DEFAULT_LAYOUT_OPTION = 'gos_v3_default_layout_template';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_migrate']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_ajax_gos_v3_preview_save', [__CLASS__, 'ajax_preview_save']);
        add_action('wp_ajax_gos_v3_layout_templates_save', [__CLASS__, 'ajax_layout_templates_save']);
        add_action('admin_post_gos_v3_preview_post', [__CLASS__, 'preview_post']);
        add_action('admin_post_gos_v3_mobile_preview', [__CLASS__, 'mobile_preview_shell']);
        add_filter('wp_is_mobile', [__CLASS__, 'force_mobile_preview']);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('wp_head', [__CLASS__, 'boot_hide'], 0);
        add_action('wp_head', [__CLASS__, 'front_styles'], 99);
        add_action('wp_footer', [__CLASS__, 'front_script'], 99);
        add_shortcode('garden_opening_status', [__CLASS__, 'shortcode_status']);
        add_shortcode('garden_event', [__CLASS__, 'shortcode_event']);
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
        if (is_array($saved) && (int)($saved['schema_version'] ?? 0) >= 3) {
            $saved_version = (string)get_option(self::VERSION_OPTION, '');
            // 3.0.15: 旧「状態×端末」デザインを「季節×状態×端末」へ複製して移行。
            if (!isset($saved['designs']['spring']) || !is_array($saved['designs']['spring'])) {
                $legacy_designs = is_array($saved['designs'] ?? null) ? $saved['designs'] : [];
                $season_designs = [];
                foreach (self::event_keys() as $event_key) {
                    foreach (self::state_keys() as $state) {
                        foreach (['desktop','mobile'] as $device) {
                            $season_designs[$event_key][$state][$device] =
                                is_array($legacy_designs[$state][$device] ?? null)
                                    ? $legacy_designs[$state][$device]
                                    : self::default_device_design($device);
                        }
                    }
                }
                $saved['designs'] = $season_designs;
                update_option(self::OPTION, $saved, false);
            }
            if (version_compare($saved_version, '3.0.12', '<')) {
                foreach (self::event_keys() as $event_key) {
                    foreach (self::state_keys() as $state) {
                    foreach (['desktop','mobile'] as $device) {
                        if (empty($saved['designs'][$event_key][$state][$device]) || !is_array($saved['designs'][$event_key][$state][$device])) continue;
                        $d =& $saved['designs'][$event_key][$state][$device];
                        $bg = strtolower((string)($d['button_background'] ?? ''));
                        if ($bg === '' || $bg === '#000000' || $bg === '000000') $d['button_background'] = '#ffffff';
                        $text = strtolower((string)($d['button_text_color'] ?? ''));
                        if ($text === '' || $text === '#000000' || $text === '000000') $d['button_text_color'] = '#303030';
                        $border = strtolower((string)($d['button_border_color'] ?? ''));
                        if ($border === '' || $border === '#000000' || $border === '000000') $d['button_border_color'] = '#555555';
                        if (empty($d['button_min_width'])) $d['button_min_width'] = $device === 'mobile' ? 112 : 120;
                        if (!isset($d['button_radius']) || (int)$d['button_radius'] === 0) $d['button_radius'] = 999;
                    }
                    }
                }
                update_option(self::OPTION, $saved, false);
            }
            update_option(self::VERSION_OPTION, self::VERSION, false);
            return;
        }
        $new = self::defaults();
        if (is_array($saved) && $saved) {
            $new['enabled'] = !empty($saved['enabled']) ? 1 : 0;
            $new['next_mode'] = sanitize_key($saved['next_mode'] ?? 'auto');
            $new['temporary_closed_date'] = self::clean_date($saved['temporary_closed_date'] ?? '');
            $new['access_url'] = esc_url_raw($saved['access_url'] ?? $new['access_url']);
            if (!empty($saved['texts']['detail_button'])) $new['detail_button'] = sanitize_text_field($saved['texts']['detail_button']);
            if (!empty($saved['texts']['access_button'])) $new['access_button'] = sanitize_text_field($saved['texts']['access_button']);
            if (!empty($saved['texts']['aria_label'])) $new['aria_label'] = sanitize_text_field($saved['texts']['aria_label']);

            foreach (self::event_keys() as $key) {
                $old = $saved['seasons'][$key] ?? [];
                if ($old) {
                    $new['events'][$key]['label'] = sanitize_text_field($old['label'] ?? $new['events'][$key]['label']);
                    $new['events'][$key]['start'] = self::clean_date($old['start'] ?? '');
                    $new['events'][$key]['end'] = self::clean_date($old['end'] ?? '');
                    $new['events'][$key]['open_time'] = self::clean_time($old['open_time'] ?? '09:00');
                    $new['events'][$key]['close_time'] = self::clean_time($old['close_time'] ?? '17:00');
                    $new['events'][$key]['detail_url'] = esc_url_raw($old['detail_url'] ?? '');
                    $new['events'][$key]['price'] = sanitize_text_field($saved['fee_text'] ?? '');
                    $status = $old['status'] ?? 'undecided';
                    $new['events'][$key]['enabled'] = $status === 'cancelled' ? 0 : 1;
                }
            }

            $text_map = [
                'temporary_closed' => 'temporary_closed',
                'before_open' => 'before_open',
                'open' => 'open',
                'after_close' => 'after_close',
                'event_ended' => 'after_close',
                'closed' => 'off_season',
                'next_notice' => !empty($saved['texts']['next_confirmed_title']) ? 'next_confirmed' : 'planned',
            ];
            foreach ($text_map as $new_state => $old_state) {
                foreach (['eyebrow', 'title', 'detail'] as $part) {
                    $old_key = $old_state . '_' . $part;
                    if (isset($saved['texts'][$old_key])) $new['texts'][$new_state][$part] = sanitize_textarea_field($saved['texts'][$old_key]);
                }
            }

            foreach (self::state_keys() as $state) {
                $legacy_key = $state;
                if ($state === 'event_ended') $legacy_key = 'after_close';
                if ($state === 'closed') $legacy_key = 'off_season';
                if ($state === 'next_notice') $legacy_key = 'next_confirmed';
                $legacy = $saved['state_design'][$legacy_key] ?? ($saved['design'] ?? []);
                if (is_array($legacy) && $legacy) {
                    foreach (self::event_keys() as $event_key) {
                        $new['designs'][$event_key][$state]['desktop'] = self::convert_legacy_design($legacy, 'desktop');
                        $new['designs'][$event_key][$state]['mobile'] = self::convert_legacy_design($legacy, 'mobile');
                    }
                }
            }
        }
        update_option(self::OPTION, $new, false);
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    private static function convert_legacy_design($old, $device) {
        $d = self::default_device_design($device);
        $mobile = $device === 'mobile';
        $map = [
            'layout' => $mobile ? 'mobile_layout' : 'desktop_layout',
            'width' => $mobile ? 'mobile_width' : 'desktop_width',
            'height' => $mobile ? 'mobile_min_height' : 'desktop_min_height',
            'radius' => $mobile ? 'mobile_border_radius' : 'border_radius',
            'offset_x' => $mobile ? 'mobile_offset_x' : 'desktop_offset_x',
            'offset_y' => $mobile ? 'mobile_offset_y' : 'desktop_offset_y',
            'padding_x' => $mobile ? 'mobile_padding_x' : 'desktop_padding_x',
            'padding_y' => $mobile ? 'mobile_padding_y' : 'desktop_padding_y',
            'eyebrow_size' => $mobile ? 'eyebrow_mobile_size' : 'eyebrow_size',
            'title_size' => $mobile ? 'title_mobile_size' : 'title_size',
            'event_size' => $mobile ? 'event_mobile_size' : 'event_size',
            'detail_size' => $mobile ? 'detail_mobile_size' : 'detail_size',
            'price_size' => $mobile ? 'fee_mobile_size' : 'fee_size',
            'button_size' => $mobile ? 'button_mobile_size' : 'button_size',
        ];
        foreach ($map as $new_key => $old_key) if (isset($old[$old_key])) $d[$new_key] = $old[$old_key];
        foreach (['background_color','background_opacity','shadow_strength','text_color','text_align','title_weight','event_weight','eyebrow_line_height','title_line_height','event_line_height','detail_line_height','price_line_height','button_min_width','button_radius','button_background','button_text_color','button_border_color'] as $key) {
            if (isset($old[$key])) $d[$key] = $old[$key];
        }
        if (isset($old['muted_text_color'])) $d['muted_color'] = $old['muted_text_color'];
        if (isset($old['eyebrow_margin_bottom'])) $d['eyebrow_margin'] = $old['eyebrow_margin_bottom'];
        if (isset($old['detail_margin_top'])) $d['detail_margin'] = $old['detail_margin_top'];
        if (isset($old['fee_margin_top'])) $d['price_margin'] = $old['fee_margin_top'];
        if (isset($old['actions_margin_top'])) $d['actions_margin'] = $old['actions_margin_top'];
        foreach (['eyebrow','title_before','event','title_after','detail','actions'] as $el) {
            foreach (['x','y','align'] as $axis) {
                $old_key = $device . '_' . $el . '_' . $axis;
                if (isset($old[$old_key])) $d[$el . '_' . $axis] = $old[$old_key];
            }
        }
        foreach (['x','y','align'] as $axis) {
            $old_key = $device . '_fee_' . $axis;
            if (isset($old[$old_key])) $d['price_' . $axis] = $old[$old_key];
        }
        return $d;
    }

    private static function options($allow_preview = true) {
        if ($allow_preview && current_user_can('manage_options')) {
            $token = sanitize_key($_GET['gos_preview_token'] ?? '');
            if ($token) {
                $preview = get_transient(self::preview_key($token));
                if (is_array($preview)) return self::normalize($preview);
            }
        }
        return self::normalize(get_option(self::OPTION, []));
    }

    private static function normalize($saved) {
        $defaults = self::defaults();
        $normalized = is_array($saved) ? array_replace_recursive($defaults, $saved) : $defaults;
        $templates = self::stored_layout_templates();
        $normalized['layout_templates'] = $templates;
        $normalized['default_layout_template'] = self::stored_default_layout_template($templates);
        return $normalized;
    }

    private static function preview_key($token) {
        return 'gos3_' . get_current_user_id() . '_' . substr(preg_replace('/[^a-z0-9_-]/i', '', $token), 0, 40);
    }

    public static function admin_menu() {
        add_menu_page('開催状況', '開催状況', 'manage_options', 'garden-opening-status', [__CLASS__, 'admin_page'], 'dashicons-calendar-alt', 25);
    }

    public static function admin_assets($hook) {
        if ($hook !== 'toplevel_page_garden-opening-status') return;
        wp_enqueue_style('gos-v3-admin', plugins_url('assets/admin.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('gos-v3-admin', plugins_url('assets/admin.js', __FILE__), [], self::VERSION, true);
        wp_localize_script('gos-v3-admin', 'GOS_V3', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce(self::PREVIEW_NONCE),
            'homeUrl' => home_url('/'),
            'mobileShellUrl' => admin_url('admin-post.php?action=gos_v3_mobile_preview'),
            'previewPostUrl' => admin_url('admin-post.php'),
            'stateLabels' => self::state_labels(),
        ]);
    }

    public static function handle_save() {
        if (!is_admin() || empty($_POST['gos_v3_action'])) return;
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);
        $clean = self::sanitize_payload(wp_unslash($_POST));
        update_option(self::OPTION, $clean, false);
        update_option(self::VERSION_OPTION, self::VERSION, false);
        wp_safe_redirect(add_query_arg(['page' => 'garden-opening-status', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function sanitize_payload($input) {
        $out = self::defaults();
        $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $allowed_next = array_merge(['auto', 'none'], self::event_keys());
        $out['next_mode'] = in_array(($input['next_mode'] ?? 'auto'), $allowed_next, true) ? $input['next_mode'] : 'auto';
        $out['state_mode'] = in_array(($input['state_mode'] ?? 'manual'), ['auto','manual'], true) ? $input['state_mode'] : 'manual';
        $out['manual_state'] = in_array(($input['manual_state'] ?? 'closed'), self::state_keys(), true) ? $input['manual_state'] : 'closed';
        $out['manual_event'] = in_array(($input['manual_event'] ?? 'spring'), self::event_keys(), true) ? $input['manual_event'] : 'spring';
        $out['temporary_closed_date'] = self::clean_date($input['temporary_closed_date'] ?? '');
        $out['access_url'] = esc_url_raw($input['access_url'] ?? '');
        $out['detail_button'] = sanitize_text_field($input['detail_button'] ?? '');
        $out['access_button'] = sanitize_text_field($input['access_button'] ?? '');
        $out['aria_label'] = sanitize_text_field($input['aria_label'] ?? '');

        foreach (self::event_keys() as $key) {
            $src = is_array($input['events'][$key] ?? null) ? $input['events'][$key] : [];
            $out['events'][$key] = [
                'enabled' => !empty($src['enabled']) ? 1 : 0,
                'label' => sanitize_text_field($src['label'] ?? ''),
                'usual_period' => sanitize_text_field($src['usual_period'] ?? ''),
                'start' => self::clean_date($src['start'] ?? ''),
                'end' => self::clean_date($src['end'] ?? ''),
                'open_time' => self::clean_time($src['open_time'] ?? ''),
                'close_time' => self::clean_time($src['close_time'] ?? ''),
                'price' => sanitize_text_field($src['price'] ?? ''),
                'detail_url' => esc_url_raw($src['detail_url'] ?? ''),
                'publish_mode' => in_array(($src['publish_mode'] ?? ''), ['immediate','scheduled','manual'], true) ? $src['publish_mode'] : 'immediate',
                'publish_at' => self::clean_datetime_local($src['publish_at'] ?? ''),
                'manual_published' => !empty($src['manual_published']) ? 1 : 0,
                'post_end_days' => max(0, min(365, (int)($src['post_end_days'] ?? 30))),
            ];
        }

        foreach (self::state_keys() as $state) {
            $src = is_array($input['texts'][$state] ?? null) ? $input['texts'][$state] : [];
            $out['texts'][$state] = [
                'eyebrow' => sanitize_textarea_field($src['eyebrow'] ?? ''),
                'title' => sanitize_textarea_field($src['title'] ?? ''),
                'detail' => sanitize_textarea_field($src['detail'] ?? ''),
            ];
            $so = is_array($input['state_options'][$state] ?? null) ? $input['state_options'][$state] : [];
            $out['state_options'][$state] = [
                'show_price' => !empty($so['show_price']) ? 1 : 0,
                'show_detail_button' => !empty($so['show_detail_button']) ? 1 : 0,
                'show_access_button' => !empty($so['show_access_button']) ? 1 : 0,
            ];
        }

        $json = json_decode((string)($input['designs_json'] ?? ''), true);
        if (!is_array($json)) $json = [];
        foreach (self::event_keys() as $event_key) {
            foreach (self::state_keys() as $state) {
                foreach (['desktop','mobile'] as $device) {
                    $src = is_array($json[$event_key][$state][$device] ?? null)
                        ? $json[$event_key][$state][$device]
                        : self::default_device_design($device);
                    $out['designs'][$event_key][$state][$device] = self::sanitize_design($src, $device);
                }
            }
        }
        // レイアウトテンプレートは通常設定とは別optionで管理する。
        // プレビュー保存や通常設定保存で上書き・消失させない。
        $out['layout_templates'] = self::stored_layout_templates();
        $out['default_layout_template'] = self::stored_default_layout_template($out['layout_templates']);
        return $out;
    }

    private static function sanitize_layout_templates($templates) {
        if (!is_array($templates)) return [];
        $out = [];
        $count = 0;
        foreach ($templates as $id => $template) {
            if ($count >= 30 || !is_array($template)) break;
            $clean_id = sanitize_key((string)$id);
            if ($clean_id === '') $clean_id = 'layout_' . substr(md5(wp_json_encode($template) . $count), 0, 12);
            $name = sanitize_text_field((string)($template['name'] ?? ''));
            if ($name === '') continue;
            $out[$clean_id] = [
                'name' => mb_substr($name, 0, 80),
                'desktop' => self::sanitize_design(is_array($template['desktop'] ?? null) ? $template['desktop'] : [], 'desktop'),
                'mobile' => self::sanitize_design(is_array($template['mobile'] ?? null) ? $template['mobile'] : [], 'mobile'),
            ];
            $count++;
        }
        return $out;
    }

    public static function ajax_layout_templates_save() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => '権限がありません。'], 403);
        check_ajax_referer(self::PREVIEW_NONCE, 'nonce');

        $raw = json_decode((string)wp_unslash($_POST['templates_json'] ?? ''), true);
        $templates = self::sanitize_layout_templates($raw);
        $default_id = sanitize_key((string)wp_unslash($_POST['default_layout_template'] ?? ''));
        if ($default_id !== '' && !isset($templates[$default_id])) $default_id = '';

        // 通常設定とは完全に分離して保存する。
        update_option(self::LAYOUTS_OPTION, $templates, false);
        update_option(self::DEFAULT_LAYOUT_OPTION, $default_id, false);

        // DBから再取得して照合し、実保存できた場合だけ成功を返す。
        $stored_templates = self::stored_layout_templates();
        $stored_default = self::stored_default_layout_template($stored_templates);
        if (wp_json_encode($stored_templates) !== wp_json_encode($templates) || $stored_default !== $default_id) {
            wp_send_json_error(['message' => 'レイアウトをデータベースへ保存できませんでした。'], 500);
        }

        wp_send_json_success([
            'message' => 'レイアウトを保存しました。',
            'templates' => $stored_templates,
            'default_layout_template' => $stored_default,
        ]);
    }

    private static function sanitize_design($src, $device) {
        $d = self::default_device_design($device);
        $ranges = [
            'width' => [120, 1400], 'height' => [120, 1000], 'radius' => [0, 999],
            'offset_x' => [-500, 500], 'offset_y' => [-500, 500],
            'padding_x' => [0, 200], 'padding_y' => [0, 200],
            'background_opacity' => [0, 100], 'shadow_strength' => [0, 100],
            'eyebrow_size' => [8, 100], 'title_size' => [8, 140], 'event_size' => [8, 160],
            'detail_size' => [8, 100], 'price_size' => [8, 100], 'button_size' => [8, 80],
            'title_weight' => [100, 900], 'event_weight' => [100, 900],
            'eyebrow_line_height' => [80, 250], 'title_line_height' => [80, 250], 'event_line_height' => [80, 250],
            'detail_line_height' => [80, 250], 'price_line_height' => [80, 250],
            'eyebrow_margin' => [-100, 200], 'detail_margin' => [-100, 200], 'price_margin' => [-100, 200], 'actions_margin' => [-100, 200],
            'button_min_width' => [0, 500], 'button_radius' => [0, 999],
        ];
        foreach (['eyebrow','title_before','event','title_after','detail','price','actions'] as $el) {
            $ranges[$el . '_x'] = [-500, 500];
            $ranges[$el . '_y'] = [-500, 500];
        }
        foreach ($ranges as $key => $range) {
            $value = isset($src[$key]) ? (int)$src[$key] : (int)$d[$key];
            $d[$key] = max($range[0], min($range[1], $value));
        }
        $d['layout'] = in_array(($src['layout'] ?? ''), ['circle','horizontal','vertical','free'], true) ? $src['layout'] : $d['layout'];
        $d['text_align'] = in_array(($src['text_align'] ?? ''), ['left','center','right'], true) ? $src['text_align'] : 'center';
        foreach (['eyebrow','title_before','event','title_after','detail','price','actions'] as $el) {
            $key = $el . '_align';
            $d[$key] = in_array(($src[$key] ?? ''), ['left','center','right'], true) ? $src[$key] : 'center';
        }
        foreach (['background_color','text_color','muted_color','button_background','button_text_color','button_border_color'] as $key) {
            $color = sanitize_hex_color($src[$key] ?? '');
            if ($color) $d[$key] = $color;
        }
        return $d;
    }

    private static function clean_date($value) {
        $value = sanitize_text_field((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function clean_time($value) {
        $value = sanitize_text_field((string)$value);
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    private static function clean_datetime_local($value) {
        $value = sanitize_text_field((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
    }

    public static function ajax_preview_save() {
        check_ajax_referer(self::PREVIEW_NONCE, 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => '権限がありません。'], 403);
        $token = sanitize_key($_POST['preview_token'] ?? '');
        if (!$token) wp_send_json_error(['message' => 'プレビュー識別子がありません。'], 400);
        $clean = self::sanitize_payload(wp_unslash($_POST));
        set_transient(self::preview_key($token), $clean, 30 * MINUTE_IN_SECONDS);
        wp_send_json_success(['token' => $token]);
    }

    public static function preview_post() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::PREVIEW_NONCE, 'preview_nonce');
        $token = sanitize_key($_POST['preview_token'] ?? '');
        if (!$token) wp_die('プレビュー識別子がありません。');
        $clean = self::sanitize_payload(wp_unslash($_POST));
        set_transient(self::preview_key($token), $clean, 30 * MINUTE_IN_SECONDS);
        $state = sanitize_key($_POST['preview_state'] ?? '');
        if (!in_array($state, self::state_keys(), true)) $state = $clean['state_mode'] === 'manual' ? $clean['manual_state'] : '';
        $event = sanitize_key($_POST['preview_event'] ?? 'spring');
        if (!in_array($event, self::event_keys(), true)) $event = 'spring';
        $device = sanitize_key($_POST['preview_device'] ?? 'desktop');
        if (!in_array($device, ['desktop','mobile'], true)) $device = 'desktop';
        if (sanitize_key($_POST['preview_mode'] ?? '') === 'mobile_shell') {
            $url = add_query_arg(['action'=>'gos_v3_mobile_preview','token'=>$token,'state'=>$state,'event'=>$event], admin_url('admin-post.php'));
        } else {
            $args = ['garden_status_preview'=>1,'gos_preview_token'=>$token,'gos_preview_device'=>$device,'_gos'=>time()];
            if ($state) $args['gos_force_state'] = $state;
            if ($event) $args['gos_force_event'] = $event;
            $url = add_query_arg($args, home_url('/'));
        }
        wp_safe_redirect($url);
        exit;
    }

    public static function force_mobile_preview($is_mobile) {
        if (!current_user_can('manage_options') || empty($_GET['garden_status_preview'])) return $is_mobile;
        $device = sanitize_key($_GET['gos_preview_device'] ?? '');
        if ($device === 'mobile') return true;
        if ($device === 'desktop') return false;
        return $is_mobile;
    }

    public static function body_class($classes) {
        if (current_user_can('manage_options') && !empty($_GET['garden_status_preview'])) {
            $device = sanitize_key($_GET['gos_preview_device'] ?? '');
            if ($device === 'mobile') $classes[] = 'gos-force-mobile';
            if ($device === 'desktop') $classes[] = 'gos-force-desktop';
        }
        return $classes;
    }

    private static function now() {
        return new DateTimeImmutable('now', wp_timezone());
    }

    private static function dt($date, $time = '00:00') {
        if (!$date) return null;
        try { return new DateTimeImmutable($date . ' ' . ($time ?: '00:00'), wp_timezone()); }
        catch (Exception $e) { return null; }
    }

    private static function publish_dt($value) {
        if (!$value) return null;
        try { return new DateTimeImmutable(str_replace('T', ' ', $value), wp_timezone()); }
        catch (Exception $e) { return null; }
    }

    private static function event_released($event, $now) {
        if (($event['publish_mode'] ?? 'immediate') === 'manual') return !empty($event['manual_published']);
        if (($event['publish_mode'] ?? 'immediate') === 'scheduled') {
            $at = self::publish_dt($event['publish_at'] ?? '');
            return $at && $now >= $at;
        }
        return true;
    }

    private static function event_specific_dates_visible($event, $now) {
        if (!self::event_released($event, $now)) return false;
        if (empty($event['start']) || empty($event['end'])) return false;
        $end = self::dt($event['end'], '23:59');
        if (!$end) return false;
        $cutoff = $end->modify('+' . max(0, (int)$event['post_end_days']) . ' days');
        return $now <= $cutoff;
    }

    private static function event_date_text($event, $now) {
        if (self::event_specific_dates_visible($event, $now)) return self::format_date_range($event['start'], $event['end']);
        return trim((string)($event['usual_period'] ?? ''));
    }

    private static function event_vars($event, $now) {
        return [
            '{event}' => (string)($event['label'] ?? ''),
            '{date_range}' => self::event_date_text($event, $now),
            '{open_time}' => self::format_time($event['open_time'] ?? ''),
            '{close_time}' => self::format_time($event['close_time'] ?? ''),
            '{price}' => (string)($event['price'] ?? ''),
        ];
    }

    private static function current_and_future($o, $now) {
        $current = null; $future = []; $past = [];
        foreach ($o['events'] as $key => $event) {
            if (empty($event['enabled'])) continue;
            $item = ['key' => $key] + $event;
            $start = self::dt($event['start'], '00:00');
            $end = self::dt($event['end'], '23:59');
            if ($start && $end && $now >= $start && $now <= $end) $current = $item;
            elseif ($start && $start > $now) { $item['_start'] = $start; $future[] = $item; }
            elseif ($end && $end < $now) { $item['_end'] = $end; $past[] = $item; }
        }
        usort($future, fn($a,$b) => $a['_start'] <=> $b['_start']);
        usort($past, fn($a,$b) => $b['_end'] <=> $a['_end']);
        return [$current, $future, $past];
    }

    private static function choose_next_event($o, $future, $past) {
        if ($o['next_mode'] === 'none') return null;
        if (in_array($o['next_mode'], self::event_keys(), true)) {
            $event = $o['events'][$o['next_mode']] ?? null;
            return $event && !empty($event['enabled']) ? ['key' => $o['next_mode']] + $event : null;
        }
        if ($future) return $future[0];
        $order = self::event_keys();
        if ($past) {
            $last = array_search($past[0]['key'], $order, true);
            for ($i = 1; $i <= count($order); $i++) {
                $key = $order[($last + $i) % count($order)];
                if (!empty($o['events'][$key]['enabled'])) return ['key' => $key] + $o['events'][$key];
            }
        }
        foreach ($order as $key) if (!empty($o['events'][$key]['enabled'])) return ['key' => $key] + $o['events'][$key];
        return null;
    }

    private static function view_model($o = null, $forced_state = '', $forced_event = '') {
        $o = $o ?: self::options();
        $now = self::now();
        [$current, $future, $past] = self::current_and_future($o, $now);

        if ($forced_state && in_array($forced_state, self::state_keys(), true)) {
            $event_key = in_array($forced_event, self::event_keys(), true) ? $forced_event : 'spring';
            $event = ['key' => $event_key] + $o['events'][$event_key];
            return self::make_model($o, $forced_state, $event, $now);
        }

        if (($o['state_mode'] ?? 'manual') === 'manual') {
            $event_key = in_array(($o['manual_event'] ?? ''), self::event_keys(), true) ? $o['manual_event'] : 'spring';
            $event = ['key' => $event_key] + $o['events'][$event_key];
            return self::make_model($o, $o['manual_state'] ?? 'closed', $event, $now);
        }

        if ($current) {
            if ($o['temporary_closed_date'] === $now->format('Y-m-d')) return self::make_model($o, 'temporary_closed', $current, $now);
            $open = self::dt($now->format('Y-m-d'), $current['open_time']);
            $close = self::dt($now->format('Y-m-d'), $current['close_time']);
            if ($open && $now < $open) return self::make_model($o, 'before_open', $current, $now);
            if ($close && $now > $close) return self::make_model($o, 'after_close', $current, $now);
            return self::make_model($o, 'open', $current, $now);
        }

        if ($past) {
            $last = $past[0];
            $cutoff = $last['_end']->modify('+' . max(0, (int)$last['post_end_days']) . ' days');
            if ($now <= $cutoff) return self::make_model($o, 'event_ended', $last, $now);
        }

        $next = self::choose_next_event($o, $future, $past);
        if ($next && (self::event_date_text($next, $now) !== '' || !empty($next['label']))) return self::make_model($o, 'next_notice', $next, $now);
        return self::make_model($o, 'closed', null, $now);
    }

    private static function make_model($o, $state, $event, $now) {
        $event = is_array($event) ? $event : self::default_event('');
        $vars = self::event_vars($event, $now);
        $text = $o['texts'][$state] ?? ['eyebrow'=>'','title'=>'','detail'=>''];
        foreach ($text as $key => $value) $text[$key] = strtr((string)$value, $vars);
        $so = $o['state_options'][$state] ?? [];
        return [
            'state' => $state,
            'state_label' => self::state_labels()[$state] ?? $state,
            'event_key' => $event['key'] ?? '',
            'event' => (string)($event['label'] ?? ''),
            'eyebrow' => $text['eyebrow'] ?? '',
            'title' => $text['title'] ?? '',
            'detail' => $text['detail'] ?? '',
            'price' => !empty($so['show_price']) ? (string)($event['price'] ?? '') : '',
            'detail_url' => !empty($so['show_detail_button']) ? (string)($event['detail_url'] ?? '') : '',
            'show_access' => !empty($so['show_access_button']),
        ];
    }

    private static function format_time($time) {
        return $time ? preg_replace('/^0/', '', $time) : '';
    }

    private static function format_date_range($start, $end) {
        $s = self::dt($start); $e = self::dt($end);
        if (!$s || !$e) return '';
        return $s->format('Y') === $e->format('Y')
            ? $s->format('Y年n月j日') . '～' . $e->format('n月j日')
            : $s->format('Y年n月j日') . '～' . $e->format('Y年n月j日');
    }

    private static function title_html($title, $event) {
        $title = (string)$title; $event = (string)$event;
        if ($event !== '' && mb_strpos($title, $event) !== false) {
            [$before, $after] = explode($event, $title, 2);
            // {event} の前後に置いた区切り用改行は、ブロック間の巨大な空白にしない。
            // 前後の改行だけ除去し、文中の意図的な改行は残す。
            $before = rtrim($before, "\r\n");
            $after  = ltrim($after, "\r\n");
            return '<span class="gos3-title-before">' . esc_html($before) . '</span>'
                . '<span class="gos3-event">' . esc_html($event) . '</span>'
                . '<span class="gos3-title-after">' . esc_html($after) . '</span>';
        }
        return '<span class="gos3-title-before">' . esc_html($title) . '</span>';
    }

    private static function panel_html($o, $model) {
        ob_start(); ?>
        <div class="gos3-panel gos3-state-<?php echo esc_attr($model['state']); ?>" role="status" aria-live="polite">
            <?php if ($model['eyebrow'] !== ''): ?><div class="gos3-eyebrow"><?php echo esc_html($model['eyebrow']); ?></div><?php endif; ?>
            <?php if ($model['title'] !== ''): ?><div class="gos3-title"><?php echo self::title_html($model['title'], $model['event']); ?></div><?php endif; ?>
            <?php if ($model['detail'] !== ''): ?><div class="gos3-detail"><?php echo esc_html($model['detail']); ?></div><?php endif; ?>
            <?php if ($model['price'] !== ''): ?><div class="gos3-price"><?php echo esc_html($model['price']); ?></div><?php endif; ?>
            <?php if (($model['detail_url'] && $o['detail_button']) || ($model['show_access'] && $o['access_url'] && $o['access_button'])): ?>
                <div class="gos3-actions">
                    <?php if ($model['detail_url'] && $o['detail_button']): ?><a href="<?php echo esc_url($model['detail_url']); ?>"><?php echo esc_html($o['detail_button']); ?></a><?php endif; ?>
                    <?php if ($model['show_access'] && $o['access_url'] && $o['access_button']): ?><a href="<?php echo esc_url($o['access_url']); ?>"><?php echo esc_html($o['access_button']); ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php return trim(ob_get_clean());
    }

    private static function runtime_context() {
        $o = self::options();
        $forced_state = '';
        $forced_event = '';
        if (current_user_can('manage_options') && !empty($_GET['garden_status_preview'])) {
            $candidate = sanitize_key($_GET['gos_force_state'] ?? '');
            if (in_array($candidate, self::state_keys(), true)) $forced_state = $candidate;
            $candidate_event = sanitize_key($_GET['gos_force_event'] ?? '');
            if (in_array($candidate_event, self::event_keys(), true)) $forced_event = $candidate_event;
        }
        $model = self::view_model($o, $forced_state, $forced_event);
        return [$o, $model];
    }

    private static function should_render() {
        if (!is_front_page()) return false;
        [$o] = self::runtime_context();
        return !empty($o['enabled']) || (current_user_can('manage_options') && !empty($_GET['garden_status_preview']));
    }

    public static function boot_hide() {
        if (!self::should_render()) return;
        // 新表示が有効な間はテーマ標準の円を最初から描画対象外にする。
        // 新パネルは独立要素 #gos3-overlay なので、この指定の影響を受けない。
        echo '<style id="gos3-hide-original">#top-slider-content{display:none!important;visibility:hidden!important;}</style>';
    }

    private static function hex_rgb($hex) {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return [255,255,255];
        return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
    }

    public static function front_styles() {
        if (!self::should_render()) return;
        [$o, $model] = self::runtime_context();
        $event_key = in_array(($model['event_key'] ?? ''), self::event_keys(), true) ? $model['event_key'] : 'spring';
        $desktop = $o['designs'][$event_key][$model['state']]['desktop'];
        $mobile  = $o['designs'][$event_key][$model['state']]['mobile'];
        $rgbd = self::hex_rgb($desktop['background_color']);
        $rgbm = self::hex_rgb($mobile['background_color']);
        ?>
        <style id="gos3-style">
        .top-slider-wrapper{position:relative!important}
        #gos3-overlay{
          box-sizing:border-box!important;position:absolute!important;left:50%!important;top:50%!important;right:auto!important;bottom:auto!important;margin:0!important;padding:0!important;
          z-index:20!important;overflow:hidden!important;display:flex!important;align-items:center!important;justify-content:center!important;
          font-family:inherit!important;text-decoration:none!important;
        }
        #gos3-overlay *{box-sizing:border-box!important}
        #gos3-overlay.gos3-render-desktop{
          width:<?php echo (int)$desktop['width']; ?>px!important;height:<?php echo (int)($desktop['layout']==='circle'?$desktop['width']:$desktop['height']); ?>px!important;
          max-width:calc(100% - 32px)!important;min-width:120px!important;min-height:120px!important;
          transform:translate(calc(-50% + <?php echo (int)$desktop['offset_x']; ?>px),calc(-50% + <?php echo (int)$desktop['offset_y']; ?>px))!important;
          border-radius:<?php echo $desktop['layout']==='circle'?'50%':((int)$desktop['radius'].'px'); ?>!important;
          background:rgba(<?php echo implode(',',$rgbd); ?>,<?php echo ((int)$desktop['background_opacity'])/100; ?>)!important;
          box-shadow:0 8px 30px rgba(0,0,0,<?php echo ((int)$desktop['shadow_strength'])/100; ?>)!important;
          color:<?php echo esc_attr($desktop['text_color']); ?>!important;
        }
        #gos3-overlay.gos3-render-mobile{
          width:<?php echo (int)$mobile['width']; ?>px!important;height:<?php echo (int)($mobile['layout']==='circle'?$mobile['width']:$mobile['height']); ?>px!important;
          max-width:calc(100% - 24px)!important;min-width:120px!important;min-height:120px!important;
          transform:translate(calc(-50% + <?php echo (int)$mobile['offset_x']; ?>px),calc(-50% + <?php echo (int)$mobile['offset_y']; ?>px))!important;
          border-radius:<?php echo $mobile['layout']==='circle'?'50%':((int)$mobile['radius'].'px'); ?>!important;
          background:rgba(<?php echo implode(',',$rgbm); ?>,<?php echo ((int)$mobile['background_opacity'])/100; ?>)!important;
          box-shadow:0 8px 30px rgba(0,0,0,<?php echo ((int)$mobile['shadow_strength'])/100; ?>)!important;
          color:<?php echo esc_attr($mobile['text_color']); ?>!important;
        }
        #gos3-overlay .gos3-panel{width:100%!important;max-width:100%!important;height:auto!important;margin:0!important;position:static!important;line-height:1.4!important}
        #gos3-overlay.gos3-render-desktop .gos3-panel{padding:<?php echo (int)$desktop['padding_y']; ?>px <?php echo (int)$desktop['padding_x']; ?>px!important;text-align:<?php echo esc_attr($desktop['text_align']); ?>!important}
        #gos3-overlay.gos3-render-mobile .gos3-panel{padding:<?php echo (int)$mobile['padding_y']; ?>px <?php echo (int)$mobile['padding_x']; ?>px!important;text-align:<?php echo esc_attr($mobile['text_align']); ?>!important}
        #gos3-overlay .gos3-eyebrow,#gos3-overlay .gos3-title,#gos3-overlay .gos3-detail,#gos3-overlay .gos3-price,#gos3-overlay .gos3-actions,#gos3-overlay span,#gos3-overlay p{position:static!important;top:auto!important;right:auto!important;bottom:auto!important;left:auto!important;width:auto!important;height:auto!important;float:none!important;clear:none!important;background:transparent!important;z-index:auto!important;animation:none!important}
        #gos3-overlay .gos3-title-before,#gos3-overlay .gos3-event,#gos3-overlay .gos3-title-after{display:block!important;margin:0!important;padding:0!important;white-space:pre-line!important}
        #gos3-overlay .gos3-actions{display:flex!important;flex-wrap:wrap!important;gap:8px!important}
        #gos3-overlay .gos3-actions a{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:38px!important;padding:7px 16px!important;margin:0!important;text-decoration:none!important;line-height:1.2!important}

        #gos3-overlay.gos3-render-desktop .gos3-eyebrow{font-size:<?php echo (int)$desktop['eyebrow_size']; ?>px!important;line-height:<?php echo ((int)$desktop['eyebrow_line_height'])/100; ?>!important;margin:0 0 <?php echo (int)$desktop['eyebrow_margin']; ?>px!important;color:<?php echo esc_attr($desktop['muted_color']); ?>!important;transform:translate(<?php echo (int)$desktop['eyebrow_x']; ?>px,<?php echo (int)$desktop['eyebrow_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['eyebrow_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-desktop .gos3-title{font-size:<?php echo (int)$desktop['title_size']; ?>px!important;font-weight:<?php echo (int)$desktop['title_weight']; ?>!important;line-height:<?php echo ((int)$desktop['title_line_height'])/100; ?>!important;margin:0!important;color:<?php echo esc_attr($desktop['text_color']); ?>!important;overflow-wrap:anywhere!important}
        #gos3-overlay.gos3-render-desktop .gos3-title-before{transform:translate(<?php echo (int)$desktop['title_before_x']; ?>px,<?php echo (int)$desktop['title_before_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['title_before_align']); ?>!important}
        #gos3-overlay.gos3-render-desktop .gos3-event{font-size:<?php echo (int)$desktop['event_size']; ?>px!important;font-weight:<?php echo (int)$desktop['event_weight']; ?>!important;line-height:<?php echo ((int)$desktop['event_line_height'])/100; ?>!important;transform:translate(<?php echo (int)$desktop['event_x']; ?>px,<?php echo (int)$desktop['event_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['event_align']); ?>!important}
        #gos3-overlay.gos3-render-desktop .gos3-title-after{transform:translate(<?php echo (int)$desktop['title_after_x']; ?>px,<?php echo (int)$desktop['title_after_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['title_after_align']); ?>!important}
        #gos3-overlay.gos3-render-desktop .gos3-detail{font-size:<?php echo (int)$desktop['detail_size']; ?>px!important;line-height:<?php echo ((int)$desktop['detail_line_height'])/100; ?>!important;margin:<?php echo (int)$desktop['detail_margin']; ?>px 0 0!important;color:<?php echo esc_attr($desktop['muted_color']); ?>!important;transform:translate(<?php echo (int)$desktop['detail_x']; ?>px,<?php echo (int)$desktop['detail_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['detail_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-desktop .gos3-price{font-size:<?php echo (int)$desktop['price_size']; ?>px!important;line-height:<?php echo ((int)$desktop['price_line_height'])/100; ?>!important;margin:<?php echo (int)$desktop['price_margin']; ?>px 0 0!important;transform:translate(<?php echo (int)$desktop['price_x']; ?>px,<?php echo (int)$desktop['price_y']; ?>px)!important;text-align:<?php echo esc_attr($desktop['price_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-desktop .gos3-actions{justify-content:<?php echo $desktop['actions_align']==='left'?'flex-start':($desktop['actions_align']==='right'?'flex-end':'center'); ?>!important;margin:<?php echo (int)$desktop['actions_margin']; ?>px 0 0!important;transform:translate(<?php echo (int)$desktop['actions_x']; ?>px,<?php echo (int)$desktop['actions_y']; ?>px)!important}
        #gos3-overlay.gos3-render-desktop .gos3-actions a{min-width:<?php echo (int)$desktop['button_min_width']; ?>px!important;border:1px solid <?php echo esc_attr($desktop['button_border_color']); ?>!important;border-radius:<?php echo (int)$desktop['button_radius']; ?>px!important;background:<?php echo esc_attr($desktop['button_background']); ?>!important;color:<?php echo esc_attr($desktop['button_text_color']); ?>!important;font-size:<?php echo (int)$desktop['button_size']; ?>px!important}

        #gos3-overlay.gos3-render-mobile .gos3-eyebrow{font-size:<?php echo (int)$mobile['eyebrow_size']; ?>px!important;line-height:<?php echo ((int)$mobile['eyebrow_line_height'])/100; ?>!important;margin:0 0 <?php echo (int)$mobile['eyebrow_margin']; ?>px!important;color:<?php echo esc_attr($mobile['muted_color']); ?>!important;transform:translate(<?php echo (int)$mobile['eyebrow_x']; ?>px,<?php echo (int)$mobile['eyebrow_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['eyebrow_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-mobile .gos3-title{font-size:<?php echo (int)$mobile['title_size']; ?>px!important;font-weight:<?php echo (int)$mobile['title_weight']; ?>!important;line-height:<?php echo ((int)$mobile['title_line_height'])/100; ?>!important;margin:0!important;color:<?php echo esc_attr($mobile['text_color']); ?>!important;overflow-wrap:anywhere!important}
        #gos3-overlay.gos3-render-mobile .gos3-title-before{transform:translate(<?php echo (int)$mobile['title_before_x']; ?>px,<?php echo (int)$mobile['title_before_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['title_before_align']); ?>!important}
        #gos3-overlay.gos3-render-mobile .gos3-event{font-size:<?php echo (int)$mobile['event_size']; ?>px!important;font-weight:<?php echo (int)$mobile['event_weight']; ?>!important;line-height:<?php echo ((int)$mobile['event_line_height'])/100; ?>!important;transform:translate(<?php echo (int)$mobile['event_x']; ?>px,<?php echo (int)$mobile['event_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['event_align']); ?>!important}
        #gos3-overlay.gos3-render-mobile .gos3-title-after{transform:translate(<?php echo (int)$mobile['title_after_x']; ?>px,<?php echo (int)$mobile['title_after_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['title_after_align']); ?>!important}
        #gos3-overlay.gos3-render-mobile .gos3-detail{font-size:<?php echo (int)$mobile['detail_size']; ?>px!important;line-height:<?php echo ((int)$mobile['detail_line_height'])/100; ?>!important;margin:<?php echo (int)$mobile['detail_margin']; ?>px 0 0!important;color:<?php echo esc_attr($mobile['muted_color']); ?>!important;transform:translate(<?php echo (int)$mobile['detail_x']; ?>px,<?php echo (int)$mobile['detail_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['detail_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-mobile .gos3-price{font-size:<?php echo (int)$mobile['price_size']; ?>px!important;line-height:<?php echo ((int)$mobile['price_line_height'])/100; ?>!important;margin:<?php echo (int)$mobile['price_margin']; ?>px 0 0!important;transform:translate(<?php echo (int)$mobile['price_x']; ?>px,<?php echo (int)$mobile['price_y']; ?>px)!important;text-align:<?php echo esc_attr($mobile['price_align']); ?>!important;white-space:pre-line!important}
        #gos3-overlay.gos3-render-mobile .gos3-actions{justify-content:<?php echo $mobile['actions_align']==='left'?'flex-start':($mobile['actions_align']==='right'?'flex-end':'center'); ?>!important;margin:<?php echo (int)$mobile['actions_margin']; ?>px 0 0!important;transform:translate(<?php echo (int)$mobile['actions_x']; ?>px,<?php echo (int)$mobile['actions_y']; ?>px)!important}
        #gos3-overlay.gos3-render-mobile .gos3-actions a{min-width:<?php echo (int)$mobile['button_min_width']; ?>px!important;border:1px solid <?php echo esc_attr($mobile['button_border_color']); ?>!important;border-radius:<?php echo (int)$mobile['button_radius']; ?>px!important;background:<?php echo esc_attr($mobile['button_background']); ?>!important;color:<?php echo esc_attr($mobile['button_text_color']); ?>!important;font-size:<?php echo (int)$mobile['button_size']; ?>px!important}
        </style>
        <?php
    }

    public static function front_script() {
        if (!self::should_render()) return;
        [$o, $model] = self::runtime_context();
        $forced_device = sanitize_key($_GET['gos_preview_device'] ?? '');
        $render_device = (current_user_can('manage_options') && !empty($_GET['garden_status_preview']) && in_array($forced_device,['desktop','mobile'],true)) ? $forced_device : (wp_is_mobile()?'mobile':'desktop');
        $html = self::panel_html($o,$model);
        ?>
        <script id="gos3-script">
        (function(){
          var html=<?php echo wp_json_encode($html,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
          function apply(){
            var wrapper=document.querySelector('.top-slider-wrapper');
            if(!wrapper)return false;
            var overlay=document.getElementById('gos3-overlay');
            if(!overlay){
              overlay=document.createElement('div');
              overlay.id='gos3-overlay';
              wrapper.appendChild(overlay);
            }
            overlay.className='gos3-render-<?php echo esc_js($render_device); ?>';
            overlay.innerHTML=html;
            overlay.setAttribute('aria-label',<?php echo wp_json_encode((string)($o['aria_label']??'開催状況')); ?>);
            return true;
          }
          function start(){if(apply())return;var n=0,t=setInterval(function(){n++;if(apply()||n>80)clearInterval(t)},50)}
          if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
          window.addEventListener('load',apply,{once:true});
        })();
        </script>
        <?php
    }

    public static function shortcode_status() {
        $o = self::options(false); $model = self::view_model($o);
        return self::panel_html($o, $model);
    }

    public static function shortcode_event($atts) {
        $atts = shortcode_atts(['season'=>'spring','field'=>'date'], $atts, 'garden_event');
        $o = self::options(false);
        $season = sanitize_key($atts['season']);
        if (!isset($o['events'][$season])) return '';
        $event = $o['events'][$season];
        switch (sanitize_key($atts['field'])) {
            case 'event': case 'name': return esc_html($event['label']);
            case 'date': case 'date_range': return esc_html(self::event_date_text($event, self::now()));
            case 'usual_period': return esc_html($event['usual_period']);
            case 'time': return esc_html(self::format_time($event['open_time']) . '～' . self::format_time($event['close_time']));
            case 'price': return esc_html($event['price']);
            case 'url': return esc_url($event['detail_url']);
        }
        return '';
    }

    public static function mobile_preview_shell() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        $token = sanitize_key($_GET['token'] ?? '');
        $state = sanitize_key($_GET['state'] ?? '');
        $event = sanitize_key($_GET['event'] ?? 'spring');
        if (!$token || !get_transient(self::preview_key($token))) wp_die('プレビュー情報が見つかりません。設定画面から開き直してください。');
        $src = add_query_arg([
            'garden_status_preview' => 1, 'gos_preview_token' => $token,
            'gos_force_state' => $state, 'gos_force_event' => $event,
            'gos_preview_device' => 'mobile', '_gos' => time(),
        ], home_url('/'));
        ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>スマホ実画面プレビュー</title>
        <style>body{margin:0;background:#e5e5e5;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.bar{position:sticky;top:0;z-index:2;background:#fff;padding:10px 16px;box-shadow:0 1px 5px rgba(0,0,0,.18);display:flex;gap:12px;align-items:center}.frame{width:390px;max-width:calc(100vw - 24px);height:844px;max-height:calc(100vh - 70px);margin:12px auto;background:#fff;border:10px solid #222;border-radius:28px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.28)}iframe{width:100%;height:100%;border:0;background:#fff}</style></head><body>
        <div class="bar"><button onclick="history.back()">設定画面に戻る</button><strong>スマホ実画面プレビュー</strong></div><div class="frame"><iframe src="<?php echo esc_url($src); ?>"></iframe></div></body></html><?php exit;
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options(false);
        $current = self::view_model($o);
        $auto_options = $o; $auto_options['state_mode'] = 'auto';
        $automatic = self::view_model($auto_options);
        $token = wp_generate_password(20, false, false);
        $labels = self::state_labels();
        set_transient(self::preview_key($token), $o, 30 * MINUTE_IN_SECONDS);
        $preview_src = add_query_arg([
            'garden_status_preview'=>1, 'gos_preview_token'=>$token, 'gos_preview_device'=>'desktop', '_gos'=>time()
        ], home_url('/'));
        ?>
        <div class="wrap gos3-admin" data-preview-token="<?php echo esc_attr($token); ?>" data-current-state="<?php echo esc_attr($current['state']); ?>" data-selected-layout="<?php echo esc_attr(sanitize_key($_GET['selected_layout'] ?? '')); ?>">
            <h1>開催状況</h1>
            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>
            <?php endif; ?>
            <div class="gos3-current"><strong>現在公開する状態：</strong><span><?php echo esc_html($current['state_label']); ?></span><small><?php echo esc_html($current['event']); ?></small><br><small>日時からの自動判定：<?php echo esc_html($automatic['state_label']); ?></small></div>

            <form method="post" id="gos3-form">
                <?php wp_nonce_field(self::NONCE); ?><?php wp_nonce_field(self::PREVIEW_NONCE, 'preview_nonce'); ?><input type="hidden" name="gos_v3_action" value="save">
                <input type="hidden" name="preview_state" id="gos3-preview-state" value="<?php echo esc_attr($current['state']); ?>">
                <input type="hidden" name="preview_event" id="gos3-preview-event" value="spring">
                <input type="hidden" name="preview_device" id="gos3-preview-device-input" value="desktop">
                <input type="hidden" name="preview_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="designs_json" id="gos3-designs-json" value="<?php echo esc_attr(wp_json_encode($o['designs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>">
                <input type="hidden" name="layout_templates_json" id="gos3-layout-templates-json" value="<?php echo esc_attr(wp_json_encode((object)$o['layout_templates'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>">
                <input type="hidden" name="default_layout_template" id="gos3-default-layout-template" value="<?php echo esc_attr($o['default_layout_template']); ?>">
                <input type="hidden" name="layout_save_action" id="gos3-layout-save-action" value="">
                <input type="hidden" name="layout_selected_id" id="gos3-layout-selected-id" value="">

                <div class="gos3-grid">
                    <main class="gos3-settings">
                        <section class="gos3-card">
                            <h2>基本設定</h2>
                            <div class="gos3-fields">
                                <label class="wide"><input type="checkbox" name="enabled" value="1" <?php checked($o['enabled']); ?>> 新しい開催状況表示を有効にする</label>
                                <label>公開状態の決め方<select name="state_mode" id="gos3-state-mode"><option value="manual" <?php selected($o['state_mode'],'manual'); ?>>手動で選ぶ</option><option value="auto" <?php selected($o['state_mode'],'auto'); ?>>日時から自動判定</option></select></label>
                                <label>現在公開する状態<select name="manual_state" id="gos3-manual-state"><?php foreach ($labels as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['manual_state'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                                <label>公開に使うイベント<select name="manual_event" id="gos3-manual-event"><?php foreach (self::event_keys() as $key): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['manual_event'],$key); ?>><?php echo esc_html($o['events'][$key]['label']); ?></option><?php endforeach; ?></select></label>
                                <div class="wide gos3-state-note">日時からの自動判定：<strong><?php echo esc_html($automatic['state_label']); ?></strong>　現在の公開設定：<strong><?php echo esc_html($o['state_mode']==='manual' ? $labels[$o['manual_state']] : '自動判定'); ?></strong></div>
                                <label>次回表示<select name="next_mode"><option value="auto" <?php selected($o['next_mode'],'auto'); ?>>自動</option><?php foreach (self::event_keys() as $key): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['next_mode'],$key); ?>><?php echo esc_html($o['events'][$key]['label']); ?></option><?php endforeach; ?><option value="none" <?php selected($o['next_mode'],'none'); ?>>表示しない</option></select></label>
                                <label>臨時閉苑日<input type="date" name="temporary_closed_date" value="<?php echo esc_attr($o['temporary_closed_date']); ?>"></label>
                                <label class="wide">アクセスページURL<input type="url" name="access_url" value="<?php echo esc_attr($o['access_url']); ?>"></label>
                                <label>詳細ボタン名<input type="text" name="detail_button" value="<?php echo esc_attr($o['detail_button']); ?>"></label>
                                <label>アクセスボタン名<input type="text" name="access_button" value="<?php echo esc_attr($o['access_button']); ?>"></label>
                                <label class="wide">読み上げ用ラベル<input type="text" name="aria_label" value="<?php echo esc_attr($o['aria_label']); ?>"></label>
                            </div>
                        </section>

                        <section class="gos3-card">
                            <h2>イベント</h2>
                            <div class="gos3-segment" id="gos3-event-tabs"><?php foreach (self::event_keys() as $i=>$key): ?><button type="button" data-event="<?php echo esc_attr($key); ?>" class="<?php echo $i===0?'active':''; ?>"><?php echo esc_html(['spring'=>'春','autumn'=>'秋','winter'=>'冬'][$key]); ?></button><?php endforeach; ?></div>
                            <?php foreach (self::event_keys() as $i=>$key): $e=$o['events'][$key]; ?>
                            <div class="gos3-event-panel <?php echo $i===0?'active':''; ?>" data-event-panel="<?php echo esc_attr($key); ?>">
                                <div class="gos3-fields">
                                    <label class="wide"><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($e['enabled']); ?>> このイベントを使用する</label>
                                    <label class="wide">催し名<input type="text" name="events[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($e['label']); ?>"></label>
                                    <label class="wide">例年の開催時期<input type="text" name="events[<?php echo esc_attr($key); ?>][usual_period]" value="<?php echo esc_attr($e['usual_period']); ?>" placeholder="例：4月上旬～5月上旬"></label>
                                    <label>確定開始日<input type="date" name="events[<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($e['start']); ?>"></label>
                                    <label>確定終了日<input type="date" name="events[<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($e['end']); ?>"></label>
                                    <label>開苑時刻<input type="time" name="events[<?php echo esc_attr($key); ?>][open_time]" value="<?php echo esc_attr($e['open_time']); ?>"></label>
                                    <label>終了時刻<input type="time" name="events[<?php echo esc_attr($key); ?>][close_time]" value="<?php echo esc_attr($e['close_time']); ?>"></label>
                                    <label class="wide">料金<input type="text" name="events[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($e['price']); ?>"></label>
                                    <label class="wide">詳細ページURL<input type="url" name="events[<?php echo esc_attr($key); ?>][detail_url]" value="<?php echo esc_attr($e['detail_url']); ?>"></label>
                                    <label>情報公開<select name="events[<?php echo esc_attr($key); ?>][publish_mode]"><option value="immediate" <?php selected($e['publish_mode'],'immediate'); ?>>すぐ公開</option><option value="scheduled" <?php selected($e['publish_mode'],'scheduled'); ?>>指定日時に公開</option><option value="manual" <?php selected($e['publish_mode'],'manual'); ?>>手動公開</option></select></label>
                                    <label>公開日時<input type="datetime-local" name="events[<?php echo esc_attr($key); ?>][publish_at]" value="<?php echo esc_attr($e['publish_at']); ?>"></label>
                                    <label><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][manual_published]" value="1" <?php checked($e['manual_published']); ?>> 手動公開をON</label>
                                    <label>終了後の確定日表示日数<input type="number" min="0" max="365" name="events[<?php echo esc_attr($key); ?>][post_end_days]" value="<?php echo esc_attr($e['post_end_days']); ?>"></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </section>

                        <section class="gos3-card">
                            <h2>編集する状態</h2>
                            <select id="gos3-state-select"><?php foreach ($labels as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                            <div class="gos3-state-note">ここで選んだ状態を編集・確認します。公開状態とは別に切り替えられます。</div>
                            <?php foreach ($labels as $key=>$label): $t=$o['texts'][$key]; $so=$o['state_options'][$key]; ?>
                            <div class="gos3-text-panel" data-text-panel="<?php echo esc_attr($key); ?>">
                                <h3><?php echo esc_html($label); ?></h3>
                                <div class="gos3-fields">
                                    <label class="wide">上段<textarea rows="2" name="texts[<?php echo esc_attr($key); ?>][eyebrow]"><?php echo esc_textarea($t['eyebrow']); ?></textarea></label>
                                    <label class="wide">主文<textarea rows="4" name="texts[<?php echo esc_attr($key); ?>][title]"><?php echo esc_textarea($t['title']); ?></textarea></label>
                                    <label class="wide">補足<textarea rows="3" name="texts[<?php echo esc_attr($key); ?>][detail]"><?php echo esc_textarea($t['detail']); ?></textarea></label>
                                    <label><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_price]" value="1" <?php checked($so['show_price']); ?>> 料金を表示</label>
                                    <label><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_detail_button]" value="1" <?php checked($so['show_detail_button']); ?>> 詳細ボタンを表示</label>
                                    <label><input type="checkbox" name="state_options[<?php echo esc_attr($key); ?>][show_access_button]" value="1" <?php checked($so['show_access_button']); ?>> アクセスボタンを表示</label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <p class="description">使用可能：{event}　{date_range}　{open_time}　{close_time}　{price}。入力欄の改行はそのまま表示されます。</p>
                        </section>

                        <section class="gos3-card">
                            <h2>デザイン</h2><p class="description gos3-design-scope">現在選択中の季節・状態・端末ごとに保存されます。</p>
                            <div class="gos3-segment" id="gos3-device-tabs"><button type="button" data-device="desktop" class="active">PC</button><button type="button" data-device="mobile">スマホ</button></div>
                            <div class="gos3-presets" aria-label="デザインプリセット">
                                <span>目安：</span>
                                <button type="button" class="button" data-gos3-preset="compact">コンパクト</button>
                                <button type="button" class="button button-primary" data-gos3-preset="standard">標準</button>
                                <button type="button" class="button" data-gos3-preset="large">大きめ</button>
                                <small>選択中の季節・状態・PCまたはスマホだけに適用</small>
                            </div>
                            <div class="gos3-design-groups">
                                <details open><summary>図形・サイズ</summary><div class="gos3-design-fields">
                                    <?php self::design_select('layout','図形',['horizontal'=>'横長','circle'=>'円','vertical'=>'縦長','free'=>'自由']); ?>
                                    <?php self::design_number('width','幅',120,1400); self::design_number('height','高さ',120,1000); self::design_number('radius','角丸',0,999); ?>
                                    <?php self::design_number('offset_x','左右位置',-500,500); self::design_number('offset_y','上下位置',-500,500); ?>
                                    <?php self::design_number('padding_x','左右余白',0,200); self::design_number('padding_y','上下余白',0,200); ?>
                                </div></details>
                                <details><summary>文字</summary><div class="gos3-design-fields">
                                    <?php self::design_select('text_align','全体揃え',['left'=>'左','center'=>'中央','right'=>'右']); ?>
                                    <?php foreach (['eyebrow_size'=>'上段サイズ','title_size'=>'主文サイズ','event_size'=>'イベント名サイズ','detail_size'=>'補足サイズ','price_size'=>'料金サイズ','button_size'=>'ボタン文字サイズ'] as $k=>$l) self::design_number($k,$l,8,160); ?>
                                    <?php self::design_number('title_weight','主文太さ',100,900,100); self::design_number('event_weight','イベント名太さ',100,900,100); ?>
                                    <?php self::design_number('eyebrow_line_height','上段行間（%）',80,250); self::design_number('title_line_height','主文行間（%）',80,250); self::design_number('event_line_height','イベント名行間（%）',80,250); self::design_number('detail_line_height','補足行間（%）',80,250); self::design_number('price_line_height','料金行間（%）',80,250); ?>
                                    <?php self::design_number('eyebrow_margin','上段下余白',-100,200); self::design_number('detail_margin','補足上余白',-100,200); self::design_number('price_margin','料金上余白',-100,200); self::design_number('actions_margin','ボタン上余白',-100,200); ?>
                                </div></details>
                                <details><summary>色・ボタン</summary><div class="gos3-design-fields">
                                    <?php foreach (['background_color'=>'背景色','text_color'=>'文字色','muted_color'=>'補足色','button_background'=>'ボタン背景','button_text_color'=>'ボタン文字','button_border_color'=>'ボタン枠'] as $k=>$l) self::design_color($k,$l); ?>
                                    <?php self::design_number('background_opacity','背景透明度',0,100); self::design_number('shadow_strength','影の濃さ',0,100); self::design_number('button_min_width','ボタン最小幅',0,500); self::design_number('button_radius','ボタン角丸',0,999); ?>
                                </div></details>
                                <details><summary>要素別位置・揃え</summary><div class="gos3-design-fields">
                                    <?php foreach (['eyebrow'=>'上段','title_before'=>'主文前','event'=>'イベント名','title_after'=>'主文後','detail'=>'補足','price'=>'料金','actions'=>'ボタン'] as $el=>$label): ?>
                                        <fieldset class="gos3-element-row"><legend><?php echo esc_html($label); ?></legend><?php self::design_number($el.'_x','X',-500,500); self::design_number($el.'_y','Y',-500,500); self::design_select($el.'_align','揃え',['left'=>'左','center'=>'中央','right'=>'右']); ?></fieldset>
                                    <?php endforeach; ?>
                                </div></details>
                            </div>
                        </section>

                        <section class="gos3-card gos3-layout-tools" id="gos3-layout-tools">
                            <h2>レイアウト保存・コピー</h2>
                            <p class="description">文言・開催日程は含めず、図形・サイズ・文字・色・ボタン・位置だけを保存します。保存レイアウトにはPCとスマホの両方が入ります。</p>

                            <div class="gos3-layout-save-row">
                                <label>レイアウト名<input type="text" id="gos3-layout-name" maxlength="80" placeholder="例：閉苑中・円形"></label>
                                <button type="button" class="button button-primary" id="gos3-layout-save-new">新規保存</button>
                            </div>
                            <div class="gos3-layout-manage-row">
                                <label>保存済みレイアウト<select id="gos3-layout-select"><option value="">選択してください</option></select></label>
                                <label class="gos3-inline-check"><input type="checkbox" id="gos3-layout-load-desktop" checked> PC</label>
                                <label class="gos3-inline-check"><input type="checkbox" id="gos3-layout-load-mobile" checked> スマホ</label>
                                <button type="button" class="button" id="gos3-layout-load">読み込む</button>
                                <button type="button" class="button" id="gos3-layout-overwrite">上書き</button>
                                <button type="button" class="button" id="gos3-layout-rename">名前変更</button>
                                <button type="button" class="button" id="gos3-layout-set-default">初期レイアウトに設定</button>
                                <button type="button" class="button" id="gos3-layout-load-default">初期レイアウトを読み込む</button>
                                <button type="button" class="button button-link-delete" id="gos3-layout-delete">削除</button>
                            </div>
                            <p class="gos3-layout-status" id="gos3-layout-status" aria-live="polite"></p>

                            <hr>
                            <h3>現在のレイアウトを他の設定へコピー</h3>
                            <p class="description">現在選択中の季節・状態をコピー元にします。PCはコピー元のPC、スマホはコピー元のスマホを使います。</p>
                            <div class="gos3-copy-grid">
                                <fieldset><legend>季節</legend>
                                    <label><input type="checkbox" data-copy-event="spring"> 春</label>
                                    <label><input type="checkbox" data-copy-event="autumn"> 秋</label>
                                    <label><input type="checkbox" data-copy-event="winter"> 冬</label>
                                    <button type="button" class="button button-small" data-copy-all="event">すべて選択</button>
                                </fieldset>
                                <fieldset><legend>状態</legend>
                                    <?php foreach ($labels as $key=>$label): ?><label><input type="checkbox" data-copy-state="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label><?php endforeach; ?>
                                    <button type="button" class="button button-small" data-copy-all="state">すべて選択</button>
                                </fieldset>
                                <fieldset><legend>端末</legend>
                                    <label><input type="checkbox" data-copy-device="desktop" checked> PC</label>
                                    <label><input type="checkbox" data-copy-device="mobile" checked> スマホ</label>
                                </fieldset>
                            </div>
                            <button type="button" class="button button-primary" id="gos3-copy-layout">選択先へコピー</button>
                            <p class="description">コピー結果は下の「設定を保存」で確定します。</p>
                        </section>
                        <?php submit_button('設定を保存'); ?>
                    </main>

                    <aside class="gos3-preview-card">
                        <div class="gos3-preview-head"><h2>プレビュー</h2><div class="gos3-segment" id="gos3-preview-device"><button type="button" data-preview-device="desktop" class="active">PC</button><button type="button" data-preview-device="mobile">スマホ</button></div></div>
                        <div class="gos3-preview-actions"><button type="button" class="button" id="gos3-open-pc">PC実画面</button><button type="button" class="button" id="gos3-open-mobile">スマホ実画面</button><button type="button" class="button" id="gos3-reload-preview">再読込</button></div>
                        <div class="gos3-direct-editor" id="gos3-direct-editor">
                            <div class="gos3-direct-editor-label"><strong>プレビュー上で移動</strong><small>要素を選んでドラッグ。矢印キー1px、Shift＋矢印10px。</small></div>
                            <div class="gos3-direct-elements">
                                <button type="button" class="button active" data-gos3-edit-element="eyebrow">上段</button>
                                <button type="button" class="button" data-gos3-edit-element="title_before">主文前</button>
                                <button type="button" class="button" data-gos3-edit-element="event">イベント名</button>
                                <button type="button" class="button" data-gos3-edit-element="title_after">主文後</button>
                                <button type="button" class="button" data-gos3-edit-element="detail">補足</button>
                                <button type="button" class="button" data-gos3-edit-element="price">料金</button>
                                <button type="button" class="button" data-gos3-edit-element="actions">ボタン</button>
                            </div>
                            <div class="gos3-direct-actions">
                                <button type="button" class="button" data-gos3-align="left">左揃え</button>
                                <button type="button" class="button" data-gos3-align="center">中央揃え</button>
                                <button type="button" class="button" data-gos3-align="right">右揃え</button>
                                <button type="button" class="button" id="gos3-reset-element-position">位置を0に戻す</button>
                                <label class="gos3-snap-toggle"><input type="checkbox" id="gos3-snap-center" checked> 中心線へスナップ</label>
                                <small class="gos3-snap-help">Altを押しながらドラッグすると一時的にスナップ解除</small>
                            </div>
                        </div>
                        <div class="gos3-preview-frame desktop" id="gos3-preview-frame"><iframe id="gos3-preview-iframe" name="gos3-preview-iframe-window" src="<?php echo esc_url($preview_src); ?>"></iframe></div>
                        <p id="gos3-preview-status">編集中の内容を実画面で表示します。</p>
                    </aside>
                </div>
            </form>
        </div>
        <?php
    }

    private static function design_number($key,$label,$min,$max,$step=1) {
        echo '<label>' . esc_html($label) . '<input type="number" data-design-key="' . esc_attr($key) . '" min="' . (int)$min . '" max="' . (int)$max . '" step="' . (int)$step . '"></label>';
    }
    private static function design_color($key,$label) {
        echo '<label>' . esc_html($label) . '<input type="color" data-design-key="' . esc_attr($key) . '"></label>';
    }
    private static function design_select($key,$label,$options) {
        echo '<label>' . esc_html($label) . '<select data-design-key="' . esc_attr($key) . '">';
        foreach ($options as $value=>$text) echo '<option value="' . esc_attr($value) . '">' . esc_html($text) . '</option>';
        echo '</select></label>';
    }
}

Garden_Opening_Status_V3::init();
