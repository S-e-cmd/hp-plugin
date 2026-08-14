from pathlib import Path
P=[Path('garden-opening-status/garden-opening-status.php'),Path('plugins/garden-opening-status/garden-opening-status.php')]
for p in P:
 s=p.read_text()
 s=s.replace('Version: 3.2.93','Version: 3.2.94',1).replace("const VERSION = '3.2.93';","const VERSION = '3.2.94';",1).replace('Garden Opening Status 3.2.93 multilingual SEO active','Garden Opening Status 3.2.94 multilingual SEO active',1)
 a="        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_notice_page_schema'], 38);\n"
 b=a+"        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_contact_page_schema'], 39);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_notice_page_description'], 108);\n"
 b=a+"        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_contact_page_title'], 109);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_contact_page_description'], 109);\n"; assert a in s; s=s.replace(a,b,1)
 a="        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_notice_page_twitter_tags'], 108);\n"
 b=a+"        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_contact_page_facebook_tags'], 109);\n        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_contact_page_twitter_tags'], 109);\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_notice_page = self::is_notice_page();\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page) return;\n"
 b="        $is_notice_page = self::is_notice_page();\n        $is_contact_page = is_page('contact');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page && !$is_contact_page) return;\n"; assert a in s; s=s.replace(a,b,1)
 a="        $is_notice_page = self::is_notice_page();\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page) return $html;\n"
 b="        $is_notice_page = self::is_notice_page();\n        $is_contact_page = is_page('contact');\n        if ($language !== 'ja' && $language !== 'en' && $language !== 'zh-Hant' && !$is_event_page && !$is_schedule_index && !$is_access_page && !$is_notice_page && !$is_contact_page) return $html;\n"; assert a in s; s=s.replace(a,b,1)
 anchor="""    public static function filter_aioseo_notice_page_schema($graphs) {
        $seo = self::notice_page_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_notice_page_schema_node($graphs, $seo);
        return $graphs;
    }

"""
 add=anchor+"""    /** Search metadata for the Japanese contact page. */
    private static function contact_page_seo_config() {
        if (!is_page('contact')) return [];
        return [
            'title' => '上野東照宮ぼたん苑へのお問い合わせ｜入苑・取材・各種ご相談',
            'description' => '上野東照宮ぼたん苑へのお問い合わせはこちらから。入苑に関するご質問、取材・メディア関連、その他のご相談についてご案内しています。',
        ];
    }

    public static function filter_aioseo_contact_page_title($title) {
        $seo = self::contact_page_seo_config();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_contact_page_description($description) {
        $seo = self::contact_page_seo_config();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

    public static function filter_aioseo_contact_page_facebook_tags($tags) {
        $seo = self::contact_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['og:locale'] = 'ja_JP';
        $tags['og:url'] = home_url('/contact/');
        $tags['og:title'] = $seo['title'];
        $tags['og:description'] = $seo['description'];
        return $tags;
    }

    public static function filter_aioseo_contact_page_twitter_tags($tags) {
        $seo = self::contact_page_seo_config();
        if (!$seo || !is_array($tags)) return $tags;
        $tags['twitter:title'] = $seo['title'];
        $tags['twitter:description'] = $seo['description'];
        return $tags;
    }

    private static function localize_aioseo_contact_page_schema_node(&$node, $seo) {
        if (!is_array($node)) return;
        $types = [];
        if (isset($node['@type'])) $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        if (in_array('WebPage', $types, true) || in_array('ContactPage', $types, true)) {
            $node['name'] = $seo['title'];
            $node['description'] = $seo['description'];
            $node['inLanguage'] = 'ja';
        }
        foreach ($node as &$child) if (is_array($child)) self::localize_aioseo_contact_page_schema_node($child, $seo);
        unset($child);
    }

    public static function filter_aioseo_contact_page_schema($graphs) {
        $seo = self::contact_page_seo_config();
        if (!$seo || !is_array($graphs)) return $graphs;
        self::localize_aioseo_contact_page_schema_node($graphs, $seo);
        return $graphs;
    }

"""; assert anchor in s; s=s.replace(anchor,add,1)
 p.write_text(s)
E="""開催情報・開催状況管理 3.2.94

3.2.94:
- 日本語お問い合わせページ /contact/ の表示本文・フォームを変更せず、title / descriptionを「お問い合わせ・入苑・取材・各種相談」文脈へ調整
- /contact/ のOG / Twitter / AIOSEO WebPage・ContactPage schemaを同じtitle / descriptionへ同期
- /contact/ の旧テーマOGを除去しAIOSEOをheadメタ情報の正本に統一
- トップ、入苑案内、アクセス、会期一覧、春・秋・冬、英語・繁体字、本文・フォーム・見出し・レイアウトには変更なし

"""
for p in [Path('garden-opening-status/readme.txt'),Path('plugins/garden-opening-status/readme.txt')]:
 s=p.read_text(); assert s.startswith('開催情報・開催状況管理 3.2.93'); p.write_text(E+s)
