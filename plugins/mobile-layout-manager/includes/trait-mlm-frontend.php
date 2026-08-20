<?php
/**
 * Front-end mobile overrides and slider replacement.
 *
 * @package MLM_Mobile_Layout_Manager
 */

if (!defined('ABSPATH')) exit;

trait MLM_Frontend_Trait {
    public static function preview_no_cache() {
        if (!empty($_GET['mlm_preview']) && is_user_logged_in()) nocache_headers();
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
}
