<?php
/**
 * Plugin Name: モバイル表示管理
 * Description: 既存テーマを変更せず、トップスライダーと投稿・固定ページのスマホ表示を上書きします。
 * Version: 1.6.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class MLM_Mobile_Layout_Manager {
    const OPTION = 'mlm_options';
    const NONCE  = 'mlm_save_options';
    const VERSION = '1.6.1';

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

    public static function preview_no_cache() {
        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) nocache_headers();
    }

    private static function defaults() {
        return [
            'enabled' => 0,
            'top_enabled' => 0,
            'breakpoint' => 767,
            'global_font_scale' => 100,
            'global_heading_scale' => 100,
            'global_side_padding' => 20,
            'button_min_height' => 44,
            'slider' => [
                1 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
                2 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
                3 => ['image_id' => 0, 'position_x' => 50, 'position_y' => 50],
            ],
        ];
    }

    private static function options() {
        $saved = get_option(self::OPTION, []);
        $o = array_replace_recursive(self::defaults(), is_array($saved) ? $saved : []);
        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) {
            $draft = get_user_meta(get_current_user_id(), '_mlm_preview_draft', true);
            if (is_array($draft)) $o = array_replace_recursive($o, $draft);
        }
        return $o;
    }

    public static function admin_menu() {
        add_menu_page(
            'モバイル表示管理',
            'モバイル表示',
            'manage_options',
            'mobile-layout-manager',
            [__CLASS__, 'settings_page'],
            'dashicons-smartphone',
            26
        );
        add_submenu_page(
            'mobile-layout-manager',
            'スマホ実画面プレビュー',
            '実画面プレビュー',
            'manage_options',
            'mobile-layout-preview',
            [__CLASS__, 'preview_page']
        );
    }

    public static function admin_assets($hook) {
        if (strpos($hook, 'mobile-layout') === false && !in_array($hook, ['post.php', 'post-new.php'], true)) return;
        wp_enqueue_media();
        wp_enqueue_script(
            'mlm-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery'],
            self::VERSION,
            true
        );
        wp_enqueue_style(
            'mlm-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            self::VERSION
        );
        wp_localize_script('mlm-admin', 'MLM_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mlm_preview_draft'),
            'previewAdminUrl' => admin_url('admin.php?page=mobile-layout-preview'),
        ]);
    }

    public static function save_options() {
        if (!is_admin() || empty($_POST['mlm_action'])) return;
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);

        $in = wp_unslash($_POST);
        $out = self::defaults();
        $out['enabled'] = empty($in['enabled']) ? 0 : 1;
        $out['top_enabled'] = empty($in['top_enabled']) ? 0 : 1;
        $out['breakpoint'] = self::bounded_int($in['breakpoint'] ?? 767, 480, 1200);
        $out['global_font_scale'] = self::bounded_int($in['global_font_scale'] ?? 100, 70, 160);
        $out['global_heading_scale'] = self::bounded_int($in['global_heading_scale'] ?? 100, 70, 180);
        $out['global_side_padding'] = self::bounded_int($in['global_side_padding'] ?? 20, 0, 60);
        $out['button_min_height'] = self::bounded_int($in['button_min_height'] ?? 44, 32, 80);

        for ($i = 1; $i <= 3; $i++) {
            $src = $in['slider'][$i] ?? [];
            $out['slider'][$i] = [
                'image_id' => absint($src['image_id'] ?? 0),
                'position_x' => self::bounded_int($src['position_x'] ?? 50, 0, 100),
                'position_y' => self::bounded_int($src['position_y'] ?? 50, 0, 100),
            ];
        }

        update_option(self::OPTION, $out, false);
        delete_user_meta(get_current_user_id(), '_mlm_preview_draft');
        wp_safe_redirect(add_query_arg(['page' => 'mobile-layout-manager', 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    public static function ajax_save_preview_draft() {
        check_ajax_referer('mlm_preview_draft', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => '権限がありません。'], 403);
        $in = wp_unslash($_POST);
        $draft = self::defaults();
        $draft['enabled'] = empty($in['enabled']) ? 0 : 1;
        $draft['top_enabled'] = empty($in['top_enabled']) ? 0 : 1;
        $draft['breakpoint'] = self::bounded_int($in['breakpoint'] ?? 767, 480, 1200);
        $draft['global_font_scale'] = self::bounded_int($in['global_font_scale'] ?? 100, 70, 160);
        $draft['global_heading_scale'] = self::bounded_int($in['global_heading_scale'] ?? 100, 70, 180);
        $draft['global_side_padding'] = self::bounded_int($in['global_side_padding'] ?? 20, 0, 60);
        $draft['button_min_height'] = self::bounded_int($in['button_min_height'] ?? 44, 32, 80);
        for ($i=1;$i<=3;$i++) {
            $src=$in['slider'][$i]??[];
            $draft['slider'][$i]=[
                'image_id'=>absint($src['image_id']??0),
                'position_x'=>self::bounded_int($src['position_x']??50,0,100),
                'position_y'=>self::bounded_int($src['position_y']??50,0,100),
            ];
        }
        update_user_meta(get_current_user_id(), '_mlm_preview_draft', $draft);
        wp_send_json_success(['message'=>'プレビューへ一時反映しました。']);
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }

    private static function original_slider_data() {
        $dp = get_option('dp_options', []);
        $rows = [];
        $render_index = 0;
        for ($slot = 1; $slot <= 3; $slot++) {
            $original_id = absint($dp['slider_image' . $slot] ?? 0);
            if ($original_id) $render_index++;
            $rows[$slot] = [
                'original_id' => $original_id,
                'render_index' => $original_id ? $render_index : 0,
                'original_url' => $original_id ? wp_get_attachment_image_url($original_id, 'medium') : '',
            ];
        }
        return $rows;
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options();
        $original = self::original_slider_data();
        ?>
        <div class="wrap mlm-wrap">
            <h1>モバイル表示管理</h1>
            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>保存しました。</p></div>
            <?php endif; ?>

            <form method="post" id="mlm-settings-form">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="mlm_action" value="save">

                <section class="mlm-card">
                    <h2>基本設定</h2>
                    <label class="mlm-switch-row">
                        <input type="checkbox" name="enabled" value="1" <?php checked($o['enabled'], 1); ?>>
                        <strong>モバイル表示の上書きを有効にする</strong>
                    </label>
                    <p class="description">オフにすると、テーマ本来の表示へすぐ戻ります。</p>

                    <div class="mlm-grid-2">
                        <?php self::range_field('breakpoint', 'スマホ切替幅', $o['breakpoint'], 480, 1200, 'px'); ?>
                        <?php self::range_field('global_side_padding', '左右余白', $o['global_side_padding'], 0, 60, 'px'); ?>
                        <?php self::range_field('global_font_scale', '本文文字倍率', $o['global_font_scale'], 70, 160, '%'); ?>
                        <?php self::range_field('global_heading_scale', '見出し文字倍率', $o['global_heading_scale'], 70, 180, '%'); ?>
                        <?php self::range_field('button_min_height', 'ボタン最小高さ', $o['button_min_height'], 32, 80, 'px'); ?>
                    </div>
                </section>

                <section class="mlm-card">
                    <div class="mlm-section-head">
                        <div>
                            <h2>トップスライダー：スマホ専用画像</h2>
                            <p>PC画像は現在のテーマ設定をそのまま使用し、スマホ幅だけ下の画像へ差し替えます。この機能は上の全体ON/OFFとは独立して動作します。</p>
                        </div>
                        <label><input type="checkbox" name="top_enabled" value="1" <?php checked($o['top_enabled'], 1); ?>> この機能を有効にする</label>
                    </div>

                    <div class="mlm-slider-list">
                    <?php for ($i = 1; $i <= 3; $i++):
                        $mobile_id = absint($o['slider'][$i]['image_id']);
                        $mobile_url = $mobile_id ? wp_get_attachment_image_url($mobile_id, 'medium') : '';
                    ?>
                        <div class="mlm-slider-card">
                            <h3>スライダー <?php echo $i; ?></h3>
                            <div class="mlm-images-row">
                                <div>
                                    <span class="mlm-label">現在のPC画像</span>
                                    <div class="mlm-thumb mlm-original-thumb">
                                        <?php if ($original[$i]['original_url']): ?>
                                            <img src="<?php echo esc_url($original[$i]['original_url']); ?>" alt="">
                                        <?php else: ?>
                                            <span>未設定</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="mlm-label">スマホ専用画像</span>
                                    <div class="mlm-thumb mlm-media-preview" data-placeholder="未設定">
                                        <?php if ($mobile_url): ?><img src="<?php echo esc_url($mobile_url); ?>" alt="" data-attachment-id="<?php echo esc_attr($mobile_id); ?>"><?php else: ?><span>未設定</span><?php endif; ?>
                                    </div>
                                    <small class="mlm-selected-id"><?php echo $mobile_id ? '画像ID: ' . esc_html($mobile_id) : '画像未設定'; ?></small>
                                    <input type="hidden" class="mlm-media-id" name="slider[<?php echo $i; ?>][image_id]" value="<?php echo esc_attr($mobile_id); ?>">
                                    <p>
                                        <button type="button" class="button mlm-media-select">画像を選択</button>
                                        <button type="button" class="button-link-delete mlm-media-clear">解除</button>
                                    </p>
                                </div>
                            </div>
                            <?php if (!$original[$i]['original_id']): ?>
                                <p class="notice notice-warning inline">テーマ側でこのスライダー画像が未設定のため、現在は表示対象になりません。</p>
                            <?php endif; ?>
                            <div class="mlm-grid-2">
                                <?php self::range_field("slider[$i][position_x]", '表示位置・左右', $o['slider'][$i]['position_x'], 0, 100, '%'); ?>
                                <?php self::range_field("slider[$i][position_y]", '表示位置・上下', $o['slider'][$i]['position_y'], 0, 100, '%'); ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                    </div>
                </section>

                <?php submit_button('設定を保存'); ?>
                <button type="button" class="button button-secondary" data-mlm-open-preview>スマホ実画面プレビュー</button>
            </form>
        </div>
        <?php
    }

    private static function range_field($name, $label, $value, $min, $max, $unit) {
        $id = 'mlm-' . sanitize_html_class(str_replace(['[', ']'], ['-', ''], $name));
        ?>
        <label class="mlm-range-field" for="<?php echo esc_attr($id); ?>">
            <span><?php echo esc_html($label); ?></span>
            <div>
                <input id="<?php echo esc_attr($id); ?>" type="range" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($value); ?>" data-mlm-range>
                <input type="number" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" data-mlm-number>
                <em><?php echo esc_html($unit); ?></em>
            </div>
        </label>
        <?php
    }

    public static function preview_page() {
        if (!current_user_can('manage_options')) return;
        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : home_url('/');
        if (!$url || strpos($url, home_url('/')) !== 0) $url = home_url('/');
        $preview_url = add_query_arg('mlm_preview', '1', $url);
        ?>
        <div class="wrap mlm-preview-wrap">
            <h1>スマホ実画面プレビュー</h1>
            <form method="get" class="mlm-preview-toolbar">
                <input type="hidden" name="page" value="mobile-layout-preview">
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mobile-layout-manager')); ?>">← 設定画面に戻る</a>
                <input type="url" name="url" value="<?php echo esc_attr($url); ?>" class="regular-text">
                <button class="button button-primary">表示</button>
                <button type="button" class="button" data-mlm-rotate>縦横切替</button>
                <button type="button" class="button" data-mlm-reload>再読込</button>
                <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(admin_url('admin.php?page=mobile-layout-preview&url=' . rawurlencode($url))); ?>">このスマホ枠を別タブで開く</a>
            </form>
            <div class="mlm-device-shell" data-orientation="portrait">
                <iframe id="mlm-preview-frame" src="<?php echo esc_url($preview_url); ?>" title="スマホ実画面プレビュー"></iframe>
            </div>
        </div>
        <?php
    }

    public static function add_meta_boxes() {
        foreach (['page', 'post', 'news'] as $type) {
            add_meta_box('mlm-mobile-settings', 'スマホ表示設定', [__CLASS__, 'meta_box'], $type, 'side', 'default');
        }
    }

    public static function meta_box($post) {
        wp_nonce_field('mlm_post_meta', 'mlm_post_nonce');
        $enabled = (int)get_post_meta($post->ID, '_mlm_enabled', true);
        $font = get_post_meta($post->ID, '_mlm_font_scale', true);
        $heading = get_post_meta($post->ID, '_mlm_heading_scale', true);
        $padding = get_post_meta($post->ID, '_mlm_side_padding', true);
        $hide_thumb = (int)get_post_meta($post->ID, '_mlm_hide_thumbnail', true);
        ?>
        <p><label><input type="checkbox" name="mlm_post_enabled" value="1" <?php checked($enabled, 1); ?>> このページだけ上書き</label></p>
        <p><label>本文倍率<br><input type="number" name="mlm_post_font_scale" value="<?php echo esc_attr($font ?: 100); ?>" min="70" max="160">%</label></p>
        <p><label>見出し倍率<br><input type="number" name="mlm_post_heading_scale" value="<?php echo esc_attr($heading ?: 100); ?>" min="70" max="180">%</label></p>
        <p><label>左右余白<br><input type="number" name="mlm_post_side_padding" value="<?php echo esc_attr($padding !== '' ? $padding : 20); ?>" min="0" max="60">px</label></p>
        <p><label><input type="checkbox" name="mlm_hide_thumbnail" value="1" <?php checked($hide_thumb, 1); ?>> スマホでアイキャッチを隠す</label></p>
        <p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(admin_url('admin.php?page=mobile-layout-preview&url=' . rawurlencode(get_permalink($post)))); ?>">スマホ実画面プレビュー</a></p>
        <?php
    }

    public static function save_post_meta($post_id) {
        if (empty($_POST['mlm_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mlm_post_nonce'])), 'mlm_post_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_mlm_enabled', empty($_POST['mlm_post_enabled']) ? 0 : 1);
        update_post_meta($post_id, '_mlm_font_scale', self::bounded_int($_POST['mlm_post_font_scale'] ?? 100, 70, 160));
        update_post_meta($post_id, '_mlm_heading_scale', self::bounded_int($_POST['mlm_post_heading_scale'] ?? 100, 70, 180));
        update_post_meta($post_id, '_mlm_side_padding', self::bounded_int($_POST['mlm_post_side_padding'] ?? 20, 0, 60));
        update_post_meta($post_id, '_mlm_hide_thumbnail', empty($_POST['mlm_hide_thumbnail']) ? 0 : 1);
    }

    public static function body_classes($classes) {
        $o = self::options();
        if ($o['enabled']) $classes[] = 'mlm-enabled';
        if ($o['top_enabled']) $classes[] = 'mlm-top-slider-enabled';
        if (is_singular() && (int)get_post_meta(get_queried_object_id(), '_mlm_enabled', true)) $classes[] = 'mlm-page-override';
        return $classes;
    }

    public static function frontend_css() {
        $o = self::options();
        if (empty($o['enabled']) && empty($o['top_enabled']) && empty($_GET['mlm_preview'])) return;

        $breakpoint = (int)$o['breakpoint'];
        $font = (int)$o['global_font_scale'];
        $heading = (int)$o['global_heading_scale'];
        $padding = (int)$o['global_side_padding'];
        $button = (int)$o['button_min_height'];

        if (is_singular() && (int)get_post_meta(get_queried_object_id(), '_mlm_enabled', true)) {
            $font = self::bounded_int(get_post_meta(get_queried_object_id(), '_mlm_font_scale', true) ?: 100, 70, 160);
            $heading = self::bounded_int(get_post_meta(get_queried_object_id(), '_mlm_heading_scale', true) ?: 100, 70, 180);
            $padding = self::bounded_int(get_post_meta(get_queried_object_id(), '_mlm_side_padding', true), 0, 60);
        }

        $slider_css = '';
        if (!empty($o['top_enabled']) && is_front_page()) {
            $original = self::original_slider_data();
            foreach ($o['slider'] as $slot => $row) {
                $id = absint($row['image_id']);
                $nth = (int)($original[$slot]['render_index'] ?? 0);
                if (!$id || !$nth) continue;
                $url = wp_get_attachment_image_url($id, 'full');
                if (!$url) continue;
                $x = self::bounded_int($row['position_x'], 0, 100);
                $y = self::bounded_int($row['position_y'], 0, 100);
                $slider_css .= '[id="top-slider-item' . (int)$slot . '"]>span,[id="top-slider-item' . (int)$slot . '"]>a>span{background-image:url("' . esc_url($url) . '")!important;background-position:' . (int)$x . '% ' . (int)$y . '%!important;background-size:cover!important;background-repeat:no-repeat!important;}' . "\n";
            }
        }
        ?>
        <style id="mlm-mobile-overrides">
        @media only screen and (max-width: <?php echo $breakpoint; ?>px) {
            body.mlm-enabled p,
            body.mlm-enabled .post-content,
            body.mlm-enabled .content01-text,
            body.mlm-enabled .content02-text,
            body.mlm-enabled .column-layout03-text { font-size: <?php echo $font; ?>% !important; }

            body.mlm-enabled h1,
            body.mlm-enabled h2,
            body.mlm-enabled h3,
            body.mlm-enabled .headline-primary,
            body.mlm-enabled .content01-title,
            body.mlm-enabled .column-layout03-title { font-size: <?php echo $heading; ?>% !important; }

            body.mlm-enabled .post-content,
            body.mlm-enabled .page-content,
            body.mlm-enabled .main > .inner { box-sizing: border-box; padding-left: <?php echo $padding; ?>px !important; padding-right: <?php echo $padding; ?>px !important; }

            body.mlm-enabled .button a,
            body.mlm-enabled button,
            body.mlm-enabled input[type="submit"] { min-height: <?php echo $button; ?>px; }

            <?php echo $slider_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if (is_singular() && (int)get_post_meta(get_queried_object_id(), '_mlm_hide_thumbnail', true)): ?>
            .post-header-image,.post-thumbnail,.single-thumbnail { display:none!important; }
            <?php endif; ?>
        }
        </style>
        <?php
    }


    public static function frontend_slider_js() {
        $o = self::options();
        if (empty($o['top_enabled']) || !is_front_page()) return;

        $items = [];
        foreach ($o['slider'] as $slot => $row) {
            $id = absint($row['image_id']);
            if (!$id) continue;
            $url = wp_get_attachment_image_url($id, 'full');
            if (!$url) continue;
            $items[(string)(int)$slot] = [
                'id'  => $id,
                'url' => esc_url_raw($url),
                'x'   => self::bounded_int($row['position_x'], 0, 100),
                'y'   => self::bounded_int($row['position_y'], 0, 100),
            ];
        }
        if (!$items) return;

        $breakpoint = (int)$o['breakpoint'];
        ?>
        <style id="mlm-mobile-slider-late-css">
        @media only screen and (max-width: <?php echo $breakpoint; ?>px) {
        <?php foreach ($items as $slot => $row): ?>
          #top-slider #top-slider-item<?php echo (int)$slot; ?> > span,
          #top-slider #top-slider-item<?php echo (int)$slot; ?> > a > span {
            background-image: url("<?php echo esc_url($row['url']); ?>") !important;
            background-position: <?php echo (int)$row['x']; ?>% <?php echo (int)$row['y']; ?>% !important;
            background-size: cover !important;
            background-repeat: no-repeat !important;
          }
        <?php endforeach; ?>
        }
        </style>
        <script id="mlm-mobile-slider-images">
        (function(){
          'use strict';
          var items=<?php echo wp_json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          var bp=<?php echo $breakpoint; ?>;
          var mq=window.matchMedia('(max-width:'+bp+'px)');
          var applying=false;

          function targetsFor(slot){
            var root=document.getElementById('top-slider');
            if(!root)return [];
            var item=root.querySelector('#top-slider-item'+slot);
            if(!item)return [];
            var list=[];
            if(item.matches('span'))list.push(item);
            item.querySelectorAll(':scope > span, :scope > a > span').forEach(function(el){list.push(el);});
            return list;
          }

          function paint(el,row){
            if(!el)return;
            var url='url("'+String(row.url).replace(/"/g,'\\"')+'")';
            el.style.setProperty('background-image',url,'important');
            el.style.setProperty('background-position',row.x+'% '+row.y+'%','important');
            el.style.setProperty('background-size','cover','important');
            el.style.setProperty('background-repeat','no-repeat','important');
            el.setAttribute('data-mlm-mobile-image-id',String(row.id));
          }

          function apply(){
            if(applying||!mq.matches)return;
            applying=true;
            Object.keys(items).forEach(function(slot){
              targetsFor(slot).forEach(function(el){paint(el,items[slot]);});
            });
            applying=false;
          }

          function boot(){
            apply();
            var slider=document.getElementById('top-slider');
            if(slider){
              new MutationObserver(function(){requestAnimationFrame(apply);}).observe(slider,{subtree:true,childList:true,attributes:true,attributeFilter:['style','class']});
              if(window.jQuery){
                window.jQuery(slider).on('init reInit setPosition beforeChange afterChange',function(){requestAnimationFrame(apply);});
              }
            }
            [0,50,150,300,600,1000,1800].forEach(function(ms){setTimeout(apply,ms);});
          }

          if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
          else boot();
          window.addEventListener('load',apply,{once:true});
          if(mq.addEventListener)mq.addEventListener('change',apply);else if(mq.addListener)mq.addListener(apply);
        })();
        </script>
        <?php
    }

    public static function admin_bar_preview($bar) {
        if (!is_admin_bar_showing() || !current_user_can('edit_posts')) return;
        $url = is_admin() ? home_url('/') : (is_singular() ? get_permalink() : home_url('/'));
        $bar->add_node([
            'id' => 'mlm-mobile-preview',
            'title' => 'スマホプレビュー',
            'href' => admin_url('admin.php?page=mobile-layout-preview&url=' . rawurlencode($url)),
            'meta' => ['target' => '_blank'],
        ]);
    }
}

MLM_Mobile_Layout_Manager::init();
