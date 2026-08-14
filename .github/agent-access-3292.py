from pathlib import Path
P=[Path('garden-opening-status/garden-opening-status.php'),Path('plugins/garden-opening-status/garden-opening-status.php')]
for p in P:
 s=p.read_text()
 s=s.replace('Version: 3.2.91','Version: 3.2.92',1).replace("const VERSION = '3.2.91';","const VERSION = '3.2.92';",1).replace('Garden Opening Status 3.2.91 multilingual SEO active','Garden Opening Status 3.2.92 multilingual SEO active',1)
 a="        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_schedule_index_schema'], 36);\n"
 b=a+"        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_access_page_schema'], 37);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_schedule_index_description'], 106);\n"
 b=a+"        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_access_page_title'], 107);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_access_page_description'], 107);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_schedule_index_twitter_tags'], 106);\n"
 b=a+"        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_access_page_facebook_tags'], 107);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_access_page_twitter_tags'], 107);\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_schedule_index = is_page('schedule');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return;\n"
 b="        $is_schedule_index = is_page('schedule');\n        $is_access_page = is_page('access');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page) return;\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_schedule_index = is_page('schedule');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index) return $html;\n"
 b="        $is_schedule_index = is_page('schedule');\n        $is_access_page = is_page('access');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page) return $html;\n"; assert a in s; s=s.replace(a,b,1)
 anchor="""    public static function filter_aioseo_schedule_index_schema($graphs) {
        $seo = self::schedule_index_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_schedule_index_schema_node($graphs, $seo);
        return $graphs;
    }

"""
 add=anchor+"""    /** Search metadata for the Japanese access page. */
    private static function access_page_seo_config() {
        if (!is_page('access')) return [];
        return [
            'title' => '上野東照宮ぼたん苑へのアクセス｜上野駅・上野公園からの行き方',
            'description' => '東京・上野公園にある上野東照宮ぼたん苑へのアクセスをご案内します。上野駅からの行き方、周辺交通、所在地など、来苑前に確認したい情報をまとめています。',
        ];
    }

    public static function filter_aioseo_access_page_title($title) {
        $seo = self::access_page_seo_config();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_access_page_description($description) {
        $seo = self::access_page_seo_config();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    public static function filter_aioseo_access_page_facebook_tags($tags) {
        $seo = self::access_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['og:locale'] = 'ja_JP';
        $tags['og:url'] = home_url('/access/');
        $tags['og:title'] = $seo['title'];
        $tags['og:description'] = $seo['description'];
        return $tags;
    }

    public static function filter_aioseo_access_page_twitter_tags($tags) {
        $seo = self::access_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:description'] = $seo['description'];
        return $tags;
    }

    private static function localize_aioseo_access_page_schema_node(&$node, $seo) {
        if (!is_array($node)) return;
        $types = [];
        if (isset($node['@type'])) $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        if (in_array('WebPage', $types, true) || in_array('CollectionPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        foreach ($node as &$child) if (is_array($child)) self::localize_aioseo_access_page_schema_node($child, $seo);
        unset($child);
    }

    public static function filter_aioseo_access_page_schema($graphs) {
        $seo = self::access_page_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_access_page_schema_node($graphs, $seo);
        return $graphs;
    }

"""; assert anchor in s; s=s.replace(anchor,add,1)
 p.write_text(s)
E="""開催情報・開催状況管理 3.2.92

3.2.92:
- 日本語アクセスページ /access/ の表示本文を変更せず、title / descriptionを「上野駅・上野公園・行き方・アクセス」文脈へ調整
- /access/ のOG / Twitter / AIOSEO WebPage・CollectionPage schemaを同じtitle / descriptionへ同期
- /access/ の旧テーマOGを除去しAIOSEOをheadメタ情報の正本に統一
- 会期・日付連動、トップ、会期一覧、春・秋・冬、英語・繁体字、本文・見出し・レイアウトには変更なし

"""
for p in [Path('garden-opening-status/readme.txt'),Path('plugins/garden-opening-status/readme.txt')]:
 s=p.read_text(); assert s.startswith('開催情報・開催状況管理 3.2.91'); p.write_text(E+s)
