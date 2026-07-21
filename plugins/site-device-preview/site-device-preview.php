<?php
/**
 * Plugin Name: サイト端末プレビュー
 * Description: WordPress管理画面からPC・タブレット・スマホの実画面表示を確認します。テーマファイルは変更しません。
 * Version: 1.2.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Site_Device_Preview {
    const PAGE = 'site-device-preview';
    const NONCE_ACTION = 'site_device_preview';

    public static function init() {
        add_action('init', [__CLASS__, 'apply_preview_device'], 0);
        add_filter('preview_post_link', [__CLASS__, 'filter_preview_post_link'], 99, 2);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar'], 90);
        add_action('post_submitbox_misc_actions', [__CLASS__, 'submitbox_preview_buttons']);
        add_action('admin_footer-post.php', [__CLASS__, 'submitbox_script']);
        add_action('admin_footer-post-new.php', [__CLASS__, 'submitbox_script']);
    }

    public static function filter_preview_post_link($preview_link, $post) {
        if (empty($_COOKIE['sdp_preview_device'])) return $preview_link;

        $device = sanitize_key(wp_unslash($_COOKIE['sdp_preview_device']));
        if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) return $preview_link;

        return add_query_arg([
            'sdp_preview' => $device,
            '_sdpnonce'   => wp_create_nonce(self::NONCE_ACTION),
            'sdp_time'    => time(),
        ], $preview_link);
    }

    public static function apply_preview_device() {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) return;

        // 標準プレビューは、いったん管理画面へPOSTしてから公開側へ遷移する。
        // 管理画面側でCookieを消費すると、遷移先で端末指定が失われるため、
        // 端末判定は公開側のリクエストでのみ行う。
        if (is_admin()) return;

        $device = '';
        if (!empty($_GET['sdp_preview']) && !empty($_GET['_sdpnonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_sdpnonce'])), self::NONCE_ACTION)) {
            $device = sanitize_key(wp_unslash($_GET['sdp_preview']));
        } elseif (!empty($_COOKIE['sdp_preview_device'])) {
            $device = sanitize_key(wp_unslash($_COOKIE['sdp_preview_device']));
            // 投稿編集画面からの一回限りの指定。次の通常閲覧には持ち越さない。
            setcookie('sdp_preview_device', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        }

        if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) return;
        if ($device === 'mobile') {
            // このテーマは独自の is_mobile() でHTTP_USER_AGENTを判定するため、
            // iframe幅だけでなくサーバー側のスマホ分岐も実機相当にする。
            $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
        } elseif ($device === 'tablet') {
            $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
        } else {
            $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
        }

        add_filter('show_admin_bar', '__return_false', 999);
        add_action('wp_head', [__CLASS__, 'preview_head'], 0);
    }

    public static function preview_head() {
        echo "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
        echo "<style>html{margin-top:0!important}#wpadminbar{display:none!important}body.admin-bar{margin-top:0!important}</style>\n";
    }

    public static function admin_menu() {
        add_menu_page(
            'サイト端末プレビュー',
            '端末プレビュー',
            'edit_theme_options',
            self::PAGE,
            [__CLASS__, 'render'],
            'dashicons-smartphone',
            26
        );
    }

    public static function admin_bar($bar) {
        if (!current_user_can('edit_theme_options')) return;
        $bar->add_node([
            'id' => 'site-device-preview',
            'title' => '端末プレビュー',
            'href' => admin_url('admin.php?page=' . self::PAGE),
        ]);
    }

    public static function submitbox_preview_buttons() {
        global $post;
        if (!$post || !current_user_can('edit_post', $post->ID)) return;
        if (!post_type_supports($post->post_type, 'editor') && !post_type_supports($post->post_type, 'title')) return;
        ?>
        <div class="misc-pub-section sdp-submitbox-preview">
            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            <strong>端末プレビュー</strong>
            <div class="sdp-submitbox-buttons">
                <button type="button" class="button sdp-native-preview" data-device="desktop">PC</button>
                <button type="button" class="button sdp-native-preview" data-device="mobile">スマホ</button>
            </div>
            <p class="sdp-submitbox-note">編集中の内容を確認します。スマホは狭い実画面幅の別ウィンドウで開きます。</p>
        </div>
        <?php
    }

    public static function submitbox_script() {
        global $post;
        if (!$post || !current_user_can('edit_post', $post->ID)) return;
        ?>
        <style>
            .sdp-submitbox-preview .dashicons{color:#646970;margin-right:4px;vertical-align:text-bottom}
            .sdp-submitbox-buttons{display:flex;gap:6px;margin-top:9px}
            .sdp-submitbox-buttons .button{flex:1;text-align:center}
            .sdp-submitbox-note{margin:7px 0 0;color:#646970;font-size:11px;line-height:1.5}
        </style>
        <script>
        (() => {
            const buttons = document.querySelectorAll('.sdp-native-preview');
            if (!buttons.length) return;

            function setDeviceCookie(device) {
                const secure = location.protocol === 'https:' ? '; Secure' : '';
                document.cookie = 'sdp_preview_device=' + encodeURIComponent(device) + '; path=/; max-age=120; SameSite=Lax' + secure;
            }

            function addDeviceToUrl(rawUrl, device) {
                try {
                    const url = new URL(rawUrl, window.location.href);
                    url.searchParams.set('sdp_preview', device);
                    url.searchParams.set('_sdpnonce', <?php echo wp_json_encode(wp_create_nonce(self::NONCE_ACTION)); ?>);
                    url.searchParams.set('sdp_time', Date.now().toString());
                    return url.toString();
                } catch (_) {
                    return rawUrl;
                }
            }

            buttons.forEach(button => button.addEventListener('click', () => {
                const nativePreview = document.getElementById('post-preview');
                if (!nativePreview) {
                    alert('WordPress標準のプレビューボタンが見つかりません。');
                    return;
                }

                const device = button.dataset.device || 'desktop';
                setDeviceCookie(device);

                if (nativePreview.href) {
                    nativePreview.href = addDeviceToUrl(nativePreview.href, device);
                }

                // Classic Editorの標準プレビューは wp-preview-{投稿ID} という名前の
                // ウィンドウへPOSTする。スマホ時はその同名ウィンドウを先に狭いサイズで開き、
                // 保存前の内容をそのまま実幅で表示させる。
                if (device === 'mobile' || device === 'tablet') {
                    const targetName = 'wp-preview-<?php echo (int) $post->ID; ?>';
                    const popupWidth  = device === 'mobile' ? 430 : 820;
                    const popupHeight = device === 'mobile' ? 900 : 1050;
                    const left = Math.max(0, Math.round((screen.availWidth - popupWidth) / 2));
                    const top  = Math.max(0, Math.round((screen.availHeight - popupHeight) / 2));
                    const features = [
                        'popup=yes',
                        'resizable=yes',
                        'scrollbars=yes',
                        'width=' + popupWidth,
                        'height=' + popupHeight,
                        'left=' + left,
                        'top=' + top
                    ].join(',');
                    const previewWindow = window.open('about:blank', targetName, features);
                    if (!previewWindow) {
                        alert('プレビューウィンドウを開けませんでした。ブラウザのポップアップを許可してください。');
                        return;
                    }
                    try {
                        previewWindow.document.title = device === 'mobile' ? 'スマホプレビュー' : 'タブレットプレビュー';
                        previewWindow.document.body.innerHTML = '<p style="font-family:sans-serif;padding:20px">プレビューを読み込んでいます…</p>';
                        previewWindow.focus();
                    } catch (_) {}
                    nativePreview.setAttribute('target', targetName);
                }

                nativePreview.click();
            }));
        })();
        </script>
        <?php
    }

    private static function internal_url($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return home_url('/');

        if (strpos($raw, '/') === 0) {
            return home_url($raw);
        }

        $url = esc_url_raw($raw);
        if (!$url) return home_url('/');

        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        return ($home_host && $url_host && strtolower($home_host) === strtolower($url_host)) ? $url : home_url('/');
    }

    private static function preview_url($url, $device) {
        return add_query_arg([
            'sdp_preview' => $device,
            '_sdpnonce'   => wp_create_nonce(self::NONCE_ACTION),
            'sdp_time'    => time(),
        ], $url);
    }

    public static function render() {
        if (!current_user_can('edit_theme_options')) return;

        $requested = isset($_GET['preview_url']) ? wp_unslash($_GET['preview_url']) : home_url('/');
        $target = self::internal_url($requested);
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $pages = get_pages([
            'sort_column' => 'menu_order,post_title',
            'post_status' => 'publish',
        ]);
        ?>
        <div class="wrap sdp-wrap">
            <h1>サイト端末プレビュー</h1>
            <p>公開サイトを実際のレスポンシブ幅で表示します。スマホでは、テーマ独自のスマホ判定も有効になります。</p>

            <div class="sdp-toolbar">
                <label class="sdp-url-label" for="sdp-url">確認するページ</label>
                <select id="sdp-page-select">
                    <option value="<?php echo esc_attr(home_url('/')); ?>">トップページ</option>
                    <?php foreach ($pages as $page): $url = get_permalink($page); ?>
                        <option value="<?php echo esc_attr($url); ?>" <?php selected(untrailingslashit($target), untrailingslashit($url)); ?>><?php echo esc_html($page->post_title ?: '(無題)'); ?></option>
                    <?php endforeach; ?>
                    <option value="custom">URLを直接入力</option>
                </select>
                <input type="url" id="sdp-url" value="<?php echo esc_attr($target); ?>" aria-label="プレビューURL">
                <button type="button" class="button button-primary" id="sdp-load">表示</button>
                <button type="button" class="button" id="sdp-refresh">再読込</button>
                <a class="button" id="sdp-new-tab" href="#" target="_blank" rel="noopener">別タブで開く</a>
            </div>

            <div class="sdp-device-row">
                <button type="button" class="button sdp-device" data-device="desktop" data-width="1440" data-height="900">PC</button>
                <button type="button" class="button sdp-device" data-device="tablet" data-width="768" data-height="1024">タブレット</button>
                <button type="button" class="button button-primary sdp-device" data-device="mobile" data-width="390" data-height="844">スマホ</button>
                <button type="button" class="button" id="sdp-rotate">縦横を入れ替え</button>
                <span id="sdp-size">390 × 844</span>
            </div>

            <div class="sdp-stage" id="sdp-stage">
                <div class="sdp-frame-shell" id="sdp-shell">
                    <div class="sdp-frame-top"><span></span></div>
                    <iframe id="sdp-frame" title="サイト端末プレビュー" loading="eager"></iframe>
                </div>
            </div>
        </div>

        <style>
            .sdp-wrap{max-width:calc(100vw - 40px)}
            .sdp-toolbar,.sdp-device-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:14px 0}
            .sdp-url-label{font-weight:600}
            #sdp-page-select{min-width:220px}
            #sdp-url{width:min(520px,55vw)}
            .sdp-device-row #sdp-size{font-variant-numeric:tabular-nums;color:#50575e;margin-left:4px}
            .sdp-stage{background:#dcdcde;border:1px solid #c3c4c7;border-radius:8px;padding:24px;overflow:auto;min-height:600px;box-sizing:border-box}
            .sdp-frame-shell{margin:0 auto;background:#1d2327;border-radius:22px;padding:12px;box-shadow:0 8px 28px rgba(0,0,0,.22);transition:width .2s ease,height .2s ease}
            .sdp-frame-shell.desktop{border-radius:10px;padding:8px}
            .sdp-frame-top{height:14px;display:flex;justify-content:center;align-items:flex-start}
            .sdp-frame-top span{display:block;width:60px;height:5px;border-radius:10px;background:#646970}
            .sdp-frame-shell.desktop .sdp-frame-top{display:none}
            #sdp-frame{display:block;width:100%;height:calc(100% - 14px);border:0;background:#fff;border-radius:12px}
            .sdp-frame-shell.desktop #sdp-frame{height:100%;border-radius:5px}
            @media(max-width:782px){#sdp-url{width:100%}.sdp-stage{padding:12px}.sdp-wrap{max-width:100%}}
        </style>

        <script>
        (() => {
            const nonce = <?php echo wp_json_encode($nonce); ?>;
            const home = <?php echo wp_json_encode(home_url('/')); ?>;
            const frame = document.getElementById('sdp-frame');
            const shell = document.getElementById('sdp-shell');
            const urlInput = document.getElementById('sdp-url');
            const pageSelect = document.getElementById('sdp-page-select');
            const sizeText = document.getElementById('sdp-size');
            const newTab = document.getElementById('sdp-new-tab');
            let device = 'mobile', width = 390, height = 844;

            function safeUrl(raw) {
                try {
                    const u = new URL(raw || home, home);
                    if (u.origin !== new URL(home).origin) return new URL(home);
                    return u;
                } catch (_) { return new URL(home); }
            }
            function buildUrl() {
                const u = safeUrl(urlInput.value);
                u.searchParams.set('sdp_preview', device);
                u.searchParams.set('_sdpnonce', nonce);
                u.searchParams.set('sdp_time', Date.now().toString());
                return u.toString();
            }
            function resize() {
                shell.style.width = width + 'px';
                shell.style.height = height + 'px';
                shell.classList.toggle('desktop', device === 'desktop');
                sizeText.textContent = width + ' × ' + height;
            }
            function load() {
                const src = buildUrl();
                frame.src = src;
                newTab.href = src;
                resize();
            }
            document.querySelectorAll('.sdp-device').forEach(btn => btn.addEventListener('click', () => {
                document.querySelectorAll('.sdp-device').forEach(x => x.classList.remove('button-primary'));
                btn.classList.add('button-primary');
                device = btn.dataset.device;
                width = Number(btn.dataset.width);
                height = Number(btn.dataset.height);
                load();
            }));
            document.getElementById('sdp-rotate').addEventListener('click', () => {
                [width, height] = [height, width];
                resize();
            });
            document.getElementById('sdp-load').addEventListener('click', load);
            document.getElementById('sdp-refresh').addEventListener('click', load);
            pageSelect.addEventListener('change', () => {
                if (pageSelect.value !== 'custom') { urlInput.value = pageSelect.value; load(); }
                else { urlInput.focus(); urlInput.select(); }
            });
            urlInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); load(); } });
            load();
        })();
        </script>
        <?php
    }
}

Site_Device_Preview::init();
