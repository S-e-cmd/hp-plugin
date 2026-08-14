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
    text = text.replace('Version: 3.2.86', 'Version: 3.2.87', 1)
    text = text.replace("const VERSION = '3.2.86';", "const VERSION = '3.2.87';", 1)
    text = text.replace('Garden Opening Status 3.2.86 multilingual SEO active', 'Garden Opening Status 3.2.87 multilingual SEO active', 1)

    old_hooks = """        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_multilingual_schema'], 30);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);\n"""
    new_hooks = """        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_multilingual_schema'], 30);\n        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_japanese_home_schema'], 35);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_japanese_home_title'], 105);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_japanese_home_description'], 105);\n"""
    if old_hooks not in text:
        raise SystemExit(f'hook anchor not found in {path}')
    text = text.replace(old_hooks, new_hooks, 1)

    old_social_hooks = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n"""
    new_social_hooks = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_japanese_home_facebook_tags'], 105);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_japanese_home_twitter_tags'], 105);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n"""
    if old_social_hooks not in text:
        raise SystemExit(f'social hook anchor not found in {path}')
    text = text.replace(old_social_hooks, new_social_hooks, 1)

    old_og_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    new_og_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    if old_og_gate not in text:
        raise SystemExit(f'OG start gate not found in {path}')
    text = text.replace(old_og_gate, new_og_gate, 1)

    old_filter_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return $html;\n"""
    new_filter_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return $html;\n"""
    if old_filter_gate not in text:
        raise SystemExit(f'OG filter gate not found in {path}')
    text = text.replace(old_filter_gate, new_filter_gate, 1)

    old_desc_gate = """        $language = self::information_page_language();\n        if ($language === '' || $language === 'en' || $language === 'zh-Hant') return;\n        ob_start([__CLASS__, 'filter_description_output']);\n"""
    new_desc_gate = """        $language = self::information_page_language();\n        if ($language === '' || $language === 'ja' || $language === 'en' || $language === 'zh-Hant') return;\n        ob_start([__CLASS__, 'filter_description_output']);\n"""
    if old_desc_gate not in text:
        raise SystemExit(f'description gate not found in {path}')
    text = text.replace(old_desc_gate, new_desc_gate, 1)

    anchor = """    public static function filter_aioseo_multilingual_description($description) {\n        $language = self::information_page_language();\n        $localized = self::multilingual_page_description($language);\n        return $localized !== '' ? $localized : $description;\n    }\n\n"""
    addition = """    public static function filter_aioseo_multilingual_description($description) {\n        $language = self::information_page_language();\n        $localized = self::multilingual_page_description($language);\n        return $localized !== '' ? $localized : $description;\n    }\n\n    /**\n     * Japanese home-page search metadata. This changes head metadata only;\n     * visible page content, headings, and layout remain untouched.\n     */\n    private static function japanese_home_seo_config() {\n        if (!is_front_page() || self::information_page_language() !== 'ja') return [];\n        return [\n            'title' => '東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑',\n            'description' => '東京・上野公園にある上野東照宮ぼたん苑。回遊形式の日本庭園で、春と冬の牡丹、秋のダリアなど季節の花を楽しめます。上野観光・東京の庭園散策にもおすすめです。',\n            'image' => home_url('/wp-content/uploads/2021/03/main1_sp.png'),\n            'image_width' => '1450',\n            'image_height' => '860',\n        ];\n    }\n\n    public static function filter_aioseo_japanese_home_title($title) {\n        $seo = self::japanese_home_seo_config();\n        return !empty($seo['title']) ? $seo['title'] : $title;\n    }\n\n    public static function filter_aioseo_japanese_home_description($description) {\n        $seo = self::japanese_home_seo_config();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n    public static function filter_aioseo_japanese_home_facebook_tags($tags) {\n        $seo = self::japanese_home_seo_config();\n        if (!$seo || !is_array($tags)) return $tags;\n        $tags['og:locale'] = 'ja_JP';\n        $tags['og:type'] = 'website';\n        $tags['og:url'] = home_url('/');\n        $tags['og:title'] = $seo['title'];\n        $tags['og:description'] = $seo['description'];\n        $tags['og:image'] = $seo['image'];\n        $tags['og:image:secure_url'] = $seo['image'];\n        $tags['og:image:width'] = $seo['image_width'];\n        $tags['og:image:height'] = $seo['image_height'];\n        return $tags;\n    }\n\n    public static function filter_aioseo_japanese_home_twitter_tags($tags) {\n        $seo = self::japanese_home_seo_config();\n        if (!$seo || !is_array($tags)) return $tags;\n        $tags['twitter:title'] = $seo['title'];\n        $tags['twitter:description'] = $seo['description'];\n        $tags['twitter:image'] = $seo['image'];\n        return $tags;\n    }\n\n    private static function localize_aioseo_japanese_home_schema_node(&$node, $seo) {\n        if (!is_array($node)) return;\n        $types = [];\n        if (isset($node['@type'])) {\n            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];\n        }\n        if (in_array('WebPage', $types, true)) {\n            $node['name'] = $seo['title'];\n            $node['description'] = $seo['description'];\n            $node['inLanguage'] = 'ja';\n        }\n        foreach ($node as &$child) {\n            if (is_array($child)) self::localize_aioseo_japanese_home_schema_node($child, $seo);\n        }\n        unset($child);\n    }\n\n    public static function filter_aioseo_japanese_home_schema($graphs) {\n        $seo = self::japanese_home_seo_config();\n        if (!$seo || !is_array($graphs)) return $graphs;\n        self::localize_aioseo_japanese_home_schema_node($graphs, $seo);\n        return $graphs;\n    }\n\n"""
    if anchor not in text:
        raise SystemExit(f'multilingual description anchor not found in {path}')
    text = text.replace(anchor, addition, 1)

    path.write_text(text, encoding='utf-8')

readme_entry = """開催情報・開催状況管理 3.2.87\n\n3.2.87:\n- 日本語トップページの表示本文を変更せず、検索向けtitle / descriptionを「東京・上野・日本庭園・季節の花」文脈へ調整\n- 日本語トップのOG / Twitter / AIOSEO WebPage schemaを同じtitle / descriptionへ統一\n- 日本語トップの旧テーマOGを除去し、AIOSEOをheadメタ情報の正本に統一\n- 日本語トップの共有画像は既存 main1_sp.png (1450x860) を使用\n- 会期ページ、英語・繁体字ページ、本文・見出し・レイアウトには変更なし\n\n"""
for path in README_PATHS:
    text = path.read_text(encoding='utf-8')
    if not text.startswith('開催情報・開催状況管理 3.2.86'):
        raise SystemExit(f'unexpected readme head in {path}')
    path.write_text(readme_entry + text, encoding='utf-8')
