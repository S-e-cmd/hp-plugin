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
    text = text.replace('Version: 3.2.87', 'Version: 3.2.88', 1)
    text = text.replace("const VERSION = '3.2.87';", "const VERSION = '3.2.88';", 1)
    text = text.replace('Garden Opening Status 3.2.87 multilingual SEO active', 'Garden Opening Status 3.2.88 multilingual SEO active', 1)

    old_hooks = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_japanese_home_title'], 105);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_japanese_home_description'], 105);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);\n"""
    new_hooks = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_japanese_home_title'], 105);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_japanese_home_description'], 105);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_schedule_index_title'], 106);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_schedule_index_description'], 106);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);\n"""
    if old_hooks not in text:
        raise SystemExit(f'title hook anchor not found in {path}')
    text = text.replace(old_hooks, new_hooks, 1)

    old_schema = """        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_japanese_home_schema'], 35);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n"""
    new_schema = """        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_japanese_home_schema'], 35);\n        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_schedule_index_schema'], 36);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n"""
    if old_schema not in text:
        raise SystemExit(f'schema hook anchor not found in {path}')
    text = text.replace(old_schema, new_schema, 1)

    old_social = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_japanese_home_facebook_tags'], 105);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_japanese_home_twitter_tags'], 105);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n"""
    new_social = """        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_japanese_home_facebook_tags'], 105);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_japanese_home_twitter_tags'], 105);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_schedule_index_facebook_tags'], 106);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_schedule_index_twitter_tags'], 106);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n"""
    if old_social not in text:
        raise SystemExit(f'social hook anchor not found in {path}')
    text = text.replace(old_social, new_social, 1)

    old_og_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    new_og_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        $is_schedule_index = is_page('schedule');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    if old_og_gate not in text:
        raise SystemExit(f'OG start gate not found in {path}')
    text = text.replace(old_og_gate, new_og_gate, 1)

    old_filter_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return $html;\n"""
    new_filter_gate = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        $is_schedule_index = is_page('schedule');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return $html;\n"""
    if old_filter_gate not in text:
        raise SystemExit(f'OG filter gate not found in {path}')
    text = text.replace(old_filter_gate, new_filter_gate, 1)

    anchor = """    public static function filter_aioseo_japanese_home_schema($graphs) {\n        $seo = self::japanese_home_seo_config();\n        if (!$seo || !is_array($graphs)) return $graphs;\n        self::localize_aioseo_japanese_home_schema_node($graphs, $seo);\n        return $graphs;\n    }\n\n"""
    addition = anchor + """    /** Search metadata for the fixed seasonal schedule index page. */\n    private static function schedule_index_seo_config() {\n        if (!is_page('schedule')) return [];\n        return [\n            'title' => '東京・上野で楽しむ季節の花｜春の牡丹・秋のダリア・冬の牡丹',\n            'description' => '東京・上野の上野東照宮ぼたん苑では、春の牡丹、秋のダリア、冬咲き牡丹を季節ごとに公開しています。各会期の開催情報、開苑時期、詳細をご案内します。',\n        ];\n    }\n\n    public static function filter_aioseo_schedule_index_title($title) {\n        $seo = self::schedule_index_seo_config();\n        return !empty($seo['title']) ? $seo['title'] : $title;\n    }\n\n    public static function filter_aioseo_schedule_index_description($description) {\n        $seo = self::schedule_index_seo_config();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n    public static function filter_aioseo_schedule_index_facebook_tags($tags) {\n        $seo = self::schedule_index_seo_config();\n        if (!$seo || !is_array($tags)) return $tags;\n        $tags['og:locale'] = 'ja_JP';\n        $tags['og:url'] = home_url('/schedule/');\n        $tags['og:title'] = $seo['title'];\n        $tags['og:description'] = $seo['description'];\n        return $tags;\n    }\n\n    public static function filter_aioseo_schedule_index_twitter_tags($tags) {\n        $seo = self::schedule_index_seo_config();\n        if (!$seo || !is_array($tags)) return $tags;\n        $tags['twitter:title'] = $seo['title'];\n        $tags['twitter:description'] = $seo['description'];\n        return $tags;\n    }\n\n    private static function localize_aioseo_schedule_index_schema_node(&$node, $seo) {\n        if (!is_array($node)) return;\n        $types = [];\n        if (isset($node['@type'])) {\n            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];\n        }\n        if (in_array('WebPage', $types, true) || in_array('CollectionPage', $types, true)) {\n            $node['name'] = $seo['title'];\n            $node['description'] = $seo['description'];\n            $node['inLanguage'] = 'ja';\n        }\n        foreach ($node as &$child) {\n            if (is_array($child)) self::localize_aioseo_schedule_index_schema_node($child, $seo);\n        }\n        unset($child);\n    }\n\n    public static function filter_aioseo_schedule_index_schema($graphs) {\n        $seo = self::schedule_index_seo_config();\n        if (!$seo || !is_array($graphs)) return $graphs;\n        self::localize_aioseo_schedule_index_schema_node($graphs, $seo);\n        return $graphs;\n    }\n\n"""
    if anchor not in text:
        raise SystemExit(f'home schema anchor not found in {path}')
    text = text.replace(anchor, addition, 1)
    path.write_text(text, encoding='utf-8')

entry = """開催情報・開催状況管理 3.2.88\n\n3.2.88:\n- 会期一覧 /schedule/ の表示本文を変更せず、title / descriptionを「東京・上野・季節の花・牡丹・ダリア」文脈へ調整\n- /schedule/ のOG / Twitter / AIOSEO WebPage・CollectionPage schemaを同じtitle / descriptionへ同期\n- /schedule/ の旧テーマOGを除去しAIOSEOをheadメタ情報の正本に統一\n- 共有画像、各会期ページ、トップ、英語・繁体字、本文・見出し・レイアウトには変更なし\n\n"""
for path in README_PATHS:
    text = path.read_text(encoding='utf-8')
    if not text.startswith('開催情報・開催状況管理 3.2.87'):
        raise SystemExit(f'unexpected readme head in {path}')
    path.write_text(entry + text, encoding='utf-8')
