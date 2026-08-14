from pathlib import Path
P=[Path('garden-opening-status/garden-opening-status.php'),Path('plugins/garden-opening-status/garden-opening-status.php')]
for p in P:
 s=p.read_text()
 s=s.replace('Version: 3.2.92','Version: 3.2.93',1).replace("const VERSION = '3.2.92';","const VERSION = '3.2.93';",1).replace('Garden Opening Status 3.2.92 multilingual SEO active','Garden Opening Status 3.2.93 multilingual SEO active',1)
 a="        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_access_page_schema'], 37);\n"
 b=a+"        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_notice_page_schema'], 38);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_access_page_description'], 107);\n"
 b=a+"        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_notice_page_title'], 108);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_notice_page_description'], 108);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_access_page_twitter_tags'], 107);\n"
 b=a+"        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_notice_page_facebook_tags'], 108);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_notice_page_twitter_tags'], 108);\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_access_page = is_page('access');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page) return;\n"
 b="        $is_access_page = is_page('access');\n        $is_notice_page = self::is_notice_page();\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page) return;\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_access_page = is_page('access');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page) return $html;\n"
 b="        $is_access_page = is_page('access');\n        $is_notice_page = self::is_notice_page();\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page) return $html;\n"; assert a in s; s=s.replace(a,b,1)
 anchor="""    public static function filter_aioseo_access_page_schema($graphs) {
        $seo = self::access_page_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_access_page_schema_node($graphs, $seo);
        return $graphs;
    }

"""
 add=anchor+"""    /** Search metadata for the evergreen admissions notice post. */
    private static function is_notice_page() {
        if (!is_single()) return false;
        $post = get_queried_object();
        return $post instanceof WP_Post && (string)$post->post_name === 'notice';
    }

    private static function notice_page_seo_config() {
        if (!self::is_notice_page()) return [];
        return [
            'title' => '上野東照宮ぼたん苑の入苑案内｜料金・支払い・撮影・注意事項',
            'description' => '上野東照宮ぼたん苑のご入苑案内です。入苑料のお支払い、入苑券、撮影、駐車場・駐輪場など、ご来苑前に確認したい注意事項をご案内します。',
        ];
    }

    public static function filter_aioseo_notice_page_title($title) {
        $seo = self::notice_page_seo_config();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_notice_page_description($description) {
        $seo = self::notice_page_seo_config();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    public static function filter_aioseo_notice_page_facebook_tags($tags) {
        $seo = self::notice_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['og:locale'] = 'ja_JP';
        $tags['og:url'] = home_url('/news/notice/');
        $tags['og:title'] = $seo['title'];
        $tags['og:description'] = $seo['description'];
        return $tags;
    }

    public static function filter_aioseo_notice_page_twitter_tags($tags) {
        $seo = self::notice_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:description'] = $seo['description'];
        return $tags;
    }

    private static function localize_aioseo_notice_page_schema_node(&$node, $seo) {
        if (!is_array($node)) return;
        $types = [];
        if (isset($node['@type'])) $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        if (in_array('WebPage', $types, true) || in_array('CollectionPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        if (in_array('Article', $types, true) || in_array('BlogPosting', $types, true) || in_array('NewsArticle', $types, true)) {
            $node['headline'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        foreach ($node as &$child) if (is_array($child)) self::localize_aioseo_notice_page_schema_node($child, $seo);
        unset($child);
    }

    public static function filter_aioseo_notice_page_schema($graphs) {
        $seo = self::notice_page_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_notice_page_schema_node($graphs, $seo);
        return $graphs;
    }

"""; assert anchor in s; s=s.replace(anchor,add,1)
 p.write_text(s)
E="""開催情報・開催状況管理 3.2.93

3.2.93:
- 常設案内として運用している /news/notice/ の表示本文を変更せず、title / descriptionを「入苑案内・料金・支払い・撮影・注意事項」文脈へ調整
- /news/notice/ のOG / Twitter / AIOSEO WebPage・Article系schemaを同じtitle / descriptionへ同期
- /news/notice/ の旧テーマOGを除去しAIOSEOをheadメタ情報の正本に統一
- 記事の日付・著者等の既存schema情報、会期・日付連動、トップ、アクセス、会期一覧、春・秋・冬、英語・繁体字、本文・見出し・レイアウトには変更なし

"""
for p in [Path('garden-opening-status/readme.txt'),Path('plugins/garden-opening-status/readme.txt')]:
 s=p.read_text(); assert s.startswith('開催情報・開催状況管理 3.2.92'); p.write_text(E+s)
