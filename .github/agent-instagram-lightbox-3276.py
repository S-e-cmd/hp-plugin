from pathlib import Path

php_paths = [Path('garden-opening-status/garden-opening-status.php'), Path('plugins/garden-opening-status/garden-opening-status.php')]
readme_paths = [Path('garden-opening-status/readme.txt'), Path('plugins/garden-opening-status/readme.txt')]
php_texts = [p.read_text(encoding='utf-8') for p in php_paths]
readme_texts = [p.read_text(encoding='utf-8') for p in readme_paths]
if php_texts[0] != php_texts[1]:
    raise SystemExit('PHP mirrors differ before change')
if readme_texts[0] != readme_texts[1]:
    raise SystemExit('readme mirrors differ before change')

text = php_texts[0]
if text.count('Version: 3.2.75') != 1 or text.count("const VERSION = '3.2.75';") != 1:
    raise SystemExit('unexpected version markers')
text = text.replace('Version: 3.2.75', 'Version: 3.2.76', 1)
text = text.replace("const VERSION = '3.2.75';", "const VERSION = '3.2.76';", 1)

old_hook = "        add_shortcode('garden_instagram_gallery', [__CLASS__, 'shortcode_instagram_gallery']);\n"
new_hook = old_hook + "        add_action('wp_footer', [__CLASS__, 'instagram_lightbox_assets'], 102);\n"
if text.count(old_hook) != 1:
    raise SystemExit('Instagram shortcode hook boundary not found exactly once')
text = text.replace(old_hook, new_hook, 1)

old_anchor = '''                    <a class="gos-instagram-gallery__item" href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer">\n                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr(wp_trim_words((string)$item['caption'], 12, '')); ?>" loading="lazy" decoding="async">'''
new_anchor = '''                    <a class="gos-instagram-gallery__item" href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer" data-gos-instagram-lightbox data-gos-image="<?php echo esc_url($item['image_url']); ?>" data-gos-caption="<?php echo esc_attr((string)$item['caption']); ?>">\n                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr(wp_trim_words((string)$item['caption'], 12, '')); ?>" loading="lazy" decoding="async">'''
if text.count(old_anchor) != 1:
    raise SystemExit('Instagram item anchor boundary not found exactly once')
text = text.replace(old_anchor, new_anchor, 1)

marker = "    public static function shortcode_instagram_gallery($atts) {\n"
if text.count(marker) != 1:
    raise SystemExit('shortcode method marker not found exactly once')
