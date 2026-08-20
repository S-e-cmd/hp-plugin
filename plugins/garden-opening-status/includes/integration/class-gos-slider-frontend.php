<?php
/**
 * Frontend owner for top-slider mobile image replacement.
 *
 * Reads the same mlm_options data during migration, so existing saved settings
 * continue to work after the legacy plugin's slider hooks are detached.
 *
 * @package Garden_Opening_Status
 */

if (!defined('ABSPATH')) exit;

final class GOS_Slider_Frontend {
    public static function register_hooks() {
        add_action('wp_loaded', [__CLASS__, 'detach_legacy_slider_js'], 20);
        add_action('wp_head', [__CLASS__, 'output_css'], 99);
        add_action('wp_footer', [__CLASS__, 'output_js'], 99);
    }

    public static function detach_legacy_slider_js() {
        if (!class_exists('MLM_Mobile_Layout_Manager', false)) return;
        remove_action('wp_footer', ['MLM_Mobile_Layout_Manager', 'frontend_slider_js'], 99);
    }

    private static function data() {
        if (!is_front_page()) return ['items' => [], 'breakpoint' => 767];

        $state = GOS_Slider_Integration::read();
        if (empty($state['mobile_enabled'])) return ['items' => [], 'breakpoint' => (int)($state['breakpoint'] ?? 767)];

        $items = [];
        foreach ((array)($state['slides'] ?? []) as $slot => $slide) {
            if (!is_array($slide) || empty($slide['renderable'])) continue;
            $mobile = isset($slide['mobile']) && is_array($slide['mobile']) ? $slide['mobile'] : [];
            $id = absint($mobile['image_id'] ?? 0);
            if (!$id) continue;
            $url = wp_get_attachment_image_url($id, 'full');
            if (!$url) continue;
            $items[(string)(int)$slot] = [
                'id' => $id,
                'url' => esc_url_raw($url),
                'x' => self::bounded_int($mobile['position_x'] ?? 50, 0, 100),
                'y' => self::bounded_int($mobile['position_y'] ?? 50, 0, 100),
            ];
        }

        return [
            'items' => $items,
            'breakpoint' => self::bounded_int($state['breakpoint'] ?? 767, 480, 1200),
        ];
    }

    public static function output_css() {
        $data = self::data();
        if (!$data['items']) return;
        ?>
        <style id="gos-top-slider-mobile-css">
        @media only screen and (max-width: <?php echo (int)$data['breakpoint']; ?>px) {
        <?php foreach ($data['items'] as $slot => $row): ?>
          #top-slider #top-slider-item<?php echo (int)$slot; ?> > span,
          #top-slider #top-slider-item<?php echo (int)$slot; ?> > a > span {
            background-image:url("<?php echo esc_url($row['url']); ?>")!important;
            background-position:<?php echo (int)$row['x']; ?>% <?php echo (int)$row['y']; ?>%!important;
            background-size:cover!important;
            background-repeat:no-repeat!important;
          }
        <?php endforeach; ?>
        }
        </style>
        <?php
    }

    public static function output_js() {
        $data = self::data();
        if (!$data['items']) return;
        ?>
        <script id="gos-top-slider-mobile-js">
        (function(){
          'use strict';
          var items=<?php echo wp_json_encode($data['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          var mq=window.matchMedia('(max-width:<?php echo (int)$data['breakpoint']; ?>px)');
          var applying=false;

          function targets(slot){
            var root=document.getElementById('top-slider');
            if(!root)return [];
            var item=root.querySelector('#top-slider-item'+slot);
            if(!item)return [];
            var list=[];
            if(item.matches('span'))list.push(item);
            item.querySelectorAll(':scope > span, :scope > a > span').forEach(function(el){list.push(el)});
            return list;
          }

          function paint(el,row){
            var url='url("'+String(row.url).replace(/"/g,'\\"')+'")';
            el.style.setProperty('background-image',url,'important');
            el.style.setProperty('background-position',row.x+'% '+row.y+'%','important');
            el.style.setProperty('background-size','cover','important');
            el.style.setProperty('background-repeat','no-repeat','important');
            el.setAttribute('data-gos-mobile-image-id',String(row.id));
          }

          function apply(){
            if(applying||!mq.matches)return;
            applying=true;
            Object.keys(items).forEach(function(slot){targets(slot).forEach(function(el){paint(el,items[slot])})});
            applying=false;
          }

          function boot(){
            apply();
            var slider=document.getElementById('top-slider');
            if(slider){
              new MutationObserver(function(){requestAnimationFrame(apply)}).observe(slider,{subtree:true,childList:true,attributes:true,attributeFilter:['style','class']});
              if(window.jQuery)window.jQuery(slider).on('init reInit setPosition beforeChange afterChange',function(){requestAnimationFrame(apply)});
            }
            [0,50,150,300,600,1000,1800].forEach(function(ms){setTimeout(apply,ms)});
          }

          if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
          window.addEventListener('load',apply,{once:true});
          if(mq.addEventListener)mq.addEventListener('change',apply);else if(mq.addListener)mq.addListener(apply);
        })();
        </script>
        <?php
    }

    private static function bounded_int($value, $min, $max) {
        return max($min, min($max, (int)$value));
    }
}
