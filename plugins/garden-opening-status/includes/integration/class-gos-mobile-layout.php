<?php
/**
 * Mobile layout controls integrated from the legacy mobile-layout-manager.
 *
 * Existing mlm_options and _mlm_* post meta are kept as-is so the handoff does
 * not require data migration. Top-slider fields are owned by GOS_Slider_Admin
 * and are preserved when this screen saves the remaining mobile settings.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Mobile_Layout {
    const OPTION = 'mlm_options';
    const PAGE = 'garden-opening-status-mobile';
    const PREVIEW_PAGE = 'garden-opening-status-mobile-preview';
    const NONCE = 'gos_mobile_layout_save';
    const PREVIEW_NONCE = 'gos_mobile_layout_preview';

    private static $registered = false;

    public static function register_hooks() {
        if (self::$registered) return;
        self::$registered = true;

        add_action('wp_loaded', [__CLASS__, 'detach_legacy_hooks'], 30);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
        add_action('admin_post_gos_mobile_layout_save', [__CLASS__, 'save_options']);
        add_action('wp_ajax_gos_mobile_layout_preview_draft', [__CLASS__, 'ajax_save_preview_draft']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_post_meta']);
        add_action('wp_head', [__CLASS__, 'frontend_css'], 98);
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_preview'], 90);
        add_filter('body_class', [__CLASS__, 'body_classes']);
        add_action('send_headers', [__CLASS__, 'preview_no_cache']);
    }

    public static function detach_legacy_hooks() {
        if (!class_exists('MLM_Mobile_Layout_Manager', false)) return;
        $c = 'MLM_Mobile_Layout_Manager';
        remove_action('admin_menu', [$c, 'admin_menu']);
        remove_action('admin_init', [$c, 'save_options']);
        remove_action('admin_enqueue_scripts', [$c, 'admin_assets']);
        remove_action('wp_ajax_mlm_save_preview_draft', [$c, 'ajax_save_preview_draft']);
        remove_action('add_meta_boxes', [$c, 'add_meta_boxes']);
        remove_action('save_post', [$c, 'save_post_meta']);
        remove_action('wp_head', [$c, 'frontend_css'], 99);
        remove_action('wp_footer', [$c, 'frontend_slider_js'], 99);
        remove_action('admin_bar_menu', [$c, 'admin_bar_preview'], 90);
        remove_filter('body_class', [$c, 'body_classes']);
        remove_action('send_headers', [$c, 'preview_no_cache']);
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
        add_submenu_page(
            'garden-opening-status',
            'モバイル表示管理',
            'モバイル表示',
            'manage_options',
            self::PAGE,
            [__CLASS__, 'settings_page']
        );
        add_submenu_page(
            'garden-opening-status',
            'スマホ実画面プレビュー',
            'スマホプレビュー',
            'manage_options',
            self::PREVIEW_PAGE,
            [__CLASS__, 'preview_page']
        );
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options();
        ?>
        <div class="wrap gos-mobile-wrap">
            <h1>モバイル表示管理</h1>
            <p>トップスライダー画像は「トップスライダー」で管理します。この画面は本文・見出し・余白・ボタンなどのスマホ表示を管理します。</p>
            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="gos-mobile-form">
                <input type="hidden" name="action" value="gos_mobile_layout_save">
                <?php wp_nonce_field(self::NONCE); ?>
                <section style="max-width:960px;background:#fff;border:1px solid #dcdcde;padding:18px 20px;margin-top:18px;">
                    <label style="display:block;margin-bottom:18px;"><input type="checkbox" name="enabled" value="1" <?php checked($o['enabled'], 1); ?>> <strong>モバイル表示の上書きを有効にする</strong></label>
                    <p class="description">オフにすると、テーマ本来の表示へすぐ戻ります。</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;">
                        <?php self::range_field('breakpoint', 'スマホ切替幅', $o['breakpoint'], 480, 1200, 'px'); ?>
                        <?php self::range_field('global_side_padding', '左右余白', $o['global_side_padding'], 0, 60, 'px'); ?>
                        <?php self::range_field('global_font_scale', '本文文字倍率', $o['global_font_scale'], 70, 160, '%'); ?>
                        <?php self::range_field('global_heading_scale', '見出し文字倍率', $o['global_heading_scale'], 70, 180, '%'); ?>
                        <?php self::range_field('button_min_height', 'ボタン最小高さ', $o['button_min_height'], 32, 80, 'px'); ?>
                    </div>
                </section>
                <?php submit_button('モバイル設定を保存'); ?>
                <button type="button" class="button button-secondary" id="gos-mobile-preview-button">編集中の設定でスマホプレビュー</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . GOS_Slider_Admin::PAGE)); ?>">トップスライダー管理</a>
            </form>
        </div>
        <script>
        (function(){
          'use strict';
          var form=document.getElementById('gos-mobile-form');
          if(!form)return;

          form.querySelectorAll('[data-gos-mobile-range]').forEach(function(range){
            var number=range.parentNode.querySelector('[data-gos-mobile-number]');
            if(!number)return;
            range.addEventListener('input',function(){number.value=range.value;});
            number.addEventListener('input',function(){range.value=number.value;});
          });

          var button=document.getElementById('gos-mobile-preview-button');
          if(button)button.addEventListener('click',function(){
            var data=new FormData(form);
            data.set('action','gos_mobile_layout_preview_draft');
            data.set('nonce',<?php echo wp_json_encode(wp_create_nonce(self::PREVIEW_NONCE)); ?>);
            var win=window.open('about:blank','gosMobilePreview');
            if(win)win.document.write('<p style="font-family:sans-serif;padding:20px">プレビューを準備しています…</p>');
            fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:data})
              .then(function(r){return r.json();})
              .then(function(res){
                if(!res||!res.success){if(win)win.close();alert('プレビュー設定を保存できませんでした。');return;}
                var url=<?php echo wp_json_encode(admin_url('admin.php?page=' . self::PREVIEW_PAGE)); ?>+'&mlm_draft='+Date.now();
                if(win)win.location=url;else window.location=url;
              });
          });
        })();
        </script>
        <?php
    }

    private static function range_field($name, $label, $value, $min, $max, $unit) {
        $id = 'gos-mobile-' . sanitize_html_class(str_replace(['[', ']'], ['-', ''], $name));
        ?>
        <label for="<?php echo esc_attr($id); ?>" style="display:block;">
            <span style="display:block;font-weight:600;margin-bottom:6px;"><?php echo esc_html($label); ?></span>
            <span style="display:flex;align-items:center;gap:8px;">
                <input id="<?php echo esc_attr($id); ?>" type="range" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($value); ?>" data-gos-mobile-range style="flex:1;min-width:120px;">
                <input type="number" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" data-gos-mobile-number style="width:82px;">
                <em><?php echo esc_html($unit); ?></em>
            </span>
        </label>
        <?php
    }

    public static function save_options() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);
        $input = wp_unslash($_POST);
        self::store_general_settings($input);
        delete_user_meta(get_current_user_id(), '_mlm_preview_draft');
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    private static function store_general_settings($input) {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $saved['enabled'] = empty($input['enabled']) ? 0 : 1;
        $saved['breakpoint'] = self::bounded_int($input['breakpoint'] ?? 767, 480, 1200);
        $saved['global_font_scale'] = self::bounded_int($input['global_font_scale'] ?? 100, 70, 160);
        $saved['global_heading_scale'] = self::bounded_int($input['global_heading_scale'] ?? 100, 70, 180);
        $saved['global_side_padding'] = self::bounded_int($input['global_side_padding'] ?? 20, 0, 60);
        $saved['button_min_height'] = self::bounded_int($input['button_min_height'] ?? 44, 32, 80);
        update_option(self::OPTION, $saved, false);
    }

    public static function ajax_save_preview_draft() {
        check_ajax_referer(self::PREVIEW_NONCE, 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => '権限がありません。'], 403);
        $input = wp_unslash($_POST);
        $base = self::options();
        $base['enabled'] = empty($input['enabled']) ? 0 : 1;
        $base['breakpoint'] = self::bounded_int($input['breakpoint'] ?? 767, 480, 1200);
        $base['global_font_scale'] = self::bounded_int($input['global_font_scale'] ?? 100, 70, 160);
        $base['global_heading_scale'] = self::bounded_int($input['global_heading_scale'] ?? 100, 70, 180);
        $base['global_side_padding'] = self::bounded_int($input['global_side_padding'] ?? 20, 0, 60);
        $base['button_min_height'] = self::bounded_int($input['button_min_height'] ?? 44, 32, 80);
        update_user_meta(get_current_user_id(), '_mlm_preview_draft', $base);
        wp_send_json_success(['message' => 'プレビューへ一時反映しました。']);
    }

    public static function preview_page() {
        if (!current_user_can('manage_options')) return;
        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : home_url('/');
        if (!$url || strpos($url, home_url('/')) !== 0) $url = home_url('/');
        $preview_url = add_query_arg('mlm_preview', '1', $url);
        ?>
        <div class="wrap gos-mobile-preview-wrap">
            <h1>スマホ実画面プレビュー</h1>
            <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PREVIEW_PAGE); ?>">
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>">← 設定画面に戻る</a>
                <input type="url" name="url" value="<?php echo esc_attr($url); ?>" class="regular-text">
                <button class="button button-primary">表示</button>
                <button type="button" class="button" id="gos-mobile-rotate">縦横切替</button>
                <button type="button" class="button" id="gos-mobile-reload">再読込</button>
                <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($preview_url); ?>">実画面を別タブで開く</a>
            </form>
            <div id="gos-mobile-device-shell" data-orientation="portrait" style="width:390px;max-width:calc(100vw - 40px);height:844px;max-height:calc(100vh - 150px);margin:0 auto;background:#fff;border:10px solid #222;border-radius:28px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);transition:width .2s,height .2s;">
                <iframe id="gos-mobile-preview-frame" src="<?php echo esc_url($preview_url); ?>" title="スマホ実画面プレビュー" style="width:100%;height:100%;border:0;"></iframe>
            </div>
        </div>
        <script>
        (function(){
          'use strict';
          var shell=document.getElementById('gos-mobile-device-shell');
          var frame=document.getElementById('gos-mobile-preview-frame');
          var rotate=document.getElementById('gos-mobile-rotate');
          var reload=document.getElementById('gos-mobile-reload');
          if(rotate&&shell)rotate.addEventListener('click',function(){
            var landscape=shell.getAttribute('data-orientation')==='landscape';
            shell.setAttribute('data-orientation',landscape?'portrait':'landscape');
            shell.style.width=landscape?'390px':'844px';
            shell.style.height=landscape?'844px':'390px';
          });
          if(reload&&frame)reload.addEventListener('click',function(){
            var u=new URL(frame.src,window.location.href);
            u.searchParams.set('_mlm_reload',String(Date.now()));
            frame.src=u.toString();
          });
        })();
        </script>
        <?php
    }

    public static function preview_no_cache() {
        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) nocache_headers();
    }

    public static function add_meta_boxes() {
        foreach (['page', 'post', 'news'] as $type) {
            add_meta_box('gos-mobile-settings', 'スマホ表示設定', [__CLASS__, 'meta_box'], $type, 'side', 'default');
        }
    }

    public static function meta_box($post) {
        wp_nonce_field('gos_mobile_post_meta', 'gos_mobile_post_nonce');
        $enabled = (int)get_post_meta($post->ID, '_mlm_enabled', true);
        $font = get_post_meta($post->ID, '_mlm_font_scale', true);
        $heading = get_post_meta($post->ID, '_mlm_heading_scale', true);
        $padding = get_post_meta($post->ID, '_mlm_side_padding', true);
        $hide_thumb = (int)get_post_meta($post->ID, '_mlm_hide_thumbnail', true);
        ?>
        <p><label><input type="checkbox" name="gos_mobile_post_enabled" value="1" <?php checked($enabled, 1); ?>> このページだけ上書き</label></p>
        <p><label>本文倍率<br><input type="number" name="gos_mobile_post_font_scale" value="<?php echo esc_attr($font ?: 100); ?>" min="70" max="160">%</label></p>
        <p><label>見出し倍率<br><input type="number" name="gos_mobile_post_heading_scale" value="<?php echo esc_attr($heading ?: 100); ?>" min="70" max="180">%</label></p>
        <p><label>左右余白<br><input type="number" name="gos_mobile_post_side_padding" value="<?php echo esc_attr($padding !== '' ? $padding : 20); ?>" min="0" max="60">px</label></p>
        <p><label><input type="checkbox" name="gos_mobile_hide_thumbnail" value="1" <?php checked($hide_thumb, 1); ?>> スマホでアイキャッチを隠す</label></p>
        <p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PREVIEW_PAGE . '&url=' . rawurlencode(get_permalink($post)))); ?>">スマホ実画面プレビュー</a></p>
        <?php
    }

    public static function save_post_meta($post_id) {
        if (empty($_POST['gos_mobile_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gos_mobile_post_nonce'])), 'gos_mobile_post_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_mlm_enabled', empty($_POST['gos_mobile_post_enabled']) ? 0 : 1);
        update_post_meta($post_id, '_mlm_font_scale', self::bounded_int($_POST['gos_mobile_post_font_scale'] ?? 100, 70, 160));
        update_post_meta($post_id, '_mlm_heading_scale', self::bounded_int($_POST['gos_mobile_post_heading_scale'] ?? 100, 70, 180));
        update_post_meta($post_id, '_mlm_side_padding', self::bounded_int($_POST['gos_mobile_post_side_padding'] ?? 20, 0, 60));
        update_post_meta($post_id, '_mlm_hide_thumbnail', empty($_POST['gos_mobile_hide_thumbnail']) ? 0 : 1);
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
        if (empty($o['enabled']) && empty($_GET['mlm_preview'])) return;

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
        ?>
        <style id="gos-mobile-layout-overrides">
        @media only screen and (max-width: <?php echo (int)$breakpoint; ?>px) {
            body.mlm-enabled p,
            body.mlm-enabled .post-content,
            body.mlm-enabled .content01-text,
            body.mlm-enabled .content02-text,
            body.mlm-enabled .column-layout03-text { font-size: <?php echo (int)$font; ?>% !important; }

            body.mlm-enabled h1,
            body.mlm-enabled h2,
            body.mlm-enabled h3,
            body.mlm-enabled .headline-primary,
            body.mlm-enabled .content01-title,
            body.mlm-enabled .column-layout03-title { font-size: <?php echo (int)$heading; ?>% !important; }

            body.mlm-enabled .post-content,
            body.mlm-enabled .page-content,
            body.mlm-enabled .main > .inner { box-sizing:border-box; padding-left:<?php echo (int)$padding; ?>px !important; padding-right:<?php echo (int)$padding; ?>px !important; }

            body.mlm-enabled .button a,
            body.mlm-enabled button,
            body.mlm-enabled input[type="submit"] { min-height:<?php echo (int)$button; ?>px; }

            <?php if (is_singular() && (int)get_post_meta(get_queried_object_id(), '_mlm_hide_thumbnail', true)): ?>
            .post-header-image,.post-thumbnail,.single-thumbnail { display:none!important; }
            <?php endif; ?>
        }
        </style>
        <?php
    }

    public static function admin_bar_preview($bar) {
        if (!is_admin_bar_showing() || !current_user_can('edit_posts')) return;
        $url = is_admin() ? home_url('/') : (is_singular() ? get_permalink() : home_url('/'));
        $bar->add_node([
            'id' => 'gos-mobile-preview',
            'title' => 'スマホプレビュー',
            'href' => admin_url('admin.php?page=' . self::PREVIEW_PAGE . '&url=' . rawurlencode($url)),
            'meta' => ['target' => '_blank'],
        ]);
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