method = r'''    public static function instagram_lightbox_assets() {
        if (is_admin()) return;
        $o = self::instagram_options();
        if (empty($o['items'])) return;
        ?>
        <style id="gos-instagram-lightbox-style">
        .gos-instagram-lightbox{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:28px;background:rgba(0,0,0,.82);box-sizing:border-box}.gos-instagram-lightbox[hidden]{display:none!important}.gos-instagram-lightbox__dialog{position:relative;max-width:min(92vw,980px);max-height:94vh;padding:18px 18px 12px;background:#fff7ef;box-shadow:0 12px 46px rgba(0,0,0,.35);box-sizing:border-box;overflow:auto}.gos-instagram-lightbox__image{display:block;max-width:100%;max-height:calc(94vh - 145px);width:auto;height:auto;margin:0 auto;object-fit:contain}.gos-instagram-lightbox__caption{margin:12px 4px 0;color:#111;font-size:18px;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}.gos-instagram-lightbox__instagram{display:inline-block;margin:8px 4px 0;color:#555!important;font-size:13px;text-decoration:underline!important}.gos-instagram-lightbox__close{position:fixed;top:14px;right:18px;width:52px;height:52px;border:3px solid #222;border-radius:50%;background:#fff;color:#222;font-size:38px;line-height:42px;font-family:Arial,sans-serif;cursor:pointer;box-shadow:0 2px 0 rgba(255,255,255,.8);z-index:2}.gos-instagram-lightbox-open{overflow:hidden!important}@media(max-width:600px){.gos-instagram-lightbox{padding:14px}.gos-instagram-lightbox__dialog{max-width:100%;max-height:92vh;padding:10px 10px 9px}.gos-instagram-lightbox__image{max-height:calc(92vh - 125px)}.gos-instagram-lightbox__caption{font-size:15px;margin-top:9px}.gos-instagram-lightbox__close{top:8px;right:8px;width:44px;height:44px;font-size:32px;line-height:34px;border-width:2px}}
        </style>
        <script id="gos-instagram-lightbox-script">
        (function(){
            if(window.GOSInstagramLightboxReady)return;
            window.GOSInstagramLightboxReady=true;
            var modal=null,lastFocused=null;
            function ensure(){
                if(modal)return modal;
                modal=document.createElement('div');
                modal.className='gos-instagram-lightbox';
                modal.hidden=true;
                modal.setAttribute('role','dialog');
                modal.setAttribute('aria-modal','true');
                modal.setAttribute('aria-label','Instagram画像拡大表示');
                modal.innerHTML='<button type="button" class="gos-instagram-lightbox__close" aria-label="閉じる">×</button><div class="gos-instagram-lightbox__dialog"><img class="gos-instagram-lightbox__image" alt=""><div class="gos-instagram-lightbox__caption"></div><a class="gos-instagram-lightbox__instagram" target="_blank" rel="noopener noreferrer">Instagramで開く</a></div>';
                document.body.appendChild(modal);
                modal.querySelector('.gos-instagram-lightbox__close').addEventListener('click',close);
                modal.addEventListener('click',function(e){if(e.target===modal)close()});
                return modal;
            }
            function close(){
                if(!modal||modal.hidden)return;
                modal.hidden=true;
                document.documentElement.classList.remove('gos-instagram-lightbox-open');
                document.body.classList.remove('gos-instagram-lightbox-open');
                if(lastFocused&&typeof lastFocused.focus==='function')lastFocused.focus();
            }
            function open(item){
                var box=ensure();
                var img=item.querySelector('img');
                var src=item.getAttribute('data-gos-image')||(img&&(img.currentSrc||img.src))||'';
                if(!src)return;
                lastFocused=item;
                var large=box.querySelector('.gos-instagram-lightbox__image');
                large.src=src;
                large.alt=(img&&img.alt)||'';
                var caption=item.getAttribute('data-gos-caption')||'';
                var captionEl=box.querySelector('.gos-instagram-lightbox__caption');
                captionEl.textContent=caption;
                captionEl.hidden=!caption;
                var link=box.querySelector('.gos-instagram-lightbox__instagram');
                link.href=item.href;
                box.hidden=false;
                document.documentElement.classList.add('gos-instagram-lightbox-open');
                document.body.classList.add('gos-instagram-lightbox-open');
                box.querySelector('.gos-instagram-lightbox__close').focus();
            }
            document.addEventListener('click',function(e){
                if(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
                var item=e.target.closest&&e.target.closest('.gos-instagram-gallery__item[data-gos-instagram-lightbox]');
                if(!item)return;
                e.preventDefault();
                open(item);
            });
            document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
        })();
        </script>
        <?php
    }

'''
text = text.replace(marker, method + marker, 1)

readme = readme_texts[0]
if not readme.startswith('開催情報・開催状況管理 3.2.75\n'):
    raise SystemExit('unexpected readme heading')
entry = "開催情報・開催状況管理 3.2.76\n\n3.2.76:\n- Instagramギャラリーの画像クリック拡大表示を追加\n- 背景クリック、閉じるボタン、Escキーで閉じる操作に対応\n- 拡大画像下に投稿キャプションとInstagramへのリンクを表示\n- スマホ表示に対応\n\n"
readme = entry + readme

for p in php_paths:
    p.write_text(text, encoding='utf-8')
for p in readme_paths:
    p.write_text(readme, encoding='utf-8')
