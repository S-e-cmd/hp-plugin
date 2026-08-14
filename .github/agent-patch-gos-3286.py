from pathlib import Path

PHP_PATHS = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
README_PATHS = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

for path in PHP_PATHS:
    text = path.read_text(encoding='utf-8')
    text = text.replace('Version: 3.2.85', 'Version: 3.2.86', 1)
    text = text.replace("const VERSION = '3.2.85';", "const VERSION = '3.2.86';", 1)
    text = text.replace('Garden Opening Status 3.2.85 multilingual SEO active', 'Garden Opening Status 3.2.86 multilingual SEO active', 1)

    old_hooks = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n"""
    new_hooks = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_event_page_twitter_tags'], 110);\n"""
    if old_hooks not in text:
        raise SystemExit(f'hook block not found in {path}')
    text = text.replace(old_hooks, new_hooks, 1)

    anchor = """    public static function filter_aioseo_event_page_description($description) {\n        $seo = self::confirmed_event_page_seo_data();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n"""
    addition = """    public static function filter_aioseo_event_page_description($description) {\n        $seo = self::confirmed_event_page_seo_data();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n    /**\n     * Use the seasonal page's featured image for social sharing. The same image\n     * already represents the page in AIOSEO's WebPage schema. Fall back to the\n     * site's established share image only when a seasonal page has no thumbnail.\n     */\n    private static function event_page_social_image_data() {\n        if (self::current_event_page_season() === '') return [];\n\n        $post_id = (int)get_queried_object_id();\n        if ($post_id > 0) {\n            $thumbnail_id = get_post_thumbnail_id($post_id);\n            if ($thumbnail_id) {\n                $image = wp_get_attachment_image_src($thumbnail_id, 'full');\n                if (is_array($image) && !empty($image[0])) {\n                    return [\n                        'url' => esc_url_raw((string)$image[0]),\n                        'width' => !empty($image[1]) ? (string)(int)$image[1] : '',\n                        'height' => !empty($image[2]) ? (string)(int)$image[2] : '',\n                    ];\n                }\n            }\n        }\n\n        return [\n            'url' => home_url('/wp-content/uploads/2021/03/main1_sp.png'),\n            'width' => '1450',\n            'height' => '860',\n        ];\n    }\n\n    public static function filter_aioseo_event_page_facebook_tags($tags) {\n        if (!is_array($tags)) return $tags;\n        $image = self::event_page_social_image_data();\n        if (empty($image['url'])) return $tags;\n\n        $tags['og:image'] = $image['url'];\n        $tags['og:image:secure_url'] = $image['url'];\n        if ($image['width'] !== '') $tags['og:image:width'] = $image['width'];\n        if ($image['height'] !== '') $tags['og:image:height'] = $image['height'];\n        return $tags;\n    }\n\n    public static function filter_aioseo_event_page_twitter_tags($tags) {\n        if (!is_array($tags)) return $tags;\n        $image = self::event_page_social_image_data();\n        if (empty($image['url'])) return $tags;\n\n        $tags['twitter:image'] = $image['url'];\n        return $tags;\n    }\n\n"""
    if anchor not in text:
        raise SystemExit(f'event SEO anchor not found in {path}')
    text = text.replace(anchor, addition, 1)
    path.write_text(text, encoding='utf-8')

readme_entry = """開催情報・開催状況管理 3.2.86\n\n3.2.86:\n- 春・秋・冬の固定会期ページでAIOSEOのOG / Twitter画像を会期ページのアイキャッチ画像へ統一\n- アイキャッチがない場合のみ既存の main1_sp.png をフォールバックとして使用\n- title / description、公開前後の会期制御、Event schemaには変更なし\n\n"""
for path in README_PATHS:
    text = path.read_text(encoding='utf-8')
    if not text.startswith('開催情報・開催状況管理 3.2.85'):
        raise SystemExit(f'unexpected readme head in {path}')
    path.write_text(readme_entry + text, encoding='utf-8')
