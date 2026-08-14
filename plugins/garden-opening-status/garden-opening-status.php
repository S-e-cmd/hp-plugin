<?php
/**
 * Plugin Name: 開催情報・開催状況管理
 * Description: 春・秋・冬の開催概要を一元管理し、各会期ページとトップページの開催状況へ共通出力します。
 * Version: 3.2.89
 * Author: Site Admin
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Garden_Opening_Status_V3 {
    const OPTION = 'garden_opening_status_options';
    const VERSION_OPTION = 'garden_opening_status_version';
    const VERSION = '3.2.89';
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
        add_filter('language_attributes', [__CLASS__, 'language_attributes'], 20, 2);
        add_action('template_redirect', [__CLASS__, 'start_multilingual_theme_og_output_buffer'], -110);
        add_action('template_redirect', [__CLASS__, 'start_description_output_buffer'], -100);
        add_action('template_redirect', [__CLASS__, 'start_permanent_guide_output_buffer'], -90);
        add_action('pre_get_posts', [__CLASS__, 'exclude_permanent_guide_from_news_queries'], 20);
        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_permanent_guide_schema'], 20);
        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_multilingual_schema'], 30);
        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_japanese_home_schema'], 35);
        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_schedule_index_schema'], 36);
        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);
        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);
        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_japanese_home_title'], 105);
        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_japanese_home_description'], 105);
        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_schedule_index_title'], 106);
        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_schedule_index_description'], 106);
        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);
        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_event_page_description'], 110);
        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);
        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);
        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_japanese_home_facebook_tags'], 105);
        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_japanese_home_twitter_tags'], 105);
        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_schedule_index_facebook_tags'], 106);
        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_schedule_index_twitter_tags'], 106);
        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);
        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_event_page_twitter_tags'], 110);
        add_action('wp_head', [__CLASS__, 'output_multilingual_seo_marker'], 1);
        add_action('wp_head', [__CLASS__, 'output_hreflang_links'], 5);
        add_action('wp_head', [__CLASS__, 'output_facility_structured_data'], 6);
        add_action('wp_head', [__CLASS__, 'output_event_structured_data'], 7);
        add_action('wp_head', [__CLASS__, 'output_completed_notice_structured_data'], 8);
        add_action('wp_head', [__CLASS__, 'output_permanent_guide_structured_data'], 9);
        add_filter('the_content', [__CLASS__, 'prepend_completed_event_notice'], 8);
        add_filter('the_content', [__CLASS__, 'event_page_preview_content'], 20);
        add_filter('the_content', [__CLASS__, 'expand_event_info_shortcodes'], 99);
        add_filter('the_content', [__CLASS__, 'append_permanent_guide_modified_date'], 100);
        add_action('wp_footer', [__CLASS__, 'japanese_access_layout'], 96);
        add_action('wp_footer', [__CLASS__, 'permanent_guide_frontend'], 95);
        add_action('wp_footer', [__CLASS__, 'instagram_gallery_fallback'], 97);
        add_action('wp_footer', [__CLASS__, 'multilingual_event_info_fallback'], 98);
        add_action('wp_footer', [__CLASS__, 'event_page_preview_fallback'], 100);
        add_action('wp_head', [__CLASS__, 'boot_hide'], 0);
        add_action('wp_head', [__CLASS__, 'front_styles'], 99);
        add_action('wp_footer', [__CLASS__, 'front_script'], 99);
        add_action('template_redirect', [__CLASS__, 'start_event_page_output_buffer'], 0);
        add_shortcode('garden_opening_status', [__CLASS__, 'shortcode_status']);
        add_shortcode('garden_event', [__CLASS__, 'shortcode_event']);
        add_shortcode('garden_event_info', [__CLASS__, 'shortcode_event_info']);
        add_shortcode('garden_event_overview', [__CLASS__, 'shortcode_event_info']);
        self::register_instagram_hooks();
    }

    private static function register_instagram_hooks() {
        add_action('admin_menu', [__CLASS__, 'instagram_admin_menu']);
        add_action('admin_post_gos_instagram_save', [__CLASS__, 'instagram_save']);
        add_action('admin_post_gos_instagram_refresh', [__CLASS__, 'instagram_refresh_action']);
        add_action('gos_instagram_cron', [__CLASS__, 'instagram_refresh']);
        add_filter('cron_schedules', [__CLASS__, 'instagram_cron_schedules']);
        add_shortcode('garden_instagram_gallery', [__CLASS__, 'shortcode_instagram_gallery']);
        add_action('wp_footer', [__CLASS__, 'instagram_lightbox_assets'], 102);
    }

    private static function information_page_language() {
        if (is_page('english')) return 'en';
        if (is_page('chinese')) return 'zh-Hant';
        if (is_front_page()) return 'ja';
        return '';
    }

    /**
     * English / Traditional Chinese pages use the correct document language
     * without changing the site's global WordPress language setting.
     */
    public static function language_attributes($output, $doctype = 'html') {
        $lang = self::information_page_language();

        if ($lang === '') return $output;

        if (preg_match('/\blang=("|\')[^"\']*\1/i', $output)) {
            return preg_replace('/\blang=("|\')[^"\']*\1/i', 'lang="' . esc_attr($lang) . '"', $output, 1);
        }

        return trim($output . ' lang="' . esc_attr($lang) . '"');
    }

    /**
     * On English / Traditional Chinese pages, the legacy theme emits its own OG
     * tags before AIOSEO. Remove only those pre-AIOSEO OG tags so AIOSEO remains
     * the single source of Open Graph metadata. No tags are reinserted here.
     */
    public static function start_multilingual_theme_og_output_buffer() {
        if (is_admin()) return;
        $language = self::information_page_language();
        $is_event_page = self::current_event_page_season() !== '';
        $is_schedule_index = is_page('schedule');
        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return;
        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);
    }

    public static function filter_multilingual_theme_og_output($html) {
        if (!is_string($html) || $html === '') return $html;

        $language = self::information_page_language();
        $is_event_page = self::current_event_page_season() !== '';
        $is_schedule_index = is_page('schedule');
        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return $html;

        $aioseo_marker = '<!-- All in One SEO';
        $marker_pos = stripos($html, $aioseo_marker);
        if ($marker_pos === false) return $html;

        $before = substr($html, 0, $marker_pos);
        $after = substr($html, $marker_pos);
        if (!is_string($before) || !is_string($after)) return $html;

        $pattern = '~<meta\\b(?=[^>]*\\bproperty\\s*=\\s*(["\\\'])og:(?:type|url|title|description|site_name|image(?::(?:secure_url|width|height))?)\\1)[^>]*>\\s*~i';
        $before = preg_replace($pattern, '', $before);
        if (!is_string($before)) return $html;

        return $before . $after;
    }

    private static function multilingual_seo_config($language) {
        if ($language === 'en') {
            return [
                'title' => 'Ueno Toshogu Peony Garden | Peonies & Dahlias in Tokyo',
                'page_name' => 'Ueno Toshogu Peony Garden',
                'home_name' => 'Home',
                'site_name' => 'Ueno Toshogu Peony Garden',
                'og_locale' => 'en_US',
                'path' => '/english/',
            ];
        }
        if ($language === 'zh-Hant') {
            return [
                'title' => '上野東照宮牡丹園｜東京上野賞牡丹・大麗花',
                'page_name' => '上野東照宮牡丹園',
                'home_name' => '首頁',
                'site_name' => '上野東照宮牡丹園',
                'og_locale' => 'zh_TW',
                'path' => '/chinese/',
            ];
        }
        return [];
    }

    private static function multilingual_page_description($language) {
        if ($language === 'en') {
            return 'Ueno Toshogu Peony Garden in central Tokyo presents the Wintertime Peony Festival, Springtime Peony Festival, and Special Festival - Autumn Dahlia Garden.';
        }
        if ($language === 'zh-Hant') {
            return '上野東照宮牡丹園位於東京都心，舉辦冬季牡丹園、春季牡丹節及特別祭典-秋季大麗花園，並提供參觀與交通資訊。';
        }
        return '';
    }

    public static function filter_aioseo_multilingual_title($title) {
        $seo = self::multilingual_seo_config(self::information_page_language());
        return $seo ? $seo['title'] : $title;
    }

    public static function filter_aioseo_multilingual_description($description) {
        $language = self::information_page_language();
        $localized = self::multilingual_page_description($language);
        return $localized !== '' ? $localized : $description;
    }

    /**
     * Japanese home-page search metadata. This changes head metadata only;
     * visible page content, headings, and layout remain untouched.
     */
    private static function japanese_home_seo_config() {
        if (!is_front_page() || self::information_page_language() !== 'ja') return [];
        return [
            'title' => '東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑',
            'description' => '東京・上野公園にある上野東照宮ぼたん苑。回遊形式の日本庭園で、春と冬の牡丹、秋のダリアなど季節の花を楽しめます。上野観光・東京の庭園散策にもおすすめです。',
            'image' => home_url('/wp-content/uploads/2021/03/main1_sp.png'),
            'image_width' => '1450',
            'image_height' => '860',
        ];
    }

    public static function filter_aioseo_japanese_home_title($title) {
        $seo = self::japanese_home_seo_config();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_japanese_home_description($description) {
        $seo = self::japanese_home_seo_config();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    public static function filter_aioseo_japanese_home_facebook_tags($tags) {
        $seo = self::japanese_home_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['og:locale'] = 'ja_JP';
        $tags['og:type'] = 'website';
        $tags['og:url'] = home_url('/');
        $tags['og:title'] = $seo['title'];
        $tags['og:description'] = $seo['description'];
        $tags['og:image'] = $seo['image'];
        $tags['og:image:secure_url'] = $seo['image'];
        $tags['og:image:width'] = $seo['image_width'];
        $tags['og:image:height'] = $seo['image_height'];
        return $tags;
    }

    public static function filter_aioseo_japanese_home_twitter_tags($tags) {
        $seo = self::japanese_home_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:description'] = $seo['description'];
        $tags['twitter:image'] = $seo['image'];
        return $tags;
    }

    private static function localize_aioseo_japanese_home_schema_node(&$node, $seo) {
        if (!is_array($node)) return;
        $types = [];
        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        }
        if (in_array('WebPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        foreach ($node as &$child) {
            if (is_array($child)) self::localize_aioseo_japanese_home_schema_node($child, $seo);
        }
        unset($child);
    }

    public static function filter_aioseo_japanese_home_schema($graphs) {
        $seo = self::japanese_home_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_japanese_home_schema_node($graphs, $seo);
        return $graphs;
    }

    /** Search metadata for the fixed seasonal schedule index page. */
    private static function schedule_index_seo_config() {
        if (!is_page('schedule')) return [];
        return [
            'title' => '東京・上野で楽しむ季節の花｜春の牡丹・秋のダリア・冬の牡丹',
            'description' => '東京・上野の上野東照宮ぼたん苑では、春の牡丹、秋のダリア、冬咲き牡丹を季節ごとに公開しています。各会期の開催情報、開苑時期、詳細をご案内します。',
        ];
    }

    public static function filter_aioseo_schedule_index_title($title) {
        $seo = self::schedule_index_seo_config();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_schedule_index_description($description) {
        $seo = self::schedule_index_seo_config();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    public static function filter_aioseo_schedule_index_facebook_tags($tags) {
        $seo = self::schedule_index_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['og:locale'] = 'ja_JP';
        $tags['og:url'] = home_url('/schedule/');
        $tags['og:title'] = $seo['title'];
        $tags['og:description'] = $seo['description'];
        return $tags;
    }

    public static function filter_aioseo_schedule_index_twitter_tags($tags) {
        $seo = self::schedule_index_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:description'] = $seo['description'];
        return $tags;
    }

    private static function localize_aioseo_schedule_index_schema_node(&$node, $seo) {
        if (!is_array($node)) return;
        $types = [];
        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        }
        if (in_array('WebPage', $types, true) || in_array('CollectionPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        foreach ($node as &$child) {
            if (is_array($child)) self::localize_aioseo_schedule_index_schema_node($child, $seo);
        }
        unset($child);
    }

    public static function filter_aioseo_schedule_index_schema($graphs) {
        $seo = self::schedule_index_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_schedule_index_schema_node($graphs, $seo);
        return $graphs;
    }

    /**
     * Seasonal fixed pages always get public-safe AIOSEO metadata from plugin
     * state instead of allowing AIOSEO to infer it from stored page content.
     * Confirmed dates appear only after the event release gate opens.
     */
    private static function confirmed_event_page_seo_data() {
        $season = self::current_event_page_season();
        if ($season === '') return [];

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        if (empty($event['enabled'])) return [];

        $name = trim((string)($event['label'] ?? ''));
        if ($name === '') return [];
        $is_spring = $season === 'spring';

        $now = self::now();
        $date_display_mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));
        $released = self::event_released($event, $now);
        $start_date = trim((string)($event['start'] ?? ''));
        $end_date = trim((string)($event['end'] ?? ''));

        if ($released && $date_display_mode === 'confirmed' && $start_date !== '' && $end_date !== '') {
            $start = self::dt($start_date, '00:00');
            $end = self::dt($end_date, '23:59');
            if ($start && $end && $end >= $start) {
                if ($start->format('Y') === $end->format('Y')) {
                    $range = $start->format('Y年n月j日') . '～' . $end->format('n月j日');
                    $description = $start->format('Y年') . 'の' . $name . 'は' . $start->format('n月j日') . 'から' . $end->format('n月j日') . 'まで開催します。';
                } else {
                    $range = $start->format('Y年n月j日') . '～' . $end->format('Y年n月j日');
                    $description = $name . 'は' . $start->format('Y年n月j日') . 'から' . $end->format('Y年n月j日') . 'まで開催します。';
                }
                if ($is_spring) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、牡丹をはじめとする春の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
                return [
                    'title' => $name . '｜' . $range . '｜上野東照宮ぼたん苑',
                    'description' => $description . '開苑時間、入苑料、アクセスなどをご案内します。',
                ];
            }
        }

        $usual = trim((string)($event['usual_period'] ?? ''));
        if ($is_spring) {
            return [
                'title' => '東京・上野で牡丹を楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する' . $name . '。日本庭園で牡丹をはじめとする春の花を楽しめます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }

        $description = $name . 'の会期情報。';
        if ($usual !== '') $description .= '例年の開苑期間は' . $usual . 'です。';
        $description .= '開苑時間、入苑料、アクセスなどをご案内します。';

        return [
            'title' => $name . '｜上野東照宮ぼたん苑',
            'description' => $description,
        ];
    }

    public static function filter_aioseo_event_page_title($title) {
        $seo = self::confirmed_event_page_seo_data();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_event_page_description($description) {
        $seo = self::confirmed_event_page_seo_data();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    /**
     * Use the seasonal page's featured image for social sharing. The same image
     * already represents the page in AIOSEO's WebPage schema. Fall back to the
     * site's established share image only when a seasonal page has no thumbnail.
     */
    private static function event_page_social_image_data() {
        if (self::current_event_page_season() === '') return [];

        $post_id = (int)get_queried_object_id();
        if ($post_id > 0) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $image = wp_get_attachment_image_src($thumbnail_id, 'full');
                if (is_array($image) && !empty($image[0])) {
                    return [
                        'url' => esc_url_raw((string)$image[0]),
                        'width' => !empty($image[1]) ? (string)(int)$image[1] : '',
                        'height' => !empty($image[2]) ? (string)(int)$image[2] : '',
                    ];
                }
            }
        }

        return [
            'url' => home_url('/wp-content/uploads/2021/03/main1_sp.png'),
            'width' => '1450',
            'height' => '860',
        ];
    }

    public static function filter_aioseo_event_page_facebook_tags($tags) {
        if (!is_array($tags)) return $tags;
        $image = self::event_page_social_image_data();
        if (empty($image['url'])) return $tags;

        $tags['og:image'] = $image['url'];
        $tags['og:image:secure_url'] = $image['url'];
        if ($image['width'] !== '') $tags['og:image:width'] = $image['width'];
        if ($image['height'] !== '') $tags['og:image:height'] = $image['height'];
        return $tags;
    }

    public static function filter_aioseo_event_page_twitter_tags($tags) {
        if (!is_array($tags)) return $tags;
        $image = self::event_page_social_image_data();
        if (empty($image['url'])) return $tags;

        $tags['twitter:image'] = $image['url'];
        return $tags;
    }

    public static function filter_aioseo_multilingual_facebook_tags($tags) {
        $language = self::information_page_language();
        $seo = self::multilingual_seo_config($language);
        if (!$seo || !is_array($tags)) return $tags;

        $image_url = home_url('/wp-content/uploads/2021/03/main1_sp.png');
        $description = self::multilingual_page_description($language);
        $tags['og:locale'] = $seo['og_locale'];
        $tags['og:type'] = 'article';
        $tags['og:url'] = home_url($seo['path']);
        $tags['og:title'] = $seo['title'];
        $tags['og:site_name'] = $seo['site_name'];
        $tags['og:image'] = $image_url;
        $tags['og:image:secure_url'] = $image_url;
        $tags['og:image:width'] = '1450';
        $tags['og:image:height'] = '860';
        if ($description !== '') $tags['og:description'] = $description;
        return $tags;
    }

    public static function filter_aioseo_multilingual_twitter_tags($tags) {
        $language = self::information_page_language();
        $seo = self::multilingual_seo_config($language);
        if (!$seo || !is_array($tags)) return $tags;

        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:image'] = home_url('/wp-content/uploads/2021/03/main1_sp.png');
        $description = self::multilingual_page_description($language);
        if ($description !== '') $tags['twitter:description'] = $description;
        return $tags;
    }

    public static function output_multilingual_seo_marker() {
        $language = self::information_page_language();
        if ($language !== 'en' && $language !== 'zh-Hant') return;
        echo "<!-- Garden Opening Status 3.2.89 multilingual SEO active -->\n";
    }

    private static function localize_aioseo_multilingual_schema_node(&$node, $language, $seo) {
        if (!is_array($node)) return;

        $types = [];
        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        }

        if (in_array('WebPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['inLanguage'] = $language;
        }

        if (in_array('BreadcrumbList', $types, true) && !empty($node['itemListElement']) && is_array($node['itemListElement'])) {
            foreach ($node['itemListElement'] as &$item) {
                if (!is_array($item)) continue;
                $position = isset($item['position']) ? (int)$item['position'] : 0;
                if ($position === 1) {
                    $item['name'] = $seo['home_name'];
                    if (isset($item['nextItem']) && is_array($item['nextItem'])) {
                        $item['nextItem']['name'] = $seo['page_name'];
                    }
                } elseif ($position === 2) {
                    $item['name'] = $seo['page_name'];
                    if (isset($item['previousItem']) && is_array($item['previousItem'])) {
                        $item['previousItem']['name'] = $seo['home_name'];
                    }
                }
            }
            unset($item);
        }

        foreach ($node as &$child) {
            if (is_array($child)) {
                self::localize_aioseo_multilingual_schema_node($child, $language, $seo);
            }
        }
        unset($child);
    }

    public static function filter_aioseo_multilingual_schema($graphs) {
        $language = self::information_page_language();
        $seo = self::multilingual_seo_config($language);
        if (!$seo || !is_array($graphs)) return $graphs;

        self::localize_aioseo_multilingual_schema_node($graphs, $language, $seo);
        return $graphs;
    }

    /**
     * Normalize description metadata on the Japanese, English, and Traditional
     * Chinese information pages. The theme and SEO plugin both output description
     * tags, so the completed HTML is filtered to leave one language-appropriate set.
     */
    public static function start_description_output_buffer() {
        if (is_admin()) return;
        $language = self::information_page_language();
        if ($language === '' || $language === 'ja' || $language === 'en' || $language === 'zh-Hant') return;
        ob_start([__CLASS__, 'filter_description_output']);
    }

    public static function filter_description_output($html) {
        if (!is_string($html) || $html === '') return $html;

        $language = self::information_page_language();
        if ($language === 'en' || $language === 'zh-Hant') return $html;
        $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';

        $patterns = [
            '~<meta\b(?=[^>]*\bname\s*=\s*(["\'])description\1)[^>]*>\s*~i',
            '~<meta\b(?=[^>]*\bproperty\s*=\s*(["\'])og:description\1)[^>]*>\s*~i',
            '~<meta\b(?=[^>]*\bname\s*=\s*(["\'])twitter:description\1)[^>]*>\s*~i',
        ];
        $html = preg_replace($patterns, '', $html);
        if (!is_string($html)) return '';

        $meta = "\n<!-- Garden page descriptions -->\n";
        $meta .= '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        $meta .= '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        $meta .= '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";

        if (stripos($html, '</head>') !== false) {
            return preg_replace('~</head>~i', $meta . '</head>', $html, 1);
        }

        return $html . $meta;
    }

    private static function permanent_guide_config() {
        $options = self::options(false);
        return is_array($options['permanent_guide'] ?? null) ? $options['permanent_guide'] : [];
    }

    private static function permanent_guide_post_id() {
        static $resolved = false;
        static $post_id = 0;
        if ($resolved) return $post_id;
        $resolved = true;
        $config = self::permanent_guide_config();
        if (empty($config['enabled'])) return 0;
        $url = trim((string)($config['url'] ?? ''));
        if ($url === '') return 0;
        $post_id = (int)url_to_postid($url);
        return $post_id;
    }

    private static function is_permanent_guide_page() {
        $post_id = self::permanent_guide_post_id();
        return $post_id > 0 && is_singular() && get_queried_object_id() === $post_id;
    }

    public static function start_permanent_guide_output_buffer() {
        if (is_admin() || !self::is_permanent_guide_page()) return;
        ob_start([__CLASS__, 'filter_permanent_guide_output']);
    }

    private static function convert_article_schema_to_webpage(&$node) {
        if (!is_array($node)) return;
        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
            $has_article = false;
            $kept = [];
            foreach ($types as $type) {
                if (in_array($type, ['Article', 'NewsArticle', 'BlogPosting'], true)) {
                    $has_article = true;
                } else {
                    $kept[] = $type;
                }
            }
            if ($has_article) {
                if (!in_array('WebPage', $kept, true)) $kept[] = 'WebPage';
                $node['@type'] = count($kept) === 1 ? reset($kept) : array_values($kept);
                unset($node['articleSection'], $node['wordCount'], $node['datePublished']);
            }
        }
        foreach ($node as &$child) {
            if (is_array($child)) self::convert_article_schema_to_webpage($child);
        }
        unset($child);
    }

    public static function filter_permanent_guide_output($html) {
        if (!is_string($html) || $html === '') return $html;
        return preg_replace_callback(
            '~<script\b([^>]*type=["\']application/ld\+json["\'][^>]*)>(.*?)</script>~is',
            static function($match) {
                $data = json_decode(trim($match[2]), true);
                if (!is_array($data)) return $match[0];
                self::convert_article_schema_to_webpage($data);
                return '<script' . $match[1] . '>'
                    . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . '</script>';
            },
            $html
        );
    }

    public static function filter_aioseo_permanent_guide_schema($graphs) {
        if (!self::is_permanent_guide_page() || !is_array($graphs)) return $graphs;
        self::convert_article_schema_to_webpage($graphs);
        return $graphs;
    }

    private static function output_json_ld($data) {
        echo '<script type="application/ld+json">';
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '</script>' . "\n";
    }

    public static function output_permanent_guide_structured_data() {
        if (!self::is_permanent_guide_page()) return;
        $post_id = self::permanent_guide_post_id();
        $url = get_permalink($post_id);
        if (!$url) return;
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => trailingslashit($url) . '#webpage',
            'url' => $url,
            'name' => get_the_title($post_id),
            'inLanguage' => 'ja',
            'dateModified' => get_post_modified_time(DATE_ATOM, false, $post_id),
            'isPartOf' => [
                '@type' => 'WebSite',
                '@id' => home_url('/#website'),
                'url' => home_url('/'),
            ],
            'about' => [
                '@type' => ['TouristAttraction', 'Park'],
                '@id' => home_url('/#garden'),
            ],
        ];
        self::output_json_ld($data);
    }

    public static function append_permanent_guide_modified_date($content) {
        if (!is_string($content) || !self::is_permanent_guide_page() || !in_the_loop() || !is_main_query()) return $content;
        $config = self::permanent_guide_config();
        if (empty($config['show_modified_date'])) return $content;
        $post_id = self::permanent_guide_post_id();
        $modified = get_post_modified_time('Y年n月j日', false, $post_id);
        if (!$modified) return $content;
        return $content . '<p class="gos-permanent-guide__modified">最終更新：' . esc_html($modified) . '</p>';
    }

    public static function exclude_permanent_guide_from_news_queries($query) {
        if (is_admin() || !is_object($query) || $query->is_singular()) return;
        $config = self::permanent_guide_config();
        if (empty($config['enabled']) || empty($config['exclude_from_news_list'])) return;
        $post_id = self::permanent_guide_post_id();
        if ($post_id <= 0) return;

        $post_type = $query->get('post_type');
        $types = is_array($post_type) ? $post_type : [$post_type];
        $is_news_query = $query->is_post_type_archive('news') || in_array('news', $types, true);
        if (!$is_news_query) return;

        $excluded = array_map('absint', (array)$query->get('post__not_in'));
        $excluded[] = $post_id;
        $query->set('post__not_in', array_values(array_unique($excluded)));
    }

    /**
     * Connect the Japanese, English, and Traditional Chinese information pages.
     * Canonical URLs remain managed by the existing SEO plugin.
     */
    public static function output_hreflang_links() {
        if (self::information_page_language() === '') return;

        $urls = [
            'ja' => home_url('/'),
            'en' => home_url('/english/'),
            'zh-Hant' => home_url('/chinese/'),
            'x-default' => home_url('/'),
        ];

        echo "\n<!-- Garden language alternates -->\n";
        foreach ($urls as $hreflang => $url) {
            printf(
                '<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
                esc_attr($hreflang),
                esc_url($url)
            );
        }
    }


    /**
     * Output a compact, factual facility profile for search engines and AI systems.
     * Seasonal opening dates and hours are intentionally excluded here because they
     * change by event and will be represented separately by Event structured data.
     */
    public static function output_facility_structured_data() {
        if (self::information_page_language() === '') return;

        $language = self::information_page_language();
        $name = '上野東照宮ぼたん苑';
        $description = '上野東照宮の境内にある季節開苑の庭園。春と冬の牡丹、秋のダリアを展示しています。';

        if ($language === 'en') {
            $name = 'Ueno Toshogu Peony Garden';
            $description = 'A seasonal garden within the grounds of Ueno Toshogu Shrine, featuring peonies in spring and winter and dahlias in autumn.';
        } elseif ($language === 'zh-Hant') {
            $name = '上野東照宮牡丹園';
            $description = '位於上野東照宮境內的季節性庭園，春季與冬季展示牡丹，秋季展示大麗花。';
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => ['TouristAttraction', 'Park'],
            '@id' => home_url('/#garden'),
            'name' => $name,
            'alternateName' => '上野東照宮ぼたん苑',
            'description' => $description,
            'inLanguage' => $language,
            'url' => home_url('/'),
            'telephone' => '+81-3-3822-3575',
            'address' => [
                '@type' => 'PostalAddress',
                'postalCode' => '110-0007',
                'addressRegion' => '東京都',
                'addressLocality' => '台東区',
                'streetAddress' => '上野公園9-88',
                'addressCountry' => 'JP',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => 35.7147171,
                'longitude' => 139.7726485,
            ],
            'hasMap' => 'https://goo.gl/maps/cDHRnQGCLMpQ8v8T8',
            'sameAs' => [
                'https://www.facebook.com/profile.php?id=100063766242032',
            ],
        ];

        echo "\n<!-- Garden facility structured data -->\n";
        self::output_json_ld($data);
    }

    /**
     * Output Event structured data from the confirmed seasonal settings.
     * Only events whose confirmed dates are released and explicitly displayed on the public site are included.
     */
    public static function output_event_structured_data() {
        $detail_season = self::current_event_page_season();
        if (!is_front_page() && $detail_season === '') return;

        $options = self::options(false);
        $now = self::now();
        $events = [];

        foreach (self::event_keys() as $season) {
            if ($detail_season !== '' && $season !== $detail_season) continue;

            $event = self::event_from_options($options, $season);

            if (empty($event['enabled'])) continue;
            if (!self::event_released($event, $now)) continue;

            // 構造化データも公開情報です。管理画面に確定日が入力されていても、
            // 公開画面で「確定日を表示」が選ばれていない会期は出力しません。
            $date_display_mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));
            if ($date_display_mode !== 'confirmed') continue;

            $start_date = trim((string)($event['start'] ?? ''));
            $end_date = trim((string)($event['end'] ?? ''));
            if ($start_date === '' || $end_date === '') continue;

            $start = self::dt($start_date, $event['open_time'] ?? '00:00');
            $end = self::dt($end_date, $event['close_time'] ?? '23:59');
            if (!$start || !$end || $end < $start) continue;

            $name = trim((string)($event['label'] ?? ''));
            if ($name === '') continue;

            // Prefer the canonical permalink resolved from the WordPress page.
            // The saved URL may still use a legacy query form such as ?p=47.
            $page_id = self::resolve_event_page_id($event, $season);
            $detail_url = $page_id > 0 ? get_permalink($page_id) : '';
            if (!$detail_url) {
                $detail_url = trim((string)($event['detail_url'] ?? ''));
            }
            if ($detail_url === '') $detail_url = home_url('/');
            $detail_url = esc_url_raw($detail_url);

            // This block is emitted only when confirmed dates are publicly displayed.
            // Use those public dates in the description instead of the usual seasonal period.
            $description_parts = [
                $name . 'は' . $start->format('Y年n月j日') . 'から' . $end->format('Y年n月j日') . 'まで開催します。',
            ];
            $time_note = trim((string)($event['time_note'] ?? ''));
            if ($time_note !== '') $description_parts[] = $time_note;
            $overview_note = trim((string)($event['overview_note'] ?? ''));
            if ($overview_note !== '') $description_parts[] = $overview_note;

            $item = [
                '@type' => 'Event',
                '@id' => trailingslashit($detail_url) . '#event',
                'name' => $name,
                'startDate' => $start->format(DATE_ATOM),
                'endDate' => $end->format(DATE_ATOM),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => [
                    '@type' => 'Place',
                    '@id' => home_url('/#garden'),
                    'name' => '上野東照宮ぼたん苑',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'postalCode' => '110-0007',
                        'addressRegion' => '東京都',
                        'addressLocality' => '台東区',
                        'streetAddress' => '上野公園9-88',
                        'addressCountry' => 'JP',
                    ],
                ],
                'organizer' => [
                    '@type' => 'Organization',
                    'name' => '上野東照宮ぼたん苑',
                    'url' => home_url('/'),
                ],
                'url' => $detail_url,
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $detail_url,
                ],
                'inLanguage' => 'ja',
            ];

            if ($description_parts) {
                $item['description'] = implode(' ', $description_parts);
            }

            if ($page_id > 0) {
                $image = get_the_post_thumbnail_url($page_id, 'full');
                if ($image) $item['image'] = [$image];
            }

            $price_text = trim((string)($event['price'] ?? ''));
            if ($price_text === '') $price_text = trim((string)($event['price_details'] ?? ''));
            if ($price_text !== '' && preg_match('/([0-9][0-9,]*)\s*円/u', $price_text, $match)) {
                $price = str_replace(',', '', $match[1]);
                $item['offers'] = [
                    '@type' => 'Offer',
                    'url' => $detail_url,
                    'price' => $price,
                    'priceCurrency' => 'JPY',
                    'availability' => 'https://schema.org/InStock',
                    'validFrom' => $start->format(DATE_ATOM),
                ];
            }

            $events[] = $item;
        }

        if (!$events) return;

        $data = [
            '@context' => 'https://schema.org',
            '@graph' => $events,
        ];

        echo "\n<!-- Garden event structured data -->\n";
        self::output_json_ld($data);
    }

    /**
     * Identify an old seasonal opening announcement whose stated end date has
     * passed. The permanent seasonal page remains the source for current dates,
     * hours and admission information.
     */
    private static function completed_notice_context() {
        static $resolved = false;
        static $context = [];
        if ($resolved) return $context;
        $resolved = true;

        if (is_admin() || !is_singular()) return [];
        $post_id = get_queried_object_id();
        if ($post_id <= 0) return [];

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) return [];

        $title = wp_strip_all_tags((string)$post->post_title);
        if (!preg_match('/開催(?:予定)?(?:のお知らせ|について)?/u', $title)) return [];
        if (preg_match('/フォトコンテスト|販売|講座|教室|夜間|ライトアップ/u', $title)) return [];

        $season = '';
        if (preg_match('/春のぼたん祭/u', $title)) {
            $season = 'spring';
        } elseif (preg_match('/ダリア綾なす秋の園|ダリア展/u', $title)) {
            $season = 'autumn';
        } elseif (preg_match('/冬ぼたん|冬のぼたん|冬咲きぼたん/u', $title)) {
            $season = 'winter';
        }
        if ($season === '') return [];

        $plain = html_entity_decode(
            wp_strip_all_tags(strip_shortcodes((string)$post->post_content)),
            ENT_QUOTES,
            get_bloginfo('charset') ?: 'UTF-8'
        );
        $pattern = '/(20\d{2})\s*(?:年|[\/.\-])\s*(\d{1,2})\s*(?:月|[\/.\-])\s*(\d{1,2})\s*日?(?:\s*[\(（][^\)）]*[\)）])?\s*[~〜～－—–]\s*(?:(20\d{2})\s*(?:年|[\/.\-])\s*)?(\d{1,2})\s*(?:月|[\/.\-])\s*(\d{1,2})\s*日?/u';
        if (!preg_match($pattern, $plain, $match)) return [];

        $start_year = (int)$match[1];
        $start_month = (int)$match[2];
        $start_day = (int)$match[3];
        $end_year = !empty($match[4]) ? (int)$match[4] : $start_year;
        $end_month = (int)$match[5];
        $end_day = (int)$match[6];
        if (empty($match[4]) && $end_month < $start_month) $end_year++;
        if (!checkdate($start_month, $start_day, $start_year) || !checkdate($end_month, $end_day, $end_year)) return [];

        $timezone = wp_timezone();
        $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $start_year, $start_month, $start_day), $timezone);
        $end = new DateTimeImmutable(sprintf('%04d-%02d-%02d 23:59:59', $end_year, $end_month, $end_day), $timezone);
        if (self::now() <= $end) return [];

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        $page_id = self::resolve_event_page_id($event, $season);
        $current_url = $page_id > 0 ? get_permalink($page_id) : trim((string)($event['detail_url'] ?? ''));
        if (!$current_url) $current_url = home_url('/schedule/');

        $label = trim((string)($event['label'] ?? ''));
        if ($label === '') $label = preg_replace('/\s*開催(?:予定)?(?:のお知らせ|について)?\s*$/u', '', $title);

        $context = [
            'post_id' => $post_id,
            'season' => $season,
            'name' => $label,
            'start' => $start,
            'end' => $end,
            'notice_url' => get_permalink($post_id),
            'current_url' => esc_url_raw($current_url),
        ];
        return $context;
    }

    /** Add a visible, crawlable end notice and point visitors to the current source. */
    public static function prepend_completed_event_notice($content) {
        if (!is_string($content) || !in_the_loop() || !is_main_query()) return $content;
        $context = self::completed_notice_context();
        if (!$context) return $content;

        $ended = $context['end']->format('Y年n月j日');
        $notice = '<aside class="gos-completed-event-notice" aria-label="終了した開催情報">';
        $notice .= '<strong>この開催は終了しました</strong>';
        $notice .= '<p>掲載している内容は過去の開催記録です（' . esc_html($ended) . '終了）。現在の開催期間・開苑時間・入苑料は、<a href="' . esc_url($context['current_url']) . '">最新の会期情報</a>をご確認ください。</p>';
        $notice .= '</aside>';
        $notice .= '<style>.gos-completed-event-notice{margin:0 0 28px;padding:16px 18px;border:1px solid #bbb;border-left:5px solid #777;background:#f7f7f7;line-height:1.7}.gos-completed-event-notice strong{display:block;font-size:1.08em}.gos-completed-event-notice p{margin:6px 0 0}</style>';
        return $notice . $content;
    }

    /** Describe an archived announcement as a completed event for machines. */
    public static function output_completed_notice_structured_data() {
        $context = self::completed_notice_context();
        if (!$context) return;

        echo '<link rel="related" href="' . esc_url($context['current_url']) . '" />' . "\n";
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => trailingslashit($context['notice_url']) . '#event',
            'name' => $context['name'],
            'startDate' => $context['start']->format(DATE_ATOM),
            'endDate' => $context['end']->format(DATE_ATOM),
            'eventStatus' => 'https://schema.org/EventCompleted',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => '終了した過去の開催情報です。現在の開催情報は固定会期ページをご確認ください。',
            'url' => $context['notice_url'],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $context['notice_url'],
            ],
            'location' => [
                '@type' => 'Place',
                '@id' => home_url('/#garden'),
                'name' => '上野東照宮ぼたん苑',
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => '上野東照宮ぼたん苑',
                'url' => home_url('/'),
            ],
            'inLanguage' => 'ja',
        ];
        self::output_json_ld($data);
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

    private static function is_event_key($key) {
        return in_array($key, self::event_keys(), true);
    }

    private static function is_state_key($key) {
        return in_array($key, self::state_keys(), true);
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
            'permanent_guide' => [
                'enabled' => 1,
                'url' => home_url('/news/notice/'),
                'show_home' => 1,
                'show_news_archive' => 1,
                'exclude_from_news_list' => 1,
                'show_modified_date' => 1,
                'eyebrow' => 'ご来苑前にご確認ください',
            ],
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
        $main = get_option(self::OPTION, []);
        $existing = get_option(self::LAYOUTS_OPTION, null);
        if ($existing === null) {
            $legacy = is_array($main) ? self::sanitize_layout_templates($main['layout_templates'] ?? []) : [];
            add_option(self::LAYOUTS_OPTION, $legacy, '', false);
        }
        $existing_default = get_option(self::DEFAULT_LAYOUT_OPTION, null);
        if ($existing_default === null) {
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
        add_menu_page('開催情報管理', '開催情報管理', 'manage_options', 'garden-opening-status', [__CLASS__, 'admin_page'], 'dashicons-calendar-alt', 25);
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


    private static function known_event_page_ids() {
        return ['spring' => 19, 'autumn' => 47, 'winter' => 49];
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
        $known_id = (int)(self::known_event_page_ids()[$season] ?? 0);
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

    private static function normalize_event_page_path($value) {
        $path = (string)wp_parse_url((string)$value, PHP_URL_PATH);
        return trim(rawurldecode($path), '/');
    }

    private static function current_event_page_season() {
        if (is_admin()) return '';

        $page_id = (int)get_queried_object_id();
        $request_path = self::normalize_event_page_path($_SERVER['REQUEST_URI'] ?? '');

        // 管理画面で各会期に設定された詳細ページURLを最優先で照合する。
        $options = self::options();
        foreach (self::event_keys() as $season) {
            $event = self::event_from_options($options, $season);

            $detail_path = self::normalize_event_page_path($event['detail_url'] ?? '');
            if ($detail_path !== '' && $request_path === $detail_path) {
                return $season;
            }
        }

        // 現在運用中の固定ページID。
        foreach (self::known_event_page_ids() as $season => $known_id) {
            if ($page_id === (int)$known_id) return $season;
        }

        // URL末尾による互換判定。ページ構成変更後も判定できるよう複数候補を持つ。
        $path_candidates = [
            'spring' => [
                'schedule/spring',
                'spring',
            ],
            'autumn' => [
                'schedule/autumn',
                'schedule/dahlia',
                'autumn',
                'dahlia',
            ],
            'winter' => [
                'schedule/winter',
                'winter',
            ],
        ];
        foreach ($path_candidates as $season => $candidates) {
            foreach ($candidates as $candidate) {
                if (
                    $request_path === $candidate
                    || substr($request_path, -strlen('/' . $candidate)) === '/' . $candidate
                ) {
                    return $season;
                }
            }
        }

        // 最終手段として、固定ページのスラッグとタイトルから判定する。
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $slug = sanitize_title((string)$post->post_name);
            $title = wp_strip_all_tags((string)$post->post_title);

            if (
                strpos($slug, 'spring') !== false
                || strpos($title, '春') !== false
            ) return 'spring';

            if (
                strpos($slug, 'autumn') !== false
                || strpos($slug, 'dahlia') !== false
                || strpos($title, '秋') !== false
                || strpos($title, 'ダリア') !== false
            ) return 'autumn';

            if (
                strpos($slug, 'winter') !== false
                || strpos($title, '冬') !== false
            ) return 'winter';
        }

        return '';
    }

    public static function start_event_page_output_buffer() {
        $season = self::current_event_page_season();
        if ($season === '') return;
        ob_start([__CLASS__, 'filter_event_page_output']);
    }

    private static function event_from_options($options, $season) {
        return is_array($options['events'][$season] ?? null) ? $options['events'][$season] : [];
    }

    private static function event_price_details($event) {
        $price = trim((string)($event['price_details'] ?? ''));
        if ($price === '') $price = trim((string)($event['price'] ?? ''));
        return $price;
    }

    private static function event_info_labels($event) {
        return [
            'date' => trim((string)($event['date_label'] ?? '')) ?: '開苑期間',
            'time' => trim((string)($event['time_label'] ?? '')) ?: '開苑時間',
            'price' => trim((string)($event['admission_label'] ?? '')) ?: '入苑料',
        ];
    }

    private static function event_page_output_values($season) {
        $options = self::options(false);
        $event = self::event_from_options($options, $season);

        $date = self::event_overview_date_text($event);

        $open = self::format_time($event['open_time'] ?? '');
        $close = self::format_time($event['close_time'] ?? '');
        $time = trim($open . (($open !== '' && $close !== '') ? '～' : '') . $close);
        $close_label = trim((string)($event['close_time_label'] ?? ''));
        if ($time !== '' && $close_label !== '') $time .= '（' . $close_label . '）';

        $price = self::event_price_details($event);
        $price_note = trim((string)($event['price_note'] ?? ''));
        if ($price_note !== '') $price .= ($price !== '' ? "\n" : '') . $price_note;

        $labels = self::event_info_labels($event);
        return [
            'date_label' => $labels['date'],
            'date' => $date,
            'time_label' => $labels['time'],
            'time' => $time,
            'price_label' => $labels['price'],
            'price' => $price,
        ];
    }

    private static function label_aliases($type, $configured_label = '') {
        $aliases = [];

        if ($configured_label !== '') {
            $aliases[] = $configured_label;
        }

        if ($type === 'date') {
            $aliases[] = '開苑期間';
            $aliases[] = '開催期間';
            $aliases[] = '会期';
        } elseif ($type === 'time') {
            $aliases[] = '開苑時間';
            $aliases[] = '開催時間';
            $aliases[] = '開園時間';
        } elseif ($type === 'price') {
            $aliases[] = '入苑料';
            $aliases[] = '入園料';
            $aliases[] = '料金';
        }

        return array_values(array_unique(array_filter(array_map('trim', $aliases))));
    }

    private static function replace_labeled_paragraph($html, $labels, $output_label, $value) {
        if ($output_label === '' || $value === '') return $html;
        if (!is_array($labels)) $labels = [$labels];

        foreach ($labels as $label) {
            if ($label === '') continue;

            $label_pattern = preg_quote($label, '~');
            $pattern = '~<p\b([^>]*)>(?:(?!</p>).)*?'
                . $label_pattern
                . '\s*[：:].*?</p>~isu';

            $replacement = '<p$1><strong style="display:inline-block;width:7.5em;font-weight:400;vertical-align:top;">'
                . esc_html($output_label)
                . '：</strong><span style="display:inline-block;vertical-align:top;">'
                . nl2br(esc_html($value))
                . '</span></p>';

            $updated = preg_replace($pattern, $replacement, $html, 1, $count);
            if (is_string($updated) && $count > 0) {
                return $updated;
            }
        }

        return $html;
    }

    private static function has_labeled_paragraph($html, $labels) {
        if (!is_array($labels)) $labels = [$labels];

        foreach ($labels as $label) {
            if ($label === '') continue;

            if (preg_match(
                '~<p\b[^>]*>(?:(?!</p>).)*?'
                . preg_quote($label, '~')
                . '\s*[：:]~isu',
                $html
            )) {
                return true;
            }
        }

        return false;
    }

    private static function insert_labeled_paragraph_after($html, $after_labels, $label, $value) {
        if ($label === '' || $value === '') return $html;
        if (!is_array($after_labels)) $after_labels = [$after_labels];

        foreach ($after_labels as $after_label) {
            if ($after_label === '') continue;

            $pattern = '~(<p\b([^>]*)>(?:(?!</p>).)*?'
                . preg_quote($after_label, '~')
                . '\s*[：:].*?</p>)~isu';

            $paragraph = '<p$2><strong style="display:inline-block;width:7.5em;font-weight:400;vertical-align:top;">'
                . esc_html($label)
                . '：</strong><span style="display:inline-block;vertical-align:top;">'
                . nl2br(esc_html($value))
                . '</span></p>';

            $updated = preg_replace($pattern, '$1' . $paragraph, $html, 1, $count);
            if (is_string($updated) && $count > 0) {
                return $updated;
            }
        }

        return $html;
    }

    public static function filter_event_page_output($html) {
        if (!is_string($html) || $html === '') return $html;

        $season = self::current_event_page_season();
        if ($season === '') return $html;

        $values = self::event_page_output_values($season);

        $date_labels = self::label_aliases('date', $values['date_label']);
        $time_labels = self::label_aliases('time', $values['time_label']);
        $price_labels = self::label_aliases('price', $values['price_label']);

        $html = self::replace_labeled_paragraph(
            $html,
            $date_labels,
            $values['date_label'],
            $values['date']
        );
        $html = self::replace_labeled_paragraph(
            $html,
            $time_labels,
            $values['time_label'],
            $values['time']
        );
        $html = self::replace_labeled_paragraph(
            $html,
            $price_labels,
            $values['price_label'],
            $values['price']
        );

        // 時間行が存在しない場合のみ、期間行の直後へ追加する。
        if (!self::has_labeled_paragraph($html, $time_labels)) {
            $html = self::insert_labeled_paragraph_after(
                $html,
                $date_labels,
                $values['time_label'],
                $values['time']
            );
        }

        // 料金行が存在しない場合のみ、時間行の直後へ追加する。
        // 秋・冬ページのように元の固定ページに料金段落がない場合も反映する。
        if (!self::has_labeled_paragraph($html, $price_labels)) {
            $html = self::insert_labeled_paragraph_after(
                $html,
                $time_labels,
                $values['price_label'],
                $values['price']
            );
        }

        return $html;
    }

    public static function handle_save() {
        if (!is_admin() || empty($_POST['gos_v3_action'])) return;
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer(self::NONCE);
        $clean = self::sanitize_payload(wp_unslash($_POST));
        update_option(self::OPTION, $clean, false);
        update_option(self::VERSION_OPTION, self::VERSION, false);

        // 公開ページは最終HTMLへ反映するため、固定ページ本文は変更しない。
        self::purge_public_caches($clean);
        wp_safe_redirect(add_query_arg(['page' => 'garden-opening-status', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function purge_public_caches($options) {
        $urls = [home_url('/')];
        if (!empty($options['permanent_guide']['url'])) $urls[] = $options['permanent_guide']['url'];
        if (!empty($options['events']) && is_array($options['events'])) {
            foreach ($options['events'] as $event) {
                $url = trim((string)($event['detail_url'] ?? ''));
                if ($url !== '') $urls[] = $url;
            }
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

        // よく使われるページキャッシュ系プラグインが存在する場合のみ実行。
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
        if (function_exists('w3tc_flush_all')) w3tc_flush_all();
        if (function_exists('do_action') && has_action('litespeed_purge_all')) do_action('litespeed_purge_all');
        if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        if (function_exists('opcache_reset')) @opcache_reset();

        do_action('garden_opening_status_saved', $options);
    }

    private static function sanitize_payload($input) {
        $out = self::defaults();
        $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $allowed_next = array_merge(['auto', 'none'], self::event_keys());
        $out['next_mode'] = in_array(($input['next_mode'] ?? 'auto'), $allowed_next, true) ? $input['next_mode'] : 'auto';
        $out['state_mode'] = in_array(($input['state_mode'] ?? 'manual'), ['auto','manual'], true) ? $input['state_mode'] : 'manual';
        $out['manual_state'] = self::is_state_key(($input['manual_state'] ?? 'closed')) ? $input['manual_state'] : 'closed';
        $out['manual_event'] = self::is_event_key(($input['manual_event'] ?? 'spring')) ? $input['manual_event'] : 'spring';
        $out['temporary_closed_date'] = self::clean_date($input['temporary_closed_date'] ?? '');
        $out['access_url'] = esc_url_raw($input['access_url'] ?? '');
        $out['detail_button'] = sanitize_text_field($input['detail_button'] ?? '');
        $out['access_button'] = sanitize_text_field($input['access_button'] ?? '');
        $out['aria_label'] = sanitize_text_field($input['aria_label'] ?? '');
        $guide = is_array($input['permanent_guide'] ?? null) ? $input['permanent_guide'] : [];
        $out['permanent_guide'] = [
            'enabled' => !empty($guide['enabled']) ? 1 : 0,
            'url' => esc_url_raw($guide['url'] ?? ''),
            'show_home' => !empty($guide['show_home']) ? 1 : 0,
            'show_news_archive' => !empty($guide['show_news_archive']) ? 1 : 0,
            'exclude_from_news_list' => !empty($guide['exclude_from_news_list']) ? 1 : 0,
            'show_modified_date' => !empty($guide['show_modified_date']) ? 1 : 0,
            'eyebrow' => sanitize_text_field($guide['eyebrow'] ?? ''),
        ];

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
                'overview_enabled' => !empty($src['overview_enabled']) ? 1 : 0,
                'overview_heading' => sanitize_text_field($src['overview_heading'] ?? '会期情報'),
                'date_label' => sanitize_text_field($src['date_label'] ?? '開苑期間'),
                'date_display_mode' => in_array(($src['date_display_mode'] ?? 'auto'), ['auto','confirmed','usual','hidden'], true) ? $src['date_display_mode'] : 'auto',
                'time_label' => sanitize_text_field($src['time_label'] ?? '開苑時間'),
                'close_time_label' => sanitize_text_field($src['close_time_label'] ?? '入苑締切'),
                'time_note' => sanitize_text_field($src['time_note'] ?? ''),
                'admission_label' => sanitize_text_field($src['admission_label'] ?? '入苑料'),
                'price_details' => sanitize_textarea_field($src['price_details'] ?? ''),
                'price_note' => sanitize_textarea_field($src['price_note'] ?? ''),
                'overview_note' => sanitize_textarea_field($src['overview_note'] ?? ''),
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
        if (!self::is_state_key($state)) $state = $clean['state_mode'] === 'manual' ? $clean['manual_state'] : '';
        $event = sanitize_key($_POST['preview_event'] ?? 'spring');
        if (!self::is_event_key($event)) $event = 'spring';
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
        if (!self::is_any_preview()) return $is_mobile;
        $device = sanitize_key($_GET['gos_preview_device'] ?? '');
        if ($device === 'mobile') return true;
        if ($device === 'desktop') return false;
        return $is_mobile;
    }

    public static function body_class($classes) {
        if (self::is_permanent_guide_page()) $classes[] = 'gos-permanent-guide-page';
        if (self::is_any_preview()) {
            $device = sanitize_key($_GET['gos_preview_device'] ?? '');
            if ($device === 'mobile') $classes[] = 'gos-force-mobile';
            if ($device === 'desktop') $classes[] = 'gos-force-desktop';
            if (!empty($_GET['gos_event_info_preview'])) $classes[] = 'gos-event-info-preview-page';
        }
        return $classes;
    }

    private static function event_page_preview_html() {
        if (!self::is_event_info_preview()) return '';
        $token = sanitize_key($_GET['gos_preview_token'] ?? '');
        $season = sanitize_key($_GET['gos_event_info_preview'] ?? '');
        if (!$token || !self::is_event_key($season)) return '';
        $preview = get_transient(self::preview_key($token));
        if (!is_array($preview)) return '';
        $o = self::normalize($preview);
        if (empty($o['events'][$season]) || !is_array($o['events'][$season])) return '';
        $notice = '<div class="gos-event-preview-notice" style="margin:0 0 18px;padding:10px 14px;border-left:4px solid #2271b1;background:#f0f6fc;font-size:14px"><strong>会期ページ実画面プレビュー</strong><br>管理画面で編集中の内容を表示しています。公開ページにはまだ反映されていません。</div>';
        return '<div id="gos-event-info-live-preview" style="margin:0 0 28px">' . $notice . self::event_info_html($o['events'][$season], $season) . '</div>';
    }

    public static function event_page_preview_content($content) {
        if (!is_singular()) return $content;
        $preview_html = self::event_page_preview_html();
        if ($preview_html === '') return $content;
        return $preview_html . $content;
    }

    public static function event_page_preview_fallback() {
        if (!is_singular()) return;
        $preview_html = self::event_page_preview_html();
        if ($preview_html === '') return;
        $json = wp_json_encode($preview_html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <script id="gos-event-info-preview-fallback">
        (function(){
            if(document.getElementById('gos-event-info-live-preview')) return;
            var html=<?php echo $json; ?>;
            var selectors=[
                '.entry-content',
                '.page-content',
                'article .post-content',
                'article .content',
                'main article',
                '#primary main',
                '.site-main',
                'main'
            ];
            var target=null;
            for(var i=0;i<selectors.length;i++){
                target=document.querySelector(selectors[i]);
                if(target) break;
            }
            if(!target) return;
            var holder=document.createElement('div');
            holder.innerHTML=html;
            var block=holder.firstElementChild;
            if(!block) return;
            target.insertBefore(block,target.firstChild);
        })();
        </script>
        <?php
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
        if (self::is_event_key($o['next_mode'])) {
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

        if ($forced_state && self::is_state_key($forced_state)) {
            $event_key = self::is_event_key($forced_event) ? $forced_event : 'spring';
            $event = ['key' => $event_key] + $o['events'][$event_key];
            return self::make_model($o, $forced_state, $event, $now);
        }

        if (($o['state_mode'] ?? 'manual') === 'manual') {
            $event_key = self::is_event_key(($o['manual_event'] ?? '')) ? $o['manual_event'] : 'spring';
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

    private static function format_date_with_weekday($date) {
        $date = trim((string)$date);
        if ($date === '') return '';
        try {
            $dt = new DateTimeImmutable($date, wp_timezone());
        } catch (Exception $e) {
            return $date;
        }
        $weekdays = ['日','月','火','水','木','金','土'];
        return $dt->format('Y年n月j日') . '（' . $weekdays[(int)$dt->format('w')] . '）';
    }

    private static function format_date_range($start, $end) {
        $start = trim((string)$start);
        $end = trim((string)$end);
        if ($start === '' && $end === '') return '';
        if ($start !== '' && $end !== '') {
            if ($start === $end) return self::format_date_with_weekday($start);
            return self::format_date_with_weekday($start) . '～' . self::format_date_with_weekday($end);
        }
        return self::format_date_with_weekday($start !== '' ? $start : $end);
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

    private static function is_status_preview() {
        return current_user_can('manage_options') && !empty($_GET['garden_status_preview']);
    }

    private static function is_event_info_preview() {
        return current_user_can('manage_options') && !empty($_GET['gos_event_info_preview']);
    }

    private static function is_any_preview() {
        return self::is_status_preview() || self::is_event_info_preview();
    }

    private static function runtime_context() {
        static $context = null;
        if ($context !== null) return $context;

        $o = self::options();
        $forced_state = '';
        $forced_event = '';
        if (self::is_status_preview()) {
            $candidate = sanitize_key($_GET['gos_force_state'] ?? '');
            if (self::is_state_key($candidate)) $forced_state = $candidate;
            $candidate_event = sanitize_key($_GET['gos_force_event'] ?? '');
            if (self::is_event_key($candidate_event)) $forced_event = $candidate_event;
        }
        $model = self::view_model($o, $forced_state, $forced_event);
        $context = [$o, $model];
        return $context;
    }

    private static function should_render() {
        if (!is_front_page()) return false;
        [$o] = self::runtime_context();
        return !empty($o['enabled']) || (self::is_status_preview());
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
        echo '<style id="gos-event-page-info-style">
        .gos-event-page-info{box-sizing:border-box;font-family:inherit;font-size:14px;font-weight:400;line-height:2;color:inherit}
        .gos-event-page-info__rows{display:block}
        .gos-event-page-info__row{display:grid;grid-template-columns:6.4em minmax(0,1fr);column-gap:0;margin:0 0 .55em;font:inherit;line-height:inherit}
        .gos-event-page-info__label{display:block;white-space:nowrap;font:inherit;text-align:justify;text-align-last:justify;padding-right:.45em}
        .gos-event-page-info__value{display:block;min-width:0;white-space:pre-line;font:inherit;line-height:inherit}
        .gos-event-page-info__note{display:block;margin:0;font:inherit;line-height:inherit;white-space:pre-line}
        .gos-event-page-info__footer-note{margin:.55em 0 0;font:inherit;line-height:inherit;white-space:pre-line}
        .gos-event-page-info[lang="en"],.gos-event-page-info[lang="zh-Hant"]{line-height:1.8}
        .gos-event-page-info[lang="en"] .gos-event-page-info__row{grid-template-columns:10.5em minmax(0,1fr);column-gap:.65em;margin:0 0 .45em}
        .gos-event-page-info[lang="zh-Hant"] .gos-event-page-info__row{grid-template-columns:5.8em minmax(0,1fr);column-gap:.65em;margin:0 0 .45em}
        .gos-event-page-info[lang="en"] .gos-event-page-info__label,.gos-event-page-info[lang="zh-Hant"] .gos-event-page-info__label{display:block;text-align:left;text-align-last:auto;padding:0;font-weight:400}
        .gos-event-page-info[lang="en"] .gos-event-page-info__value,.gos-event-page-info[lang="zh-Hant"] .gos-event-page-info__value{display:block;white-space:normal}
        .gos-event-page-info__line{display:block;margin:0 0 .2em}
        @media(max-width:782px){
            .gos-event-page-info{font-size:14px;line-height:1.85}
            .gos-event-page-info__row{grid-template-columns:4.9em minmax(0,1fr);column-gap:.55em;row-gap:0;margin-bottom:.45em;align-items:start}
            .gos-event-page-info[lang="en"] .gos-event-page-info__row{grid-template-columns:7.4em minmax(0,1fr)}
            .gos-event-page-info[lang="zh-Hant"] .gos-event-page-info__row{grid-template-columns:4.9em minmax(0,1fr)}
            .gos-event-page-info__label{font-weight:400!important;text-align:left;text-align-last:auto;padding:0;white-space:nowrap}
            .gos-event-page-info__value{white-space:normal}
            .gos-event-page-info__line{margin:0 0 .15em}
        }
        </style>';

        if (!self::should_render()) return;
        [$o, $model] = self::runtime_context();
        $event_key = self::is_event_key(($model['event_key'] ?? '')) ? $model['event_key'] : 'spring';
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
        $render_device = (self::is_status_preview() && in_array($forced_device,['desktop','mobile'],true)) ? $forced_device : (wp_is_mobile()?'mobile':'desktop');
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

    private static function event_overview_date_text($event) {
        $mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));

        if ($mode === 'hidden' || $mode === 'none') {
            return '';
        }

        if ($mode === 'confirmed') {
            // Confirmed dates may be stored before the information embargo lifts.
            // Until the event is released, keep public pages on the usual period.
            if (!self::event_released($event, self::now())) {
                return trim((string)($event['usual_period'] ?? ''));
            }
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            return self::format_date_range($start, $end);
        }

        return trim((string)($event['usual_period'] ?? ''));
    }

    private static function event_info_styles() {
        static $printed = false;
        if ($printed) return '';
        $printed = true;
        return '<style>.gos-event-info{margin:1.5em 0;padding:1.25em 1.4em;border:1px solid #d8d4cc;background:#fff;box-sizing:border-box}.gos-event-info__heading{margin:0 0 .9em;font-size:1.35em}.gos-event-info__row{display:grid;grid-template-columns:7em minmax(0,1fr);gap:.8em;padding:.55em 0;border-top:1px solid #ece9e3}.gos-event-info__row:first-of-type{border-top:0}.gos-event-info__label{font-weight:700}.gos-event-info__value p{margin:0 0 .35em}.gos-event-info__value p:last-child{margin-bottom:0}.gos-event-info__note{margin:.9em 0 0;color:#555}.gos-event-info__link{margin:1em 0 0}@media(max-width:600px){.gos-event-info{padding:1em}.gos-event-info__row{grid-template-columns:1fr;gap:.25em}}</style>';
    }

    public static function event_info_html($event, $season = '') {
        $rows = [];
        $date = self::event_overview_date_text($event);
        if ($date !== '') $rows[] = [($event['date_label'] ?: '開苑期間'), esc_html($date)];

        $open = self::format_time($event['open_time'] ?? '');
        $close = self::format_time($event['close_time'] ?? '');
        if ($open !== '' || $close !== '') {
            $time = trim($open . (($open !== '' && $close !== '') ? '～' : '') . $close);
            $close_label = trim((string)($event['close_time_label'] ?? ''));
            if ($close_label !== '' && $close !== '') $time .= '（' . $close_label . '）';
            $time_note = trim((string)($event['time_note'] ?? ''));
            if ($time_note !== '') $time .= '<br><small>' . esc_html($time_note) . '</small>';
            $rows[] = [($event['time_label'] ?: '開苑時間'), $time];
        }

        $details = self::event_price_details($event);
        if ($details !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $details);
            $html = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') $html .= '<p>' . esc_html($line) . '</p>';
            }
            $price_note = trim((string)($event['price_note'] ?? ''));
            if ($price_note !== '') $html .= '<p><small>' . nl2br(esc_html($price_note)) . '</small></p>';
            $rows[] = [($event['admission_label'] ?: '入苑料'), $html];
        }

        $heading = trim((string)($event['overview_heading'] ?? '会期情報'));
        $html = self::event_info_styles();
        $html .= '<section class="gos-event-info" data-season="' . esc_attr($season) . '">';
        if ($heading !== '') $html .= '<h2 class="gos-event-info__heading">' . esc_html($heading) . '</h2>';
        foreach ($rows as [$label, $value]) {
            $html .= '<div class="gos-event-info__row"><div class="gos-event-info__label">' . esc_html($label) . '</div><div class="gos-event-info__value">' . $value . '</div></div>';
        }
        $note = trim((string)($event['overview_note'] ?? ''));
        if ($note !== '') $html .= '<div class="gos-event-info__note">' . nl2br(esc_html($note)) . '</div>';
        $html .= '</section>';
        return $html;
    }

    private static function normalize_event_info_lang($lang) {
        $lang = strtolower(str_replace('_', '-', trim((string)$lang)));
        if (in_array($lang, ['en', 'en-us', 'en-gb'], true)) return 'en';
        if (in_array($lang, ['zh', 'zh-tw', 'zh-hant', 'zh-hk'], true)) return 'zh-Hant';
        return 'ja';
    }

    private static function localized_event_date_text($event, $lang) {
        if ($lang === 'ja') return self::event_overview_date_text($event);

        $mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));
        if ($mode === 'hidden' || $mode === 'none') return '';

        if ($mode === 'confirmed') {
            // Match the Japanese fixed-page rule: unreleased confirmed dates are
            // never exposed through English / Traditional Chinese event blocks.
            if (!self::event_released($event, self::now())) {
                $mode = 'usual';
            }
        }

        if ($mode === 'confirmed') {
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            if ($start === '' && $end === '') return '';
            $format = static function($value) use ($lang) {
                $dt = date_create_immutable((string)$value, wp_timezone());
                if (!$dt) return (string)$value;
                if ($lang === 'en') return $dt->format('F j, Y');
                return $dt->format('Y年n月j日');
            };
            if ($start !== '' && $end !== '') {
                return $format($start) . ($lang === 'en' ? ' to ' : '至') . $format($end);
            }
            return $format($start !== '' ? $start : $end);
        }

        $usual = trim((string)($event['usual_period'] ?? ''));
        if ($usual === '') return '';

        if (!preg_match('/^(\d{1,2})月(?:(\d{1,2})日|(上旬|中旬|下旬))\s*[～〜~\-–—至]+\s*(\d{1,2})月(?:(\d{1,2})日|(上旬|中旬|下旬))$/u', $usual, $m)) {
            return $usual;
        }

        $format_part = static function($month, $day, $period, $target_lang) {
            $month = (int)$month;
            if ($target_lang === 'zh-Hant') {
                return $month . '月' . ($day !== '' ? ((int)$day . '日') : $period);
            }

            $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
            if ($day !== '') return ($months[$month] ?? $month) . ' ' . (int)$day;
            $parts = ['上旬' => 'early', '中旬' => 'mid', '下旬' => 'late'];
            return ($parts[$period] ?? $period) . ' ' . ($months[$month] ?? $month);
        };

        $from = $format_part($m[1], (string)($m[2] ?? ''), (string)($m[3] ?? ''), $lang);
        $to = $format_part($m[4], (string)($m[5] ?? ''), (string)($m[6] ?? ''), $lang);
        return $from . ($lang === 'zh-Hant' ? '至' : ' to ') . $to;
    }

    private static function localize_event_info_text($text, $lang) {
        $text = trim((string)$text);
        if ($text === '' || $lang === 'ja') return $text;

        if ($lang === 'en') {
            $text = strtr($text, [
                '大人（中学生以上）' => 'Adults (junior high school age and older)',
                '大人(中学生以上)' => 'Adults (junior high school age and older)',
                '団体（15名以上）' => 'Groups of 15 or more',
                '団体(15名以上)' => 'Groups of 15 or more',
                '小学生以下無料' => 'Free for elementary school students and younger',
                '大人' => 'Adults',
                '無料' => 'Free',
                '割引はございません' => 'No discounts are available.',
            ]);
            return trim((string)preg_replace_callback('/([0-9][0-9,]*)円/u', static function($m) {
                $number = number_format((int)str_replace(',', '', $m[1]));
                return '¥' . $number;
            }, $text));
        }

        $text = strtr($text, [
            '大人（中学生以上）' => '成人（國中生以上）',
            '大人(中学生以上)' => '成人（國中生以上）',
            '団体（15名以上）' => '團體（15人以上）',
            '団体(15名以上)' => '團體（15人以上）',
            '小学生以下無料' => '小學生以下免費',
            '大人' => '成人',
            '無料' => '免費',
            '割引はございません' => '不提供折扣。',
        ]);
        return trim((string)preg_replace_callback('/([0-9][0-9,]*)円/u', static function($m) {
            $number = number_format((int)str_replace(',', '', $m[1]));
            return $number . '日圓';
        }, $text));
    }

    private static function localized_price_lines($text, $lang) {
        $text = trim((string)$text);
        if ($text === '') return [];
        $raw_lines = preg_split('/\r\n|\r|\n/u', $text);
        $lines = [];

        foreach ($raw_lines as $raw) {
            $line = trim((string)$raw);
            if ($line === '') continue;
            $plain = preg_replace('/^[※＊*\s]+/u', '', $line);

            if ($lang === 'en') {
                if (preg_match('/^大人\s*[（(]\s*中学生以上\s*[）)]\s*[:：]?\s*([0-9][0-9,]*)\s*円/u', $plain, $m)) {
                    $lines[] = 'Adults (junior high school age and older): ¥' . number_format((int)str_replace(',', '', $m[1]));
                    continue;
                }
                if (preg_match('/^団体\s*[（(]\s*(\d+)名以上\s*[）)]\s*[:：]?\s*([0-9][0-9,]*)\s*円/u', $plain, $m)) {
                    $lines[] = 'Groups of ' . (int)$m[1] . ' or more: ¥' . number_format((int)str_replace(',', '', $m[2])) . ' per person';
                    continue;
                }
                if (preg_match('/^小学生以下無料/u', $plain)) {
                    $lines[] = 'Free for elementary school students and younger';
                    continue;
                }
                if (preg_match('/割引はございません/u', $plain)) {
                    $lines[] = 'No discounts are available.';
                    continue;
                }
            } elseif ($lang === 'zh-Hant') {
                if (preg_match('/^大人\s*[（(]\s*中学生以上\s*[）)]\s*[:：]?\s*([0-9][0-9,]*)\s*円/u', $plain, $m)) {
                    $lines[] = '成人（國中生以上）：' . number_format((int)str_replace(',', '', $m[1])) . '日圓';
                    continue;
                }
                if (preg_match('/^団体\s*[（(]\s*(\d+)名以上\s*[）)]\s*[:：]?\s*([0-9][0-9,]*)\s*円/u', $plain, $m)) {
                    $lines[] = (int)$m[1] . '人以上團體：每人' . number_format((int)str_replace(',', '', $m[2])) . '日圓';
                    continue;
                }
                if (preg_match('/^小学生以下無料/u', $plain)) {
                    $lines[] = '小學生以下免費';
                    continue;
                }
                if (preg_match('/割引はございません/u', $plain)) {
                    $lines[] = '不提供折扣。';
                    continue;
                }
            }

            $lines[] = self::localize_event_info_text($line, $lang);
        }
        return $lines;
    }

    public static function expand_event_info_shortcodes($content) {
        if (!is_string($content)) return $content;
        if (
            strpos($content, '[garden_event_info') === false &&
            strpos($content, '[garden_instagram_gallery') === false
        ) {
            return $content;
        }
        return do_shortcode($content);
    }

    /**
     * Legacy page templates can print page content without applying the_content.
     * This narrowly replaces only a visible garden_event_info token on the two
     * multilingual pages, without buffering or rewriting the whole response.
     */
    public static function multilingual_event_info_fallback() {
        $lang = self::information_page_language();
        if (is_admin() || !in_array($lang, ['en', 'zh-Hant'], true)) return;
        $rendered = [];
        foreach (self::event_keys() as $season) {
            $rendered[$season] = self::shortcode_event_info([
                'season' => $season,
                'lang' => $lang,
            ]);
        }
        $json = wp_json_encode($rendered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <script id="gos-multilingual-event-info-fallback">
        (function(){
            var rendered=<?php echo $json; ?>;
            var pattern=/\[garden_event_info\s+[^\]]*(?:season|event)=["']?(spring|autumn|winter)["']?[^\]]*\]/i;
            var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{
                acceptNode:function(node){
                    if(!node.nodeValue||node.nodeValue.indexOf('[garden_event_info')===-1)return NodeFilter.FILTER_REJECT;
                    var p=node.parentElement;
                    if(!p||/^(SCRIPT|STYLE|TEXTAREA|CODE|PRE)$/i.test(p.tagName))return NodeFilter.FILTER_REJECT;
                    return NodeFilter.FILTER_ACCEPT;
                }
            });
            var nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);
            nodes.forEach(function(node){
                var text=node.nodeValue,match=text.match(pattern);if(!match)return;
                var season=String(match[1]||'').toLowerCase(),html=rendered[season]||'';
                var before=text.slice(0,match.index),after=text.slice(match.index+match[0].length);
                var frag=document.createDocumentFragment();
                if(before)frag.appendChild(document.createTextNode(before));
                var holder=document.createElement('div');holder.innerHTML=html;
                while(holder.firstChild)frag.appendChild(holder.firstChild);
                if(after)frag.appendChild(document.createTextNode(after));
                node.parentNode.replaceChild(frag,node);
            });
        })();
        </script>
        <?php
    }

    public static function shortcode_event_info($atts) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
        if (!defined('DONOTMINIFY')) define('DONOTMINIFY', true);

        $atts = shortcode_atts([
            'season' => '',
            'event' => '',
            'heading' => '1',
            'lang' => 'ja',
        ], $atts, 'garden_event_info');

        $season = sanitize_key((string)($atts['season'] !== '' ? $atts['season'] : $atts['event']));
        if (!self::is_event_key($season)) $season = 'spring';
        $lang = self::normalize_event_info_lang($atts['lang']);

        $o = self::options(false);
        $event = self::event_from_options($o, $season);

        $date_text = self::localized_event_date_text($event, $lang);
        $open_text = self::format_time($event['open_time'] ?? '');
        $close_text = self::format_time($event['close_time'] ?? '');
        $separator = $lang === 'ja' ? '～' : '–';
        $time_text = trim($open_text . (($open_text && $close_text) ? $separator : '') . $close_text);
        $close_label = trim((string)($event['close_time_label'] ?? ''));
        if ($time_text && $close_label) {
            if ($lang === 'en') $time_text .= ' (last admission)';
            elseif ($lang === 'zh-Hant') $time_text .= '（入園截止）';
            else $time_text .= '（' . $close_label . '）';
        }

        $price_details = self::event_price_details($event);
        $price_lines = self::localized_price_lines($price_details, $lang);

        $labels = [
            'ja' => self::event_info_labels($event),
            'en' => ['date' => 'Opening period', 'time' => 'Opening hours', 'price' => 'Admission'],
            'zh-Hant' => ['date' => '開放期間', 'time' => '開放時間', 'price' => '入園費'],
        ];

        $rows = [];
        if ($date_text !== '') $rows[] = [$labels[$lang]['date'], $date_text, ''];
        if ($time_text !== '') $rows[] = [$labels[$lang]['time'], $time_text, self::localize_event_info_text($event['time_note'] ?? '', $lang)];
        if ($price_lines) $rows[] = [$labels[$lang]['price'], $price_lines, self::localize_event_info_text($event['price_note'] ?? '', $lang)];
        if (!$rows) return '';

        ob_start();
        ?>
        <section class="gos-event-page-info" data-gos-event-season="<?php echo esc_attr($season); ?>" lang="<?php echo esc_attr($lang); ?>">
            <div class="gos-event-page-info__rows">
                <?php foreach ($rows as [$label, $value, $note]): ?>
                    <div class="gos-event-page-info__row">
                        <span class="gos-event-page-info__label"><?php echo esc_html($label); ?><?php echo $lang === 'en' ? ':' : '：'; ?></span>
                        <span class="gos-event-page-info__value">
                            <?php if (is_array($value)): ?>
                                <?php foreach ($value as $line): ?><span class="gos-event-page-info__line"><?php echo esc_html($line); ?></span><?php endforeach; ?>
                            <?php else: ?>
                                <?php echo nl2br(esc_html($value)); ?>
                            <?php endif; ?>
                            <?php if ($note !== ''): ?>
                                <small class="gos-event-page-info__note"><?php echo nl2br(esc_html($note)); ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string)ob_get_clean();
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
            <h1>開催情報管理</h1>
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
                                <label>開催概要<select name="manual_event" id="gos3-manual-event"><?php foreach (self::event_keys() as $key): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($o['manual_event'],$key); ?>><?php echo esc_html($o['events'][$key]['label']); ?></option><?php endforeach; ?></select></label>
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
                            <h2>常設案内</h2>
                            <p class="description">指定した記事を、通常のお知らせではなく常設の案内ページとして表示します。タイトルと本文はWordPressの記事編集画面で変更してください。</p>
                            <div class="gos3-fields">
                                <label class="wide"><input type="checkbox" name="permanent_guide[enabled]" value="1" <?php checked(!empty($o['permanent_guide']['enabled'])); ?>> 常設案内表示を有効にする</label>
                                <label class="wide">対象ページURL<input type="url" name="permanent_guide[url]" value="<?php echo esc_attr($o['permanent_guide']['url']); ?>" placeholder="<?php echo esc_attr(home_url('/news/notice/')); ?>"></label>
                                <label class="wide">案内枠の上段文<input type="text" name="permanent_guide[eyebrow]" value="<?php echo esc_attr($o['permanent_guide']['eyebrow']); ?>"></label>
                                <label><input type="checkbox" name="permanent_guide[show_home]" value="1" <?php checked(!empty($o['permanent_guide']['show_home'])); ?>> トップのお知らせ上部へ表示</label>
                                <label><input type="checkbox" name="permanent_guide[show_news_archive]" value="1" <?php checked(!empty($o['permanent_guide']['show_news_archive'])); ?>> お知らせ一覧上部へ表示</label>
                                <label><input type="checkbox" name="permanent_guide[exclude_from_news_list]" value="1" <?php checked(!empty($o['permanent_guide']['exclude_from_news_list'])); ?>> 通常のお知らせ一覧から除外</label>
                                <label><input type="checkbox" name="permanent_guide[show_modified_date]" value="1" <?php checked(!empty($o['permanent_guide']['show_modified_date'])); ?>> ページ末尾に最終更新日を表示</label>
                            </div>
                        </section>

                        <section class="gos3-card">
                            <h2>イベント・会期概要</h2>
                            <div class="gos3-segment" id="gos3-event-tabs"><?php foreach (self::event_keys() as $i=>$key): ?><button type="button" data-event="<?php echo esc_attr($key); ?>" class="<?php echo $i===0?'active':''; ?>"><?php echo esc_html(['spring'=>'春','autumn'=>'秋','winter'=>'冬'][$key]); ?></button><?php endforeach; ?></div>
                            <?php foreach (self::event_keys() as $i=>$key): $e=$o['events'][$key]; ?>
                            <div class="gos3-event-panel <?php echo $i===0?'active':''; ?>" data-event-panel="<?php echo esc_attr($key); ?>">
                                <div class="gos3-fields">
                                    <label class="wide"><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($e['enabled']); ?>> このイベントを使用する</label>
                                    <label class="wide">催し名<input type="text" name="events[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($e['label']); ?>"></label>
                                    <label class="wide">例年の開催時期<input type="text" name="events[<?php echo esc_attr($key); ?>][usual_period]" value="<?php echo esc_attr($e['usual_period']); ?>" placeholder="例：4月上旬～5月上旬"></label>

                                <label>期間の表示
                                    <select name="events[<?php echo esc_attr($key); ?>][date_display_mode]">
                                        <option value="usual" <?php selected(($e['date_display_mode'] ?? 'usual'), 'usual'); ?>>例年時期を表示</option>
                                        <option value="confirmed" <?php selected(($e['date_display_mode'] ?? 'usual'), 'confirmed'); ?>>確定日を表示</option>
                                        <option value="hidden" <?php selected(($e['date_display_mode'] ?? 'usual'), 'hidden'); ?>>表示しない</option>
                                    </select>
                                </label>

                                <label>確定開始日<input type="date" name="events[<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($e['start']); ?>"></label>
                                    <label>確定終了日<input type="date" name="events[<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($e['end']); ?>"></label>
                                    <label>開苑時刻<input type="time" name="events[<?php echo esc_attr($key); ?>][open_time]" value="<?php echo esc_attr($e['open_time']); ?>"></label>
                                    <label>終了時刻<input type="time" name="events[<?php echo esc_attr($key); ?>][close_time]" value="<?php echo esc_attr($e['close_time']); ?>"></label>
                                    <label class="wide">トップ表示用料金（短文）<input type="text" name="events[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($e['price']); ?>" placeholder="例：大人1,000円"></label>
                                    <label class="wide">詳細ページURL<input type="url" name="events[<?php echo esc_attr($key); ?>][detail_url]" value="<?php echo esc_attr($e['detail_url']); ?>"></label>

                                    <div class="wide gos3-event-overview-fields">
                                        <h3>会期ページへ出す開催概要</h3>
                                        <p class="description">保存すると、既存ページのレイアウトを維持したまま、公開画面の会期情報だけを更新します。</p>
                                        <div class="gos3-fields">
                                            <label class="wide">見出し<input type="text" name="events[<?php echo esc_attr($key); ?>][overview_heading]" value="<?php echo esc_attr($e['overview_heading']); ?>"></label>
                                            <label>期間の項目名<input type="text" name="events[<?php echo esc_attr($key); ?>][date_label]" value="<?php echo esc_attr($e['date_label']); ?>"></label>

                                            <label>時間の項目名<input type="text" name="events[<?php echo esc_attr($key); ?>][time_label]" value="<?php echo esc_attr($e['time_label']); ?>"></label>
                                            <label>終了時刻の補足<input type="text" name="events[<?php echo esc_attr($key); ?>][close_time_label]" value="<?php echo esc_attr($e['close_time_label']); ?>" placeholder="例：入苑締切"></label>
                                            <label class="wide">時間補足<input type="text" name="events[<?php echo esc_attr($key); ?>][time_note]" value="<?php echo esc_attr($e['time_note']); ?>"></label>
                                            <label class="wide">料金の項目名<input type="text" name="events[<?php echo esc_attr($key); ?>][admission_label]" value="<?php echo esc_attr($e['admission_label']); ?>"></label>
                                            <label class="wide">会期ページ用料金（1行ずつ）<textarea rows="4" name="events[<?php echo esc_attr($key); ?>][price_details]" placeholder="大人（中学生以上）1,000円&#10;団体（15名以上）800円&#10;小学生以下無料"><?php echo esc_textarea($e['price_details']); ?></textarea></label>
                                            <label class="wide">料金補足<textarea rows="2" name="events[<?php echo esc_attr($key); ?>][price_note]" placeholder="例：割引はございません"><?php echo esc_textarea($e['price_note']); ?></textarea></label>
                                            <label class="wide">開催概要の補足<textarea rows="3" name="events[<?php echo esc_attr($key); ?>][overview_note]"><?php echo esc_textarea($e['overview_note']); ?></textarea></label>
                                        </div>
                                        <div class="gos3-event-info-preview"><strong>保存済み内容の表示見本</strong><?php echo self::event_info_html($e, $key); ?></div>
                                    </div>
                                    <label>情報公開<select name="events[<?php echo esc_attr($key); ?>][publish_mode]"><option value="immediate" <?php selected($e['publish_mode'],'immediate'); ?>>すぐ公開</option><option value="scheduled" <?php selected($e['publish_mode'],'scheduled'); ?>>指定日時に公開</option><option value="manual" <?php selected($e['publish_mode'],'manual'); ?>>手動公開</option></select></label>
                                    <label>公開日時<input type="datetime-local" name="events[<?php echo esc_attr($key); ?>][publish_at]" value="<?php echo esc_attr($e['publish_at']); ?>"></label>
                                    <label><input type="hidden" name="events[<?php echo esc_attr($key); ?>][manual_published]" value="0"><input type="checkbox" name="events[<?php echo esc_attr($key); ?>][manual_published]" value="1" <?php checked($e['manual_published']); ?>> 手動公開をON</label>
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
                        <div class="gos3-preview-targets" aria-label="プレビュー対象">
                            <span class="gos3-preview-targets-label">表示対象</span>
                            <div class="gos3-segment" id="gos3-preview-targets">
                                <button type="button" data-preview-target="status" class="active">開催状況</button>
                                <button type="button" data-preview-target="event_overview">会期ページ</button>
                            </div>
                        </div>
                        <div class="gos3-preview-contexts">
                            <div class="gos3-preview-context" data-preview-context="status">
                                <small>トップページに重ねる開催状況表示</small>
                            </div>
                            <div class="gos3-preview-context" data-preview-context="event_overview" hidden>
                                <div class="gos3-segment gos3-preview-season" id="gos3-preview-season"><button type="button" data-preview-season="spring" class="active">春</button><button type="button" data-preview-season="autumn">秋</button><button type="button" data-preview-season="winter">冬</button></div>
                            </div>
                        </div>
                        <div class="gos3-preview-actions" data-preview-tools="status"><button type="button" class="button" id="gos3-open-pc">開催状況 PC実画面</button><button type="button" class="button" id="gos3-open-mobile">開催状況 スマホ実画面</button><button type="button" class="button" id="gos3-reload-preview">開催状況を再読込</button></div>
                        <div class="gos3-preview-actions" data-preview-tools="event_overview" hidden><button type="button" class="button" id="gos3-open-event-pc">会期ページ PC実画面</button><button type="button" class="button" id="gos3-open-event-mobile">会期ページ スマホ実画面</button><button type="button" class="button" id="gos3-reload-event-overview">会期ページを再読込</button></div>
                        <details class="gos3-direct-editor" id="gos3-direct-editor">
                            <summary><strong>配置調整ツール</strong><small>要素の移動・整列・スナップ</small></summary>
                            <div class="gos3-direct-editor-body">
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
                        </details>
                        <div class="gos3-preview-frame desktop" id="gos3-preview-frame"><iframe id="gos3-preview-iframe" name="gos3-preview-iframe-window" src="<?php echo esc_url($preview_src); ?>"></iframe></div>
                        <div class="gos3-overview-preview desktop" id="gos3-overview-preview" hidden>
                            <div class="gos3-overview-page"><div class="gos3-overview-page-title" id="gos3-overview-page-title"></div><div id="gos3-overview-preview-body"></div></div>
                        </div>
                        <p id="gos3-preview-status">編集中の内容を実画面で表示します。</p>
                    </aside>
                </div>
            </form>
        </div>
        <?php
    }


    private static function instagram_defaults() {
        return [
            'auto_home' => 1,
            'access_token' => '',
            'ig_user_id' => '',
            'api_version' => 'v23.0',
            'limit' => 6,
            'profile_url' => 'https://www.instagram.com/utbotanen_official/',
            'heading' => 'Instagram',
            'last_fetch' => 0,
            'last_error' => '',
            'items' => [],
        ];
    }

    private static function instagram_options() {
        $saved = get_option('gos_instagram_gallery_options', []);
        $saved = is_array($saved) ? $saved : [];
        return array_replace(self::instagram_defaults(), $saved);
    }

    private static function store_instagram_options($options) {
        update_option('gos_instagram_gallery_options', $options, false);
    }

    public static function instagram_cron_schedules($schedules) {
        if (!isset($schedules['gos_six_hours'])) {
            $schedules['gos_six_hours'] = ['interval' => 21600, 'display' => '6時間ごと'];
        }
        return $schedules;
    }

    public static function instagram_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Instagramギャラリー',
            'Instagramギャラリー',
            'manage_options',
            'gos-instagram-gallery',
            [__CLASS__, 'instagram_admin_page']
        );
    }

    public static function instagram_admin_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::instagram_options();
        $status = isset($_GET['gos_instagram_status']) ? sanitize_key((string)$_GET['gos_instagram_status']) : '';
        ?>
        <div class="wrap">
            <h1>Instagramギャラリー</h1>
            <?php if ($status === 'saved'): ?><div class="notice notice-success"><p>設定を保存しました。</p></div><?php endif; ?>
            <?php if ($status === 'refreshed'): ?><div class="notice notice-success"><p>Instagram投稿を取得しました。</p></div><?php endif; ?>
            <?php if ($status === 'error'): ?><div class="notice notice-error"><p>取得に失敗しました。前回の表示データは維持されています。</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gos_instagram_save">
                <?php wp_nonce_field('gos_instagram_save'); ?>
                <table class="form-table" role="presentation">
                    <tr><th>トップページ表示</th><td><label><input type="checkbox" name="auto_home" value="1" <?php checked(!empty($o['auto_home'])); ?>> 「お知らせ」の下へ自動表示する</label><p class="description">ショートコード表示には影響しません。</p></td></tr>
                    <tr><th>アクセストークン</th><td><input type="password" name="access_token" class="regular-text" autocomplete="new-password" placeholder="変更するときだけ入力"><p class="description">保存済みトークンは画面に再表示しません。</p></td></tr>
                    <tr><th>InstagramユーザーID</th><td><input type="text" name="ig_user_id" class="regular-text" value="<?php echo esc_attr($o['ig_user_id']); ?>"><p class="description">空欄の場合は graph.instagram.com の me/media を使用します。</p></td></tr>
                    <tr><th>APIバージョン</th><td><input type="text" name="api_version" value="<?php echo esc_attr($o['api_version']); ?>" class="small-text"></td></tr>
                    <tr><th>表示件数</th><td><input type="number" name="limit" min="1" max="12" value="<?php echo (int)$o['limit']; ?>"></td></tr>
                    <tr><th>見出し</th><td><input type="text" name="heading" class="regular-text" value="<?php echo esc_attr($o['heading']); ?>"></td></tr>
                    <tr><th>プロフィールURL</th><td><input type="url" name="profile_url" class="regular-text" value="<?php echo esc_attr($o['profile_url']); ?>"></td></tr>
                </table>
                <?php submit_button('設定を保存'); ?>
            </form>
            <hr>
            <p><strong>最終取得：</strong><?php echo $o['last_fetch'] ? esc_html(wp_date('Y-m-d H:i:s', (int)$o['last_fetch'])) : '未取得'; ?></p>
            <?php if ($o['last_error']): ?><p style="color:#b32d2e"><strong>最新エラー：</strong><?php echo esc_html($o['last_error']); ?></p><?php endif; ?>
            <p><strong>保存件数：</strong><?php echo count((array)$o['items']); ?>件</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gos_instagram_refresh">
                <?php wp_nonce_field('gos_instagram_refresh'); ?>
                <?php submit_button('今すぐ取得', 'secondary'); ?>
            </form>
            <h2>設置用ショートコード</h2>
            <code>[garden_instagram_gallery]</code>
        </div>
        <?php
    }

    private static function apply_instagram_settings_input($options, $input) {
        $options['auto_home'] = !empty($input['auto_home']) ? 1 : 0;
        $token = isset($input['access_token']) ? trim((string)wp_unslash($input['access_token'])) : '';
        if ($token !== '') $options['access_token'] = sanitize_text_field($token);
        $options['ig_user_id'] = sanitize_text_field((string)($input['ig_user_id'] ?? ''));
        $version = sanitize_text_field((string)($input['api_version'] ?? 'v23.0'));
        $options['api_version'] = preg_match('/^v?\d+\.\d+$/', $version) ? (strpos($version, 'v') === 0 ? $version : 'v'.$version) : 'v23.0';
        $options['limit'] = max(1, min(12, absint($input['limit'] ?? 6)));
        $options['heading'] = sanitize_text_field((string)($input['heading'] ?? 'Instagram'));
        $options['profile_url'] = esc_url_raw((string)($input['profile_url'] ?? ''));
        return $options;
    }

    public static function instagram_save() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer('gos_instagram_save');
        $o = self::apply_instagram_settings_input(self::instagram_options(), $_POST);
        self::store_instagram_options($o);
        if (!wp_next_scheduled('gos_instagram_cron')) wp_schedule_event(time()+300, 'gos_six_hours', 'gos_instagram_cron');
        wp_safe_redirect(add_query_arg(['page'=>'gos-instagram-gallery','gos_instagram_status'=>'saved'], admin_url('options-general.php')));
        exit;
    }

    public static function instagram_refresh_action() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer('gos_instagram_refresh');
        $ok = self::instagram_refresh();
        wp_safe_redirect(add_query_arg(['page'=>'gos-instagram-gallery','gos_instagram_status'=>$ok?'refreshed':'error'], admin_url('options-general.php')));
        exit;
    }

    private static function normalize_instagram_items($data) {
        $items = [];
        foreach ((array)$data as $item) {
            if (!is_array($item) || empty($item['permalink'])) continue;
            $image = ($item['media_type'] ?? '') === 'VIDEO' ? ($item['thumbnail_url'] ?? '') : ($item['media_url'] ?? '');
            if ($image === '') continue;
            $items[] = [
                'id' => sanitize_text_field((string)($item['id'] ?? '')),
                'caption' => sanitize_textarea_field((string)($item['caption'] ?? '')),
                'media_type' => sanitize_key((string)($item['media_type'] ?? 'IMAGE')),
                'image_url' => esc_url_raw($image),
                'permalink' => esc_url_raw((string)$item['permalink']),
                'timestamp' => sanitize_text_field((string)($item['timestamp'] ?? '')),
            ];
            if (count($items) >= 12) break;
        }
        return $items;
    }

    private static function request_instagram_media($options) {
        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';
        if ($options['ig_user_id'] !== '') {
            $url = 'https://graph.facebook.com/' . rawurlencode($options['api_version']) . '/' . rawurlencode($options['ig_user_id']) . '/media';
        } else {
            $url = 'https://graph.instagram.com/' . rawurlencode($options['api_version']) . '/me/media';
        }
        $url = add_query_arg(['fields'=>$fields,'limit'=>max(12,(int)$options['limit'])], $url);
        return wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $options['access_token']],
        ]);
    }

    private static function parse_instagram_media_response($response) {
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($body) || !isset($body['data'])) {
            $msg = is_array($body) && !empty($body['error']['message']) ? $body['error']['message'] : 'HTTP ' . $code;
            return ['data' => [], 'error' => sanitize_text_field($msg)];
        }
        return ['data' => $body['data'], 'error' => ''];
    }

    public static function instagram_refresh() {
        $o = self::instagram_options();
        if (empty($o['access_token'])) {
            $o['last_error'] = 'アクセストークンが未設定です。';
            self::store_instagram_options($o);
            return false;
        }
        $response = self::request_instagram_media($o);
        if (is_wp_error($response)) {
            $o['last_error'] = $response->get_error_message();
            self::store_instagram_options($o);
            return false;
        }
        $parsed = self::parse_instagram_media_response($response);
        if ($parsed['error'] !== '') {
            $o['last_error'] = $parsed['error'];
            self::store_instagram_options($o);
            return false;
        }
        $items = self::normalize_instagram_items($parsed['data']);
        if (!$items) {
            $o['last_error'] = '表示できる投稿がありませんでした。';
            self::store_instagram_options($o);
            return false;
        }
        $o['items'] = $items;
        $o['last_fetch'] = time();
        $o['last_error'] = '';
        self::store_instagram_options($o);
        return true;
    }


    /**
     * Some legacy page templates output page content without applying the_content,
     * leaving the gallery shortcode visible as plain text. Replace only that token
     * after the page has rendered, without buffering or rewriting the full response.
     */
    /**
     * Japanese, English and Traditional Chinese access sections: use a two-column
     * information/map layout on desktop. Mobile remains vertically stacked.
     */
    public static function permanent_guide_frontend() {
        $config = self::permanent_guide_config();
        if (empty($config['enabled'])) return;
        $post_id = self::permanent_guide_post_id();
        if ($post_id <= 0) return;
        $url = get_permalink($post_id);
        if (!$url) return;

        $is_guide = self::is_permanent_guide_page();
        $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $show_card = (is_front_page() && !empty($config['show_home']))
            || (!$is_guide && !empty($config['show_news_archive']) && ($path === 'news' || is_post_type_archive() || is_home()));
        if (!$is_guide && !$show_card) return;

        $title = get_the_title($post_id);
        $eyebrow = trim((string)($config['eyebrow'] ?? ''));
        $card = '<aside class="gos-permanent-guide-card" aria-label="常設案内">';
        if ($eyebrow !== '') $card .= '<span class="gos-permanent-guide-card__eyebrow">' . esc_html($eyebrow) . '</span>';
        $card .= '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a></aside>';
        ?>
        <style id="gos-permanent-guide-style">
        .gos-permanent-guide-card{box-sizing:border-box;width:100%;max-width:900px;margin:20px auto 28px;padding:17px 20px;border:1px solid #d4d4d4;border-left:5px solid #9e1638;background:#faf8f8;line-height:1.6}
        .gos-permanent-guide-card__eyebrow{display:block;margin:0 0 3px;color:#666;font-size:13px}
        .gos-permanent-guide-card>a{display:inline-block;color:inherit!important;font-size:17px;font-weight:600;text-decoration:none!important}
        .gos-permanent-guide-card>a::after{content:' ›';font-weight:400}
        .gos-permanent-guide-card>a:hover{text-decoration:underline!important}
        .gos-permanent-guide-page .entry-meta,.gos-permanent-guide-page .post-meta,.gos-permanent-guide-page .article-meta,.gos-permanent-guide-page .article-date,.gos-permanent-guide-page .post-date,.gos-permanent-guide-page .single-post-navigation,.gos-permanent-guide-page .post-navigation,.gos-permanent-guide-page .nav-links,.gos-permanent-guide-page .sharedaddy,.gos-permanent-guide-page .share-buttons{display:none!important}
        .gos-permanent-guide-page .gos-permanent-guide__modified{display:block!important;margin:34px 0 0!important;padding-top:12px!important;border-top:1px solid #ddd!important;color:#777!important;font-size:12px!important;text-align:right!important}
        @media(max-width:600px){.gos-permanent-guide-card{width:calc(100% - 32px);margin:16px auto 24px;padding:14px 16px}.gos-permanent-guide-card>a{font-size:15px}}
        </style>
        <script id="gos-permanent-guide-script">
        (function(){
          var targetUrl=<?php echo wp_json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var cardHtml=<?php echo wp_json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var isGuide=<?php echo $is_guide ? 'true' : 'false'; ?>;
          var showCard=<?php echo $show_card ? 'true' : 'false'; ?>;
          var excludeFromList=<?php echo !empty($config['exclude_from_news_list']) ? 'true' : 'false'; ?>;
          function text(el){return String((el&&el.textContent)||'').replace(/\s+/g,'').trim()}
          function normalizedUrl(value){
            try{var u=new URL(value,location.href);return (u.origin+u.pathname).replace(/\/+$/,'')}catch(e){return String(value||'').replace(/[?#].*$/,'').replace(/\/+$/,'')}
          }
          function hideNewsChrome(){
            document.querySelectorAll('h1,h2,h3,h4').forEach(function(h){if(text(h)==='お知らせ')h.style.display='none'});
            document.querySelectorAll('nav,ol,ul,.breadcrumb,.breadcrumbs').forEach(function(root){
              if(!/HOME|ホーム/i.test(root.textContent||''))return;
              root.querySelectorAll('li,a,span').forEach(function(el){if(text(el)==='お知らせ'){var li=el.closest('li');(li||el).style.display='none'}});
            });
            document.querySelectorAll('time.entry-date,time.published,.entry-date,.post-date,.article-date').forEach(function(el){el.style.display='none'});
          }
          function removeDuplicateCards(){
            if(!excludeFromList)return;
            var wanted=normalizedUrl(targetUrl);
            document.querySelectorAll('a[href]').forEach(function(a){
              if(normalizedUrl(a.href)!==wanted || a.closest('.gos-permanent-guide-card'))return;
              var item=a.closest('article,.article02,.news-item,.post-item,.archive-item');
              if(!item){
                var li=a.closest('li');
                if(li && (/news|post|article|archive/i.test(li.className||'') || li.querySelector('time')))item=li;
              }
              if(item && !item.closest('header,nav,footer'))item.remove();
            });
          }
          function insertCard(){
            if(!showCard || document.querySelector('.gos-permanent-guide-card'))return;
            var heading=null;
            Array.prototype.some.call(document.querySelectorAll('h1,h2,h3,h4'),function(h){if(text(h)==='お知らせ'){heading=h;return true}return false});
            if(!heading)return;
            heading.insertAdjacentHTML('afterend',cardHtml);
          }
          function apply(){
            if(isGuide){hideNewsChrome();return}
            removeDuplicateCards();insertCard();
          }
          if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',apply,{once:true});else apply();
          window.addEventListener('load',apply,{once:true});
        })();
        </script>
        <?php
    }

    public static function japanese_access_layout() {
        if (is_admin() || !is_page(['access', 'english', 'chinese'])) return;
        ?>
        <style id="gos-japanese-access-layout-style">
        .gos-access-layout{
            box-sizing:border-box;
            margin:1.5em 0 0;
        }
        .gos-access-layout__info,
        .gos-access-layout__map{
            box-sizing:border-box;
            min-width:0;
        }
        .gos-access-layout__map iframe{
            display:block;
            width:100%!important;
            max-width:none!important;
            height:420px!important;
            border:0;
        }
        .gos-access-layout__map-link{
            margin:.7em 0 0;
            text-align:center;
        }
        @media(min-width:783px){
            .gos-access-layout{
                display:grid;
                grid-template-columns:minmax(260px,36%) minmax(0,64%);
                gap:34px;
                align-items:start;
            }
            body.gos-access-multilingual .gos-access-layout{
                max-width:none;
                grid-template-columns:minmax(340px,40%) minmax(0,60%);
                gap:48px;
            }
            body.gos-access-multilingual .gos-access-layout__info{
                min-width:0;
                overflow-wrap:anywhere;
            }
            .gos-access-layout__info > :first-child,
            .gos-access-layout__map > :first-child{
                margin-top:0!important;
            }
        }
        @media(max-width:782px){
            .gos-access-layout{
                display:block;
                margin-top:1em;
            }
            .gos-access-layout__map{
                margin-top:1.6em;
            }
            .gos-access-layout__map iframe{
                height:auto!important;
                min-height:280px;
            }
        }
        </style>
        <script id="gos-japanese-access-layout-script">
        (function(){
            function cleanText(el){
                return String((el&&el.textContent)||'').replace(/\s+/g,'').trim();
            }
            function follows(a,b){
                return !!(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);
            }
            function directChildWithin(node,ancestor){
                var current=node;
                while(current && current.parentElement!==ancestor){
                    current=current.parentElement;
                }
                return current;
            }
            function commonAncestor(a,b){
                var current=a;
                while(current && current!==document.body){
                    if(current.contains(b)) return current;
                    current=current.parentElement;
                }
                return null;
            }

            var path=location.pathname.replace(/\/+$/,'');
            var headings;
            if(path.endsWith('/english')){
                document.body.classList.add('gos-access-multilingual');
                headings=['ContactInformation'];
            }else if(path.endsWith('/chinese')){
                document.body.classList.add('gos-access-multilingual');
                headings=['交通與聯絡資訊','交通与联络资讯'];
            }else{
                headings=['交通・アクセス'];
            }

            var heading=null;
            Array.prototype.some.call(
                document.querySelectorAll('h1,h2,h3,h4,h5,p,strong,div'),
                function(el){
                    if(headings.indexOf(cleanText(el))!==-1){
                        heading=el;
                        return true;
                    }
                    return false;
                }
            );
            if(!heading)return;

            /*
             * The old multilingual pages do not share the Japanese page's
             * wrapper structure. Select the first map-like iframe appearing
             * after the access heading, while excluding Facebook/calendar embeds.
             */
            var mapIframe=null;
            Array.prototype.some.call(document.querySelectorAll('iframe'),function(frame){
                if(!follows(heading,frame)) return false;
                var src=String(frame.getAttribute('src')||'').toLowerCase();
                if(src.indexOf('facebook.com')!==-1 || src.indexOf('calendar.google')!==-1){
                    return false;
                }
                if(
                    src.indexOf('google.com/maps')!==-1 ||
                    src.indexOf('maps.google')!==-1 ||
                    src.indexOf('google.co.jp/maps')!==-1 ||
                    frame.width || frame.height
                ){
                    mapIframe=frame;
                    return true;
                }
                return false;
            });
            if(!mapIframe)return;

            var contentRoot=commonAncestor(heading,mapIframe);
            if(!contentRoot || contentRoot===document.body)return;

            /*
             * On the multilingual pages both elements can initially resolve to
             * the same broad wrapper. Descend through that shared wrapper until
             * the heading area and map area become separate sibling blocks.
             */
            var headingBlock=null;
            var mapBlock=null;
            while(contentRoot && contentRoot!==document.body){
                headingBlock=directChildWithin(heading,contentRoot);
                mapBlock=directChildWithin(mapIframe,contentRoot);

                if(!headingBlock || !mapBlock)return;
                if(headingBlock!==mapBlock)break;

                contentRoot=headingBlock;
            }

            if(
                !contentRoot ||
                contentRoot===document.body ||
                !headingBlock ||
                !mapBlock ||
                headingBlock===mapBlock
            )return;

            if(contentRoot.querySelector(':scope > .gos-access-layout'))return;

            var nodes=[];
            var current=headingBlock.nextSibling;
            while(current && current!==mapBlock){
                var next=current.nextSibling;
                nodes.push(current);
                current=next;
            }
            if(current!==mapBlock)return;

            var layout=document.createElement('div');
            layout.className='gos-access-layout';
            var info=document.createElement('div');
            info.className='gos-access-layout__info';
            var map=document.createElement('div');
            map.className='gos-access-layout__map';

            contentRoot.insertBefore(layout,headingBlock.nextSibling);
            layout.appendChild(info);
            layout.appendChild(map);

            nodes.forEach(function(node){
                info.appendChild(node);
            });
            map.appendChild(mapBlock);

            if(document.body.classList.contains('gos-access-multilingual')){
                var positionLayout=function(){
                    if(window.innerWidth<783){
                        layout.style.width='';
                        layout.style.marginLeft='';
                        return;
                    }

                    layout.style.width='';
                    layout.style.marginLeft='';

                    var desiredWidth=Math.min(1120,Math.max(760,window.innerWidth-96));
                    layout.style.width=desiredWidth+'px';

                    var rect=layout.getBoundingClientRect();
                    var targetLeft=Math.max(48,(window.innerWidth-desiredWidth)/2);
                    var shift=targetLeft-rect.left;

                    layout.style.marginLeft=shift+'px';
                };

                positionLayout();
                window.addEventListener('resize',positionLayout,{passive:true});
            }

            /*
             * Move the "larger map" link into the map column when it is the
             * next nearby element. Facebook remains outside the layout.
             */
            var after=layout.nextSibling;
            while(after && after.nodeType===3 && !after.nodeValue.trim()){
                var blank=after;
                after=after.nextSibling;
                blank.parentNode.removeChild(blank);
            }
            if(after && after.nodeType===1){
                var link=after.matches('a') ? after : after.querySelector('a');
                var label=cleanText(link);
                if(
                    link &&
                    (
                        /大きな地図で見る/.test(label) ||
                        /ViewLargerMap/i.test(label) ||
                        /在較大的地圖上查看|在较大的地图上查看|在更大的地圖上查看|在更大的地图上查看/.test(label)
                    )
                ){
                    after.classList.add('gos-access-layout__map-link');
                    map.appendChild(after);
                }
            }

            /*
             * On the Chinese page the larger-map button is not adjacent to the
             * iframe; it remains inside the information column. Find that
             * existing button and move its containing block below the map.
             */
            if(path.endsWith('/chinese')){
                var chineseMapButton=null;
                Array.prototype.some.call(contentRoot.querySelectorAll('a'),function(a){
                    var label=cleanText(a);
                    if(
                        /在較大的地圖上查看|在较大的地图上查看|在更大的地圖上查看|在更大的地图上查看/.test(label)
                    ){
                        chineseMapButton=a;
                        return true;
                    }
                    return false;
                });

                if(chineseMapButton){
                    var buttonBlock=chineseMapButton;
                    while(
                        buttonBlock.parentElement &&
                        buttonBlock.parentElement!==contentRoot &&
                        buttonBlock.parentElement!==info &&
                        buttonBlock.parentElement!==map
                    ){
                        buttonBlock=buttonBlock.parentElement;
                    }

                    if(buttonBlock!==map && !map.contains(buttonBlock)){
                        buttonBlock.classList.add('gos-access-layout__map-link');
                        map.appendChild(buttonBlock);
                    }
                }
            }
        })();
        </script>
        <?php
    }

    public static function instagram_gallery_fallback() {
        if (is_admin()) return;

        $o = self::instagram_options();
        $html = self::shortcode_instagram_gallery(is_front_page() && !empty($o['auto_home']) ? ['limit' => 12] : []);

        $page_language = self::information_page_language();
        $auto_multilingual = in_array($page_language, ['en', 'zh-Hant'], true);
        $multilingual_label = $page_language === 'zh-Hant' ? '在 Instagram 查看更多' : 'View more on Instagram';
        $multilingual_html = $auto_multilingual
            ? self::shortcode_instagram_gallery([
                'limit' => 12,
                'heading' => 'Instagram',
                'variant' => 'multilingual',
                'more_label' => $multilingual_label,
            ])
            : '';

        if ($html === '' && $multilingual_html === '') return;

        $json = wp_json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $multilingual_json = wp_json_encode($multilingual_html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $auto_home = is_front_page() && !empty($o['auto_home']);
        ?>
        <script id="gos-instagram-gallery-fallback">
        (function(){
            var html=<?php echo $json; ?>;
            var multilingualHtml=<?php echo $multilingual_json; ?>;
            var autoHome=<?php echo $auto_home ? 'true' : 'false'; ?>;
            var autoMultilingual=<?php echo $auto_multilingual ? 'true' : 'false'; ?>;
            var pattern=/\[garden_instagram_gallery(?:\s+[^\]]*)?\]/i;

            function makeFragment(markup){
                var holder=document.createElement('div');
                holder.innerHTML=markup;
                var frag=document.createDocumentFragment();
                while(holder.firstChild)frag.appendChild(holder.firstChild);
                return frag;
            }

            // Expand visible shortcode text in legacy templates.
            var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{
                acceptNode:function(node){
                    if(!node.nodeValue||node.nodeValue.indexOf('[garden_instagram_gallery')===-1){
                        return NodeFilter.FILTER_REJECT;
                    }
                    var p=node.parentElement;
                    if(!p||/^(SCRIPT|STYLE|TEXTAREA|CODE|PRE)$/i.test(p.tagName)){
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            });
            var nodes=[];
            while(walker.nextNode())nodes.push(walker.currentNode);
            nodes.forEach(function(node){
                var text=node.nodeValue;
                var match=text.match(pattern);
                if(!match)return;
                var before=text.slice(0,match.index);
                var after=text.slice(match.index+match[0].length);
                var frag=document.createDocumentFragment();
                if(before)frag.appendChild(document.createTextNode(before));
                frag.appendChild(makeFragment(html));
                if(after)frag.appendChild(document.createTextNode(after));
                node.parentNode.replaceChild(frag,node);
            });

            if(
                autoMultilingual &&
                multilingualHtml &&
                !document.querySelector('.gos-instagram-gallery--multilingual')
            ){
                var accessLayout=document.querySelector('.gos-access-layout');
                if(accessLayout && accessLayout.parentNode){
                    var multilingualFragment=makeFragment(multilingualHtml);
                    accessLayout.parentNode.insertBefore(
                        multilingualFragment,
                        accessLayout.nextSibling
                    );

                    var multilingualGallery=document.querySelector('.gos-instagram-gallery--multilingual');
                    if(multilingualGallery){
                        var positionMultilingualGallery=function(){
                            if(window.innerWidth<601){
                                multilingualGallery.style.width='';
                                multilingualGallery.style.marginLeft='';
                                return;
                            }

                            multilingualGallery.style.width='900px';
                            multilingualGallery.style.maxWidth='calc(100vw - 64px)';
                            multilingualGallery.style.marginLeft='';

                            var rect=multilingualGallery.getBoundingClientRect();
                            var desiredWidth=Math.min(900,window.innerWidth-64);
                            var targetLeft=(window.innerWidth-desiredWidth)/2;
                            multilingualGallery.style.width=desiredWidth+'px';
                            multilingualGallery.style.marginLeft=(targetLeft-rect.left)+'px';
                        };

                        positionMultilingualGallery();
                        window.addEventListener('resize',positionMultilingualGallery,{passive:true});
                    }
                }
            }

            if(!autoHome||document.querySelector('.gos-instagram-gallery'))return;

            function normalizedText(el){
                return String((el&&el.textContent)||'').replace(/\s+/g,'').trim();
            }

            var moreLink=null;
            Array.prototype.some.call(document.querySelectorAll('a'),function(a){
                var label=normalizedText(a);
                var href=String(a.getAttribute('href')||'');
                if(label==='もっと見る' && (/news|information|お知らせ/i.test(href) || href)){
                    moreLink=a;
                    return true;
                }
                return false;
            });

            var newsBlock=null;
            if(moreLink){
                var node=moreLink;
                for(var i=0;i<8 && node && node!==document.body;i++,node=node.parentElement){
                    var heading=node.querySelector && node.querySelector('h1,h2,h3,h4');
                    if(heading && normalizedText(heading)==='お知らせ'){
                        newsBlock=node;
                        break;
                    }
                }
                if(!newsBlock){
                    newsBlock=moreLink.closest('section,article,.index_news,.news,.news_list,.content_inner') || moreLink.parentElement;
                }
            }

            if(!newsBlock){
                Array.prototype.some.call(document.querySelectorAll('h1,h2,h3,h4'),function(h){
                    if(normalizedText(h)!=='お知らせ')return false;
                    newsBlock=h.closest('section,article,.index_news,.news,.news_list,.content_inner') || h.parentElement;
                    return true;
                });
            }

            if(!newsBlock || !newsBlock.parentNode)return;
            newsBlock.parentNode.insertBefore(makeFragment(html),newsBlock.nextSibling);
        })();
        </script>
        <?php
    }

    public static function instagram_lightbox_assets() {
        if (is_admin()) return;
        $o = self::instagram_options();
        if (empty($o['items'])) return;
        $lang = self::information_page_language();
        if ($lang === 'en') {
            $lightbox_labels = [
                'dialog' => 'Instagram image preview',
                'close' => 'Close',
            ];
        } elseif ($lang === 'zh-Hant') {
            $lightbox_labels = [
                'dialog' => 'Instagram 圖片放大顯示',
                'close' => '關閉',
            ];
        } else {
            $lightbox_labels = [
                'dialog' => 'Instagram画像拡大表示',
                'close' => '閉じる',
            ];
        }
        ?>
        <style id="gos-instagram-lightbox-style">
        .gos-instagram-lightbox{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:28px;background:rgba(0,0,0,.82);box-sizing:border-box}.gos-instagram-lightbox[hidden]{display:none!important}.gos-instagram-lightbox__dialog{position:relative;max-width:min(92vw,980px);max-height:94vh;padding:18px;background:#fff;box-shadow:0 12px 46px rgba(0,0,0,.35);box-sizing:border-box}.gos-instagram-lightbox__image{display:block;max-width:100%;max-height:calc(94vh - 36px);width:auto;height:auto;margin:0 auto;object-fit:contain}.gos-instagram-lightbox__close{position:fixed;top:14px;right:18px;width:52px;height:52px;border:3px solid #222;border-radius:50%;background:#fff;color:#222;font-size:38px;line-height:42px;font-family:Arial,sans-serif;cursor:pointer;box-shadow:0 2px 0 rgba(255,255,255,.8);z-index:2}.gos-instagram-lightbox-open{overflow:hidden!important}@media(max-width:600px){.gos-instagram-lightbox{padding:14px}.gos-instagram-lightbox__dialog{max-width:100%;max-height:92vh;padding:10px}.gos-instagram-lightbox__image{max-height:calc(92vh - 20px)}.gos-instagram-lightbox__close{top:8px;right:8px;width:44px;height:44px;font-size:32px;line-height:34px;border-width:2px}}
        </style>
        <script id="gos-instagram-lightbox-script">
        (function(){
            if(window.GOSInstagramLightboxReady)return;
            window.GOSInstagramLightboxReady=true;
            var labels=<?php echo wp_json_encode($lightbox_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var modal=null,lastFocused=null;
            function ensure(){
                if(modal)return modal;
                modal=document.createElement('div');
                modal.className='gos-instagram-lightbox';
                modal.hidden=true;
                modal.setAttribute('role','dialog');
                modal.setAttribute('aria-modal','true');
                modal.setAttribute('aria-label',labels.dialog);
                modal.innerHTML='<button type="button" class="gos-instagram-lightbox__close">×</button><div class="gos-instagram-lightbox__dialog"><img class="gos-instagram-lightbox__image" alt=""></div>';
                modal.querySelector('.gos-instagram-lightbox__close').setAttribute('aria-label',labels.close);
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

    public static function shortcode_instagram_gallery($atts) {
        $o = self::instagram_options();
        // Placing the shortcode is itself an explicit instruction to display.
        // Do not suppress it with the separate admin enable flag.
        $atts = shortcode_atts([
            'limit' => $o['limit'],
            'heading' => $o['heading'],
            'variant' => '',
            'more_label' => 'Instagramでもっと見る',
        ], $atts, 'garden_instagram_gallery');
        $limit = max(1, min(12, absint($atts['limit'])));
        $variant = sanitize_html_class((string)$atts['variant']);
        $section_class = 'gos-instagram-gallery' . ($variant !== '' ? ' gos-instagram-gallery--' . $variant : '');
        $items = array_slice((array)$o['items'], 0, $limit);
        if (!$items) return current_user_can('manage_options') ? '<p>Instagramギャラリー：投稿未取得</p>' : '';
        ob_start(); ?>
        <section class="<?php echo esc_attr($section_class); ?>" aria-label="Instagram">
            <?php if ((string)$atts['heading'] !== ''): ?><h2 class="gos-instagram-gallery__heading"><?php echo esc_html($atts['heading']); ?></h2><?php endif; ?>
            <div class="gos-instagram-gallery__grid">
                <?php foreach ($items as $item): ?>
                    <a class="gos-instagram-gallery__item" href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer" data-gos-instagram-lightbox data-gos-image="<?php echo esc_url($item['image_url']); ?>">
                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr(wp_trim_words((string)$item['caption'], 12, '')); ?>" loading="lazy" decoding="async">
                        <?php if (($item['media_type'] ?? '') === 'VIDEO'): ?><span class="gos-instagram-gallery__video" aria-hidden="true">▶</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($o['profile_url']): ?><p class="gos-instagram-gallery__more"><a href="<?php echo esc_url($o['profile_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string)$atts['more_label']); ?></a></p><?php endif; ?>
        </section>
        <style>
        .gos-instagram-gallery{margin:46px auto 54px;max-width:900px;width:calc(100% - 32px);padding:0;box-sizing:border-box}.gos-instagram-gallery--multilingual{margin-top:46px}.gos-instagram-gallery--multilingual .gos-instagram-gallery__heading{text-align:center}.gos-instagram-gallery--multilingual .gos-instagram-gallery__more{text-align:center}.gos-instagram-gallery__heading{text-align:center;margin:0 0 22px;font-size:24px;font-weight:400;line-height:1.5;color:inherit}.gos-instagram-gallery__grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:7px}.gos-instagram-gallery__item{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;background:#eee}.gos-instagram-gallery__item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .25s ease}.gos-instagram-gallery__item:hover img{transform:scale(1.03)}.gos-instagram-gallery__video{position:absolute;right:8px;top:6px;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.7);font-size:16px}.gos-instagram-gallery__more{text-align:center;margin:22px 0 0}.gos-instagram-gallery__more a{display:inline-block;min-width:180px;padding:11px 28px;background:#b20b38;color:#fff!important;text-decoration:none!important;font-size:15px;line-height:1.4;box-sizing:border-box}.gos-instagram-gallery__more a:hover{opacity:.86}@media(max-width:600px){.gos-instagram-gallery{max-width:250px;width:calc(100% - 48px);margin:34px auto 42px}.gos-instagram-gallery--multilingual{margin-top:34px}.gos-instagram-gallery__heading{font-size:20px;margin-bottom:18px}.gos-instagram-gallery__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}.gos-instagram-gallery__item:nth-child(n+7){display:none}.gos-instagram-gallery__more{margin-top:18px}.gos-instagram-gallery__more a{min-width:174px;padding:11px 20px;font-size:15px}}
        </style>
        <?php return ob_get_clean();
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
