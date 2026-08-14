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

    old_hooks = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_event_page_description'], 110);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_event_page_twitter_tags'], 110);\n"""
    new_hooks = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_event_page_description'], 110);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_homepage_title'], 120);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_homepage_description'], 120);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_event_page_facebook_tags'], 110);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_event_page_twitter_tags'], 110);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_homepage_facebook_tags'], 120);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_homepage_twitter_tags'], 120);\n        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_homepage_schema'], 120);\n"""
    if old_hooks not in text:
        raise SystemExit(f'hook block not found in {path}')
    text = text.replace(old_hooks, new_hooks, 1)

    old_start = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    new_start = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        $is_japanese_front = $language === 'ja' && is_front_page();\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_japanese_front) return;\n        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);\n"""
    if old_start not in text:
        raise SystemExit(f'OG buffer start block not found in {path}')
    text = text.replace(old_start, new_start, 1)

    old_filter = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return $html;\n\n        $aioseo_marker = '<!-- All in One SEO';\n"""
    new_filter = """        $language = self::information_page_language();\n        $is_event_page = self::current_event_page_season() !== '';\n        $is_japanese_front = $language === 'ja' && is_front_page();\n        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_japanese_front) return $html;\n\n        $aioseo_marker = '<!-- All in One SEO';\n"""
    if old_filter not in text:
        raise SystemExit(f'OG filter block not found in {path}')
    text = text.replace(old_filter, new_filter, 1)

    anchor = """    public static function filter_aioseo_event_page_description($description) {\n        $seo = self::confirmed_event_page_seo_data();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n"""
    addition = """    public static function filter_aioseo_event_page_description($description) {\n        $seo = self::confirmed_event_page_seo_data();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n    /**\n     * Japanese homepage search metadata. This changes head metadata only; visible\n     * page content and layout are intentionally untouched.\n     */\n    private static function homepage_seo_data() {\n        if (!is_front_page() || self::information_page_language() !== 'ja') return [];\n        return [\n            'title' => '東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑',\n            'description' => '東京・上野公園にある上野東照宮ぼたん苑。回遊形式の日本庭園で、春と冬の牡丹、秋のダリアなど季節の花を楽しめます。上野観光・東京の庭園散策にもおすすめです。',\n        ];\n    }\n\n    public static function filter_aioseo_homepage_title($title) {\n        $seo = self::homepage_seo_data();\n        return !empty($seo['title']) ? $seo['title'] : $title;\n    }\n\n    public static function filter_aioseo_homepage_description($description) {\n        $seo = self::homepage_seo_data();\n        return !empty($seo['description']) ? $seo['description'] : $description;\n    }\n\n    public static function filter_aioseo_homepage_facebook_tags($tags) {\n        if (!is_array($tags)) return $tags;\n        $seo = self::homepage_seo_data();\n        if (!$seo) return $tags;\n        $tags['og:title'] = $seo['title'];\n        $tags['og:description'] = $seo['description'];\n        return $tags;\n    }\n\n    public static function filter_aioseo_homepage_twitter_tags($tags) {\n        if (!is_array($tags)) return $tags;\n        $seo = self::homepage_seo_data();\n        if (!$seo) return $tags;\n        $tags['twitter:title'] = $seo['title'];\n        $tags['twitter:description'] = $seo['description'];\n        return $tags;\n    }\n\n    private static function localize_aioseo_homepage_schema_node(&$node, $seo) {\n        if (!is_array($node)) return;\n        $types = [];\n        if (isset($node['@type'])) {\n            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];\n        }\n        if (in_array('WebPage', $types, true)) {\n            $node['name'] = $seo['title'];\n            $node['description'] = $seo['description'];\n            $node['inLanguage'] = 'ja';\n        }\n        foreach ($node as &$child) {\n            if (is_array($child)) self::localize_aioseo_homepage_schema_node($child, $seo);\n        }\n        unset($child);\n    }\n\n    public static function filter_aioseo_homepage_schema($graphs) {\n        $seo = self::homepage_seo_data();\n        if (!$seo || !is_array($graphs)) return $graphs;\n        self::localize_aioseo_homepage_schema_node($graphs, $seo);\n        return $graphs;\n    }\n\n"""
    if anchor not in text:
        raise SystemExit(f'event description anchor not found in {path}')
    text = text.replace(anchor, addition, 1)

    old_desc = """        $language = self::information_page_language();\n        if ($language === 'en' || $language === 'zh-Hant') return $html;\n        $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';\n\n        $patterns = [\n"""
    new_desc = """        $language = self::information_page_language();\n        if ($language === 'en' || $language === 'zh-Hant') return $html;\n        $homepage_seo = self::homepage_seo_data();\n        $description = !empty($homepage_seo['description'])\n            ? $homepage_seo['description']\n            : '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';\n\n        $patterns = [\n"""
    if old_desc not in text:
        raise SystemExit(f'description block not found in {path}')
    text = text.replace(old_desc, new_desc, 1)

    path.write_text(text, encoding='utf-8')

readme_entry = """開催情報・開催状況管理 3.2.87\n\n3.2.87:\n- 日本語トップページの表示本文・レイアウトを変更せず、head内のSEO情報だけを強化\n- titleを「東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑」に統一\n- meta / OG / Twitter descriptionへ東京・上野・日本庭園・季節の花の文脈を自然に追加\n- AIOSEO WebPage schemaのname / descriptionも同じ内容へ同期\n- 日本語トップのテーマ側重複OGを除去し、AIOSEOを正本化\n- 会期ページ、英語・繁体字ページ、表示本文には変更なし\n\n"""
for path in README_PATHS:
    text = path.read_text(encoding='utf-8')
    if not text.startswith('開催情報・開催状況管理 3.2.86'):
        raise SystemExit(f'unexpected readme head in {path}')
    path.write_text(readme_entry + text, encoding='utf-8')
