from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readmes = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

for path in php_paths:
    text = path.read_text()
    assert text.count('Version: 3.2.80') == 1, path
    assert text.count("const VERSION = '3.2.80';") == 1, path
    assert text.count("add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);") == 1, path
    assert text.count('Garden Opening Status 3.2.80 multilingual SEO active') == 1, path

    text = text.replace('Version: 3.2.80', 'Version: 3.2.81', 1)
    text = text.replace("const VERSION = '3.2.80';", "const VERSION = '3.2.81';", 1)

    title_hook = "        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n"
    description_hook = title_hook + "        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);\n"
    assert text.count(title_hook) == 1, path
    text = text.replace(title_hook, description_hook, 1)

    title_method = """    public static function filter_aioseo_multilingual_title($title) {\n        $seo = self::multilingual_seo_config(self::information_page_language());\n        return $seo ? $seo['title'] : $title;\n    }\n\n"""
    description_method = title_method + """    public static function filter_aioseo_multilingual_description($description) {\n        $language = self::information_page_language();\n        $localized = self::multilingual_page_description($language);\n        return $localized !== '' ? $localized : $description;\n    }\n\n"""
    assert text.count(title_method) == 1, path
    text = text.replace(title_method, description_method, 1)

    old_start = """    public static function start_description_output_buffer() {\n        if (is_admin() || self::information_page_language() === '') return;\n        ob_start([__CLASS__, 'filter_description_output']);\n    }\n"""
    new_start = """    public static function start_description_output_buffer() {\n        if (is_admin()) return;\n        $language = self::information_page_language();\n        if ($language === '' || $language === 'en' || $language === 'zh-Hant') return;\n        ob_start([__CLASS__, 'filter_description_output']);\n    }\n"""
    assert text.count(old_start) == 1, path
    text = text.replace(old_start, new_start, 1)

    old_desc_head = """        $language = self::information_page_language();\n        if ($language === 'en' || $language === 'zh-Hant') {\n            $description = self::multilingual_page_description($language);\n        } else {\n            $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';\n        }\n"""
    new_desc_head = """        $language = self::information_page_language();\n        if ($language === 'en' || $language === 'zh-Hant') return $html;\n        $description = '上野東照宮の参道内にあるぼたん苑です。「上野・東照宮 冬ぼたん」、春のぼたん祭、ダリア綾なす秋の園を開催し、冬咲きぼたんや春の牡丹、秋のダリアをお楽しみいただけます。';\n"""
    assert text.count(old_desc_head) == 1, path
    text = text.replace(old_desc_head, new_desc_head, 1)

    seo_block_start = "        $seo = self::multilingual_seo_config($language);\n        $seo_meta = '';\n        if ($seo) {\n"
    seo_block_end = "        }\n\n        $meta = \"\\n<!-- Garden page descriptions -->\\n\";\n"
    start = text.find(seo_block_start)
    assert start != -1, path
    end = text.find(seo_block_end, start)
    assert end != -1, path
    end += len("        }\n\n")
    text = text[:start] + text[end:]

    text = text.replace("return preg_replace('~</head>~i', $seo_meta . $meta . '</head>', $html, 1);", "return preg_replace('~</head>~i', $meta . '</head>', $html, 1);", 1)
    text = text.replace('return $html . $seo_meta . $meta;', 'return $html . $meta;', 1)
    text = text.replace('Garden Opening Status 3.2.80 multilingual SEO active', 'Garden Opening Status 3.2.81 multilingual SEO active', 1)

    path.write_text(text)

note = '''開催情報・開催状況管理 3.2.81\n\n3.2.81:\n- English / Chinese ではHTML完成後のSEOタグ削除・再挿入を停止\n- 通常descriptionも aioseo_description で言語別に直接補正\n- title / OG / Twitter / schema はAIOSEOフィルタ経路で維持\n- 日本語トップの既存description整理は従来処理を維持\n- canonical、hreflang、既存TouristAttraction schemaは変更なし\n\n'''
for path in readmes:
    text = path.read_text()
    assert not text.startswith('開催情報・開催状況管理 3.2.81'), path
    path.write_text(note + text)
