from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readme_paths = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

init_old = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);"""
init_new = """        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_multilingual_title'], 100);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_multilingual_description'], 100);\n        add_filter('aioseo_title', [__CLASS__, 'filter_aioseo_event_page_title'], 110);\n        add_filter('aioseo_description', [__CLASS__, 'filter_aioseo_event_page_description'], 110);\n        add_filter('aioseo_facebook_tags', [__CLASS__, 'filter_aioseo_multilingual_facebook_tags'], 100);"""

anchor = """    public static function filter_aioseo_multilingual_description($description) {\n        $language = self::information_page_language();\n        $localized = self::multilingual_page_description($language);\n        return $localized !== '' ? $localized : $description;\n    }\n\n"""
addition = r'''    /**
     * Keep the permanent seasonal page as the SEO source of truth once confirmed
     * dates are publicly released. Before release, leave the page's existing
     * evergreen AIOSEO title/description untouched.
     */
    private static function confirmed_event_page_seo_data() {
        $season = self::current_event_page_season();
        if ($season === '') return [];

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        if (empty($event['enabled'])) return [];

        $now = self::now();
        if (!self::event_released($event, $now)) return [];

        $date_display_mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));
        if ($date_display_mode !== 'confirmed') return [];

        $start_date = trim((string)($event['start'] ?? ''));
        $end_date = trim((string)($event['end'] ?? ''));
        if ($start_date === '' || $end_date === '') return [];

        $start = self::dt($start_date, '00:00');
        $end = self::dt($end_date, '23:59');
        if (!$start || !$end || $end < $start) return [];

        $name = trim((string)($event['label'] ?? ''));
        if ($name === '') return [];

        if ($start->format('Y') === $end->format('Y')) {
            $range = $start->format('Y年n月j日') . '～' . $end->format('n月j日');
            $description = $start->format('Y年') . 'の' . $name . 'は' . $start->format('n月j日') . 'から' . $end->format('n月j日') . 'まで開催します。';
        } else {
            $range = $start->format('Y年n月j日') . '～' . $end->format('Y年n月j日');
            $description = $name . 'は' . $start->format('Y年n月j日') . 'から' . $end->format('Y年n月j日') . 'まで開催します。';
        }

        return [
            'title' => $name . '｜' . $range . '｜上野東照宮ぼたん苑',
            'description' => $description . '開苑時間、入苑料、アクセスなどをご案内します。',
        ];
    }

    public static function filter_aioseo_event_page_title($title) {
        $seo = self::confirmed_event_page_seo_data();
        return !empty($seo['title']) ? $seo['title'] : $title;
    }

    public static function filter_aioseo_event_page_description($description) {
        $seo = self::confirmed_event_page_seo_data();
        return !empty($seo['description']) ? $seo['description'] : $description;
    }

'''

for path in php_paths:
    text = path.read_text()
    assert "Version: 3.2.82" in text, path
    assert "const VERSION = '3.2.82';" in text, path
    assert text.count(init_old) == 1, (path, 'init anchor')
    assert text.count(anchor) == 1, (path, 'description anchor')
    text = text.replace('Version: 3.2.82', 'Version: 3.2.83', 1)
    text = text.replace("const VERSION = '3.2.82';", "const VERSION = '3.2.83';", 1)
    text = text.replace(init_old, init_new, 1)
    text = text.replace(anchor, anchor + addition, 1)
    text = text.replace('Garden Opening Status 3.2.82 multilingual SEO active', 'Garden Opening Status 3.2.83 multilingual SEO active')
    path.write_text(text)

readme_header = """開催情報・開催状況管理 3.2.83\n\n3.2.83:\n- 確定会期が公開された固定会期ページでは、AIOSEO title / descriptionへ確定日を自動反映\n- 未解禁・例年表示・日付未確定の状態では既存の常設SEO title / descriptionを維持\n- Event schemaと同じ公開条件を使い、固定会期ページ本文・構造化データ・検索メタの確定日を一致\n- 英語・中国語ページ、日本語トップ、canonical、hreflang、既存OG/Twitter処理は変更なし\n\n"""
for path in readme_paths:
    text = path.read_text()
    assert text.startswith('開催情報・開催状況管理 3.2.82\n'), path
    path.write_text(readme_header + text)

assert php_paths[0].read_bytes() == php_paths[1].read_bytes(), 'PHP mirrors differ'
assert readme_paths[0].read_bytes() == readme_paths[1].read_bytes(), 'readme mirrors differ'
