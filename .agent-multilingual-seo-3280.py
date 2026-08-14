from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readmes = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

hook_anchor = "        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_multilingual_schema'], 30);\n"
hook_insert = hook_anchor + (
    "        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n"
    "        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);\n"
    "        add_filter('aioseo_twitter_tags', [__CLASS__, 'filter_aioseo_multilingual_twitter_tags'], 100);\n"
    "        add_action('wp_head', [__CLASS__, 'output_multilingual_seo_marker'], 1);\n"
)

method_anchor = "    private static function localize_aioseo_multilingual_schema_node(&$node, $language, $seo) {\n"
methods = '''    private static function multilingual_page_description($language) {
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
        echo "<!-- Garden Opening Status 3.2.80 multilingual SEO active -->\\n";
    }

'''

old_desc = """        if ($language === 'en') {
            $description = 'Ueno Toshogu Peony Garden in central Tokyo presents the Wintertime Peony Festival, Springtime Peony Festival, and Special Festival - Autumn Dahlia Garden.';
        } elseif ($language === 'zh-Hant') {
            $description = '上野東照宮牡丹園位於東京都心，舉辦冬季牡丹園、春季牡丹節及特別祭典-秋季大麗花園，並提供參觀與交通資訊。';
        } else {
            $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';
        }
"""
new_desc = """        if ($language === 'en' || $language === 'zh-Hant') {
            $description = self::multilingual_page_description($language);
        } else {
            $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';
        }
"""

for path in php_paths:
    text = path.read_text()
    assert text.count('Version: 3.2.79') == 1, path
    assert text.count("const VERSION = '3.2.79';") == 1, path
    assert text.count(hook_anchor) == 1, path
    assert text.count(method_anchor) == 1, path
    assert text.count(old_desc) == 1, path
    text = text.replace('Version: 3.2.79', 'Version: 3.2.80', 1)
    text = text.replace("const VERSION = '3.2.79';", "const VERSION = '3.2.80';", 1)
    text = text.replace(hook_anchor, hook_insert, 1)
    text = text.replace(method_anchor, methods + method_anchor, 1)
    text = text.replace(old_desc, new_desc, 1)
    path.write_text(text)

note = """開催情報・開催状況管理 3.2.80

3.2.80:
- AIOSEO公式の aioseo_title / aioseo_facebook_tags / aioseo_twitter_tags を使い、English / Chinese のSEO・SNSメタを直接補正
- AIOSEO schema補正は既存の aioseo_schema_output を継続
- source上で実稼働版を確認できる multilingual SEO active コメントを追加
- canonical、hreflang、既存TouristAttraction schemaは変更なし

"""
for path in readmes:
    text = path.read_text()
    assert not text.startswith('開催情報・開催状況管理 3.2.80'), path
    path.write_text(note + text)
