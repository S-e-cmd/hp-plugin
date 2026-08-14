from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readmes = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

init_old = "        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_permanent_guide_schema'], 20);\n"
init_new = init_old + "        add_filter('aioseo_schema_output', [__CLASS__, 'filter_aioseo_multilingual_schema'], 30);\n"

anchor = '''    /**
     * Normalize description metadata on the Japanese, English, and Traditional
     * Chinese information pages. The theme and SEO plugin both output description
     * tags, so the completed HTML is filtered to leave one language-appropriate set.
     */
'''

helpers = r'''    private static function multilingual_seo_config($language) {
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

'''

desc_old = '''        $patterns = [
            '~<meta\\b(?=[^>]*\\bname\\s*=\\s*(["\\'])description\\1)[^>]*>\\s*~i',
            '~<meta\\b(?=[^>]*\\bproperty\\s*=\\s*(["\\'])og:description\\1)[^>]*>\\s*~i',
            '~<meta\\b(?=[^>]*\\bname\\s*=\\s*(["\\'])twitter:description\\1)[^>]*>\\s*~i',
        ];
        $html = preg_replace($patterns, '', $html);
        if (!is_string($html)) return '';

        $meta = "\\n<!-- Garden page descriptions -->\\n";
        $meta .= '<meta name="description" content="' . esc_attr($description) . '" />' . "\\n";
        $meta .= '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\\n";
        $meta .= '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\\n";

        if (stripos($html, '</head>') !== false) {
            return preg_replace('~</head>~i', $meta . '</head>', $html, 1);
        }

        return $html . $meta;
'''

desc_new = '''        $patterns = [
            '~<meta\\b(?=[^>]*\\bname\\s*=\\s*(["\\'])description\\1)[^>]*>\\s*~i',
            '~<meta\\b(?=[^>]*\\bproperty\\s*=\\s*(["\\'])og:description\\1)[^>]*>\\s*~i',
            '~<meta\\b(?=[^>]*\\bname\\s*=\\s*(["\\'])twitter:description\\1)[^>]*>\\s*~i',
        ];
        $html = preg_replace($patterns, '', $html);
        if (!is_string($html)) return '';

        $seo = self::multilingual_seo_config($language);
        $seo_meta = '';
        if ($seo) {
            $seo_patterns = [
                '~<title\\b[^>]*>.*?</title>\\s*~is',
                '~<meta\\b(?=[^>]*\\bproperty\\s*=\\s*(["\\'])og:(?:type|url|title|site_name|locale|image(?::(?:secure_url|width|height))?)\\1)[^>]*>\\s*~i',
                '~<meta\\b(?=[^>]*\\bname\\s*=\\s*(["\\'])twitter:(?:title|image)\\1)[^>]*>\\s*~i',
            ];
            $html = preg_replace($seo_patterns, '', $html);
            if (!is_string($html)) return '';

            $page_url = home_url($seo['path']);
            $image_url = home_url('/wp-content/uploads/2021/03/main1_sp.png');
            $seo_meta .= "\\n<!-- Garden multilingual SEO -->\\n";
            $seo_meta .= '<title>' . esc_html($seo['title']) . '</title>' . "\\n";
            $seo_meta .= '<meta property="og:locale" content="' . esc_attr($seo['og_locale']) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:type" content="article" />' . "\\n";
            $seo_meta .= '<meta property="og:url" content="' . esc_url($page_url) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:title" content="' . esc_attr($seo['title']) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:site_name" content="' . esc_attr($seo['site_name']) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:image:secure_url" content="' . esc_url($image_url) . '" />' . "\\n";
            $seo_meta .= '<meta property="og:image:width" content="1450" />' . "\\n";
            $seo_meta .= '<meta property="og:image:height" content="860" />' . "\\n";
            $seo_meta .= '<meta name="twitter:title" content="' . esc_attr($seo['title']) . '" />' . "\\n";
            $seo_meta .= '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\\n";
        }

        $meta = "\\n<!-- Garden page descriptions -->\\n";
        $meta .= '<meta name="description" content="' . esc_attr($description) . '" />' . "\\n";
        $meta .= '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\\n";
        $meta .= '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\\n";

        if (stripos($html, '</head>') !== false) {
            return preg_replace('~</head>~i', $seo_meta . $meta . '</head>', $html, 1);
        }

        return $html . $seo_meta . $meta;
'''

for path in php_paths:
    text = path.read_text()
    assert text.count("Version: 3.2.78") == 1, path
    assert text.count("const VERSION = '3.2.78';") == 1, path
    assert text.count(init_old) == 1, path
    assert text.count(anchor) == 1, path
    assert text.count(desc_old) == 1, path
    text = text.replace("Version: 3.2.78", "Version: 3.2.79", 1)
    text = text.replace("const VERSION = '3.2.78';", "const VERSION = '3.2.79';", 1)
    text = text.replace(init_old, init_new, 1)
    text = text.replace(anchor, helpers + anchor, 1)
    text = text.replace(desc_old, desc_new, 1)
    path.write_text(text)

note = '''開催情報・開催状況管理 3.2.79

3.2.79:
- English / ChineseページのSEO title、OG locale、OG/Twitter title・imageを言語別に補正
- テーマとAIOSEOで重複していた主要OG出力を外国語ページでは1セットへ整理
- AIOSEO WebPage schemaのname / inLanguageを英語・繁体字へ補正
- AIOSEO Breadcrumbのホーム・ページ名を各言語へ補正
- canonical、hreflang、既存TouristAttraction schemaは変更なし

'''
for path in readmes:
    text = path.read_text()
    assert not text.startswith('開催情報・開催状況管理 3.2.79'), path
    path.write_text(note + text)
