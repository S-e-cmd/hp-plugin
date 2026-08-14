from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readme_paths = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

for path in php_paths:
    text = path.read_text(encoding='utf-8')
    assert '* Version: 3.2.81' in text, path
    assert "const VERSION = '3.2.81';" in text, path
    assert "Garden Opening Status 3.2.81 multilingual SEO active" in text, path
    assert "start_multilingual_theme_og_output_buffer" not in text, path

    text = text.replace('* Version: 3.2.81', '* Version: 3.2.82', 1)
    text = text.replace("const VERSION = '3.2.81';", "const VERSION = '3.2.82';", 1)
    text = text.replace("Garden Opening Status 3.2.81 multilingual SEO active", "Garden Opening Status 3.2.82 multilingual SEO active", 1)

    anchor = "        add_filter('language_attributes', [__CLASS__, 'language_attributes'], 20, 2);\n"
    assert text.count(anchor) == 1, path
    text = text.replace(anchor, anchor + "        add_action('template_redirect', [__CLASS__, 'start_multilingual_theme_og_output_buffer'], -110);\n", 1)

    method_anchor = "    private static function multilingual_seo_config($language) {\n"
    assert text.count(method_anchor) == 1, path
    methods = r'''    /**
     * On English / Traditional Chinese pages, the legacy theme emits its own OG
     * tags before AIOSEO. Remove only those pre-AIOSEO OG tags so AIOSEO remains
     * the single source of Open Graph metadata. No tags are reinserted here.
     */
    public static function start_multilingual_theme_og_output_buffer() {
        if (is_admin()) return;
        $language = self::information_page_language();
        if ($language !== 'en' && $language !== 'zh-Hant') return;
        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);
    }

    public static function filter_multilingual_theme_og_output($html) {
        if (!is_string($html) || $html === '') return $html;

        $language = self::information_page_language();
        if ($language !== 'en' && $language !== 'zh-Hant') return $html;

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

'''
    text = text.replace(method_anchor, methods + method_anchor, 1)
    path.write_text(text, encoding='utf-8')

entry = """開催情報・開催状況管理 3.2.82

3.2.82:
- English / Chinese でテーマがAIOSEOより前に出す重複OGタグだけを除去
- AIOSEOのtitle / description / OG / Twitter / schema出力は変更なし
- OGタグの再挿入は行わず、AIOSEOを外国語ページのOG正本に統一
- 日本語ページ、canonical、hreflang、既存TouristAttraction schemaは変更なし

"""
for path in readme_paths:
    text = path.read_text(encoding='utf-8')
    assert text.startswith('開催情報・開催状況管理 3.2.81'), path
    text = entry + text
    path.write_text(text, encoding='utf-8')
