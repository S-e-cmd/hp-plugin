<?php
/**
 * Integrated top-slider management screen.
 *
 * Existing dp_options / mlm_options contracts are retained so current saved
 * settings continue to work without migration.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Admin {
    const PAGE = 'garden-opening-status-slider';
    const NONCE = 'gos_slider_save';

    public static function register_hooks() {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
        add_action('admin_post_gos_slider_save', [__CLASS__, 'save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_footer', [__CLASS__, 'legacy_screen_handoff']);
    }

    public static function admin_menu() {
        add_submenu_page(
            'garden-opening-status',
            'トップスライダー管理',
            'トップスライダー',
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render']
        );
    }

    public static function admin_assets($hook) {
        if ($hook !== 'garden-opening-status_page_' . self::PAGE) return;
        wp_enqueue_media();
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;

        $state = GOS_Slider_Integration::read();
        $slides = isset($state['slides']) && is_array($state['slides']) ? $state['slides'] : [];
        ?>
        <div class="wrap gos-slider-admin">
            <h1>トップスライダー管理</h1>
            <p>PC画像とスマホ画像を同じ画面で管理します。既存設定をそのまま引き継ぎます。</p>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>スライダー設定を保存しました。</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="gos-slider-form">
                <input type="hidden" name="action" value="gos_slider_save">
                <?php wp_nonce_field(self::NONCE); ?>

                <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;margin:18px 0 22px;padding:16px 18px;background:#fff;border:1px solid #dcdcde;">
                    <label><input type="checkbox" name="top_enabled" value="1" <?php checked(!empty($state['mobile_enabled'])); ?>> <strong>スマホ専用画像を使用</strong></label>
                    <?php self::range_field('breakpoint', 'スマホ切替幅', (int)($state['breakpoint'] ?? 767), 480, 1200, 'px'); ?>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;max-width:1280px;">
                    <?php for ($slot = 1; $slot <= 3; $slot++):
                        $slide = isset($slides[$slot]) && is_array($slides[$slot]) ? $slides[$slot] : [];
                        self::slide_card($slot, $slide);
                    endfor; ?>
                </div>

                <?php submit_button('スライダー設定を保存'); ?>
            </form>
        </div>

        <script>
        (function(){
            'use strict';

            function syncRange(root){
                root.querySelectorAll('[data-gos-range]').forEach(function(range){
                    var number=range.parentNode.querySelector('[data-gos-number]');
                    if(!number)return;
                    range.addEventListener('input',function(){number.value=range.value;});
                    number.addEventListener('input',function(){range.value=number.value;});
                });
            }

            function applyMedia(target,item){
                var input=document.getElementById(target);
                var preview=document.querySelector('[data-gos-preview="'+target+'"]');
                if(!input||!item||!item.id)return;
                input.value=String(item.id);
                var url=(item.sizes&&item.sizes.medium_large)?item.sizes.medium_large.url:((item.sizes&&item.sizes.medium)?item.sizes.medium.url:item.url);
                if(preview&&url)preview.innerHTML='<img src="'+url+'" alt="" style="width:100%;height:180px;object-fit:cover;display:block;">';
            }

            document.querySelectorAll('[data-gos-media-select]').forEach(function(button){
                button.addEventListener('click',function(){
                    var target=button.getAttribute('data-gos-media-select');
                    var frame=wp.media({title:'画像を選択',button:{text:'この画像を使用'},multiple:false,library:{type:'image'}});
                    window.UIC_ACTIVE_MEDIA_FRAME=frame;
                    var cropApplied=false;

                    function applyModel(model){
                        if(!model)return;
                        var item=model.toJSON?model.toJSON():model;
                        applyMedia(target,item);
                    }

                    window.UIC_CONTEXT={
                        frame:frame,
                        target:'gos-top-slider',
                        onCropped:function(model){cropApplied=true;applyModel(model);}
                    };
                    frame.on('uic:cropped',function(model){if(!cropApplied){cropApplied=true;applyModel(model);}});
                    frame.on('select',function(){
                        if(cropApplied)return;
                        var selection=frame.state().get('selection');
                        var first=selection&&selection.first();
                        if(first)applyModel(first);
                    });
                    frame.on('close',function(){
                        setTimeout(function(){
                            if(window.UIC_ACTIVE_MEDIA_FRAME===frame)window.UIC_ACTIVE_MEDIA_FRAME=null;
                            if(window.UIC_CONTEXT&&window.UIC_CONTEXT.frame===frame)window.UIC_CONTEXT=null;
                        },100);
                    });
                    frame.open();
                });
            });

            document.querySelectorAll('[data-gos-media-clear]').forEach(function(button){
                button.addEventListener('click',function(){
                    var target=button.getAttribute('data-gos-media-clear');
                    var input=document.getElementById(target);
                    var preview=document.querySelector('[data-gos-preview="'+target+'"]');
                    input.value='0';
                    if(preview) preview.innerHTML='<span>'+(button.getAttribute('data-gos-clear-label')||'未設定')+'</span>';
                });
            });

            syncRange(document);
        })();
        </script>
        <?php
    }

    private static function range_field($name, $label, $value, $min, $max, $unit) {
        $id = 'gos-' . sanitize_html_class(str_replace(['[', ']'], ['-', ''], $name));
        ?>
        <label for="<?php echo esc_attr($id); ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span><?php echo esc_html($label); ?></span>
            <span style="display:flex;align-items:center;gap:6px;">
                <input id="<?php echo esc_attr($id); ?>" type="range" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($value); ?>" data-gos-range>
                <input type="number" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" data-gos-number style="width:80px;">
                <em><?php echo esc_html($unit); ?></em>
            </span>
        </label>
        <?php
    }

    private static function slide_card($slot, $slide) {
        $pc = isset($slide['pc']) && is_array($slide['pc']) ? $slide['pc'] : [];
        $mobile = isset($slide['mobile']) && is_array($slide['mobile']) ? $slide['mobile'] : [];
        $pc_id = absint($pc['image_id'] ?? 0);
        $mobile_id = absint($mobile['image_id'] ?? 0);
        $pc_url = $pc_id ? wp_get_attachment_image_url($pc_id, 'medium_large') : '';
        $mobile_url = $mobile_id ? wp_get_attachment_image_url($mobile_id, 'medium_large') : '';
        $pc_input = 'gos-slider-pc-' . $slot;
        $mobile_input = 'gos-slider-mobile-' . $slot;
        ?>
        <section style="background:#fff;border:1px solid #dcdcde;padding:16px;box-sizing:border-box;">
            <h2 style="margin:0 0 14px;">スライダー <?php echo (int)$slot; ?></h2>

            <strong>PC画像</strong>
            <div data-gos-preview="<?php echo esc_attr($pc_input); ?>" style="height:180px;background:#f0f0f1;display:flex;align-items:center;justify-content:center;margin:8px 0;overflow:hidden;">
                <?php if ($pc_url): ?><img src="<?php echo esc_url($pc_url); ?>" alt="" style="width:100%;height:180px;object-fit:cover;display:block;"><?php else: ?><span>未設定</span><?php endif; ?>
            </div>
            <input id="<?php echo esc_attr($pc_input); ?>" type="hidden" name="slides[<?php echo (int)$slot; ?>][pc_image_id]" value="<?php echo esc_attr($pc_id); ?>">
            <p><button type="button" class="button" data-gos-media-select="<?php echo esc_attr($pc_input); ?>">画像を変更</button> <button type="button" class="button-link-delete" data-gos-media-clear="<?php echo esc_attr($pc_input); ?>">解除</button></p>

            <strong>スマホ画像</strong>
            <div data-gos-preview="<?php echo esc_attr($mobile_input); ?>" style="height:180px;background:#f0f0f1;display:flex;align-items:center;justify-content:center;margin:8px 0;overflow:hidden;">
                <?php if ($mobile_url): ?><img src="<?php echo esc_url($mobile_url); ?>" alt="" style="width:100%;height:180px;object-fit:cover;display:block;"><?php else: ?><span>PC画像を使用</span><?php endif; ?>
            </div>
            <input id="<?php echo esc_attr($mobile_input); ?>" type="hidden" name="slides[<?php echo (int)$slot; ?>][mobile_image_id]" value="<?php echo esc_attr($mobile_id); ?>">
            <p><button type="button" class="button" data-gos-media-select="<?php echo esc_attr($mobile_input); ?>">画像を変更</button> <button type="button" class="button-link-delete" data-gos-media-clear="<?php echo esc_attr($mobile_input); ?>" data-gos-clear-label="PC画像を使用">PC画像を使用</button></p>

            <?php if (!$pc_id): ?><div class="notice notice-warning inline"><p>PC画像が未設定のため、このスロットはテーマ側で表示されません。</p></div><?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
                <?php self::range_field("slides[$slot][position_x]", '表示位置・左右', (int)($mobile['position_x'] ?? 50), 0, 100, '%'); ?>
                <?php self::range_field("slides[$slot][position_y]", '表示位置・上下', (int)($mobile['position_y'] ?? 50), 0, 100, '%'); ?>
            </div>
        </section>
        <?php
    }

    public static function save() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);
        GOS_Slider_Settings::save(wp_unslash($_POST));
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    public static function legacy_screen_handoff() {
        if (!current_user_can('manage_options')) return;
        if (empty($_GET['page']) || sanitize_key((string)$_GET['page']) !== 'mobile-layout-manager') return;
        $url = admin_url('admin.php?page=' . self::PAGE);
        ?>
        <script>
        (function(){
          var cards=Array.prototype.slice.call(document.querySelectorAll('.mlm-card'));
          cards.forEach(function(card){
            var heading=card.querySelector('h2');
            if(heading && heading.textContent.indexOf('トップスライダー')!==-1) card.remove();
          });
          var wrap=document.querySelector('.mlm-wrap');
          if(!wrap)return;
          var note=document.createElement('div');
          note.className='notice notice-info';
          note.innerHTML='<p>トップスライダー設定は開催情報管理へ移動しました。 <a href="<?php echo esc_url($url); ?>">トップスライダー管理を開く</a></p>';
          var h=wrap.querySelector('h1');
          if(h) h.insertAdjacentElement('afterend',note);
        })();
        </script>
        <?php
    }
}
