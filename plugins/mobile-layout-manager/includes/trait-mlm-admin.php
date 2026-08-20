<?php
/**
 * WordPress administration UI and per-post settings.
 *
 * @package MLM_Mobile_Layout_Manager
 */

if (!defined('ABSPATH')) exit;

trait MLM_Admin_Trait {
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
            plugin_dir_url(dirname(__DIR__) . '/mobile-layout-manager.php') . 'assets/admin.js',
            ['jquery'],
            self::VERSION,
            true
        );
        wp_enqueue_style(
            'mlm-admin',
            plugin_dir_url(dirname(__DIR__) . '/mobile-layout-manager.php') . 'assets/admin.css',
            [],
            self::VERSION
        );
        wp_localize_script('mlm-admin', 'MLM_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mlm_preview_draft'),
            'previewAdminUrl' => admin_url('admin.php?page=mobile-layout-preview'),
        ]);
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
