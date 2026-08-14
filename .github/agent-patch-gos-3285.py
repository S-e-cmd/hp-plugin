from pathlib import Path

php_paths = [Path('garden-opening-status/garden-opening-status.php'), Path('plugins/garden-opening-status/garden-opening-status.php')]
readme_paths = [Path('garden-opening-status/readme.txt'), Path('plugins/garden-opening-status/readme.txt')]

old_helper = '''    /**
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
'''

new_helper = '''    /**
     * Seasonal fixed pages always get public-safe AIOSEO metadata from plugin
     * state instead of allowing AIOSEO to infer it from stored page content.
     * Confirmed dates appear only after the event release gate opens.
     */
    private static function confirmed_event_page_seo_data() {
        $season = self::current_event_page_season();
        if ($season === '') return [];

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        if (empty($event['enabled'])) return [];

        $name = trim((string)($event['label'] ?? ''));
        if ($name === '') return [];

        $now = self::now();
        $date_display_mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));
        $released = self::event_released($event, $now);
        $start_date = trim((string)($event['start'] ?? ''));
        $end_date = trim((string)($event['end'] ?? ''));

        if ($released && $date_display_mode === 'confirmed' && $start_date !== '' && $end_date !== '') {
            $start = self::dt($start_date, '00:00');
            $end = self::dt($end_date, '23:59');
            if ($start && $end && $end >= $start) {
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
        }

        $usual = trim((string)($event['usual_period'] ?? ''));
        $description = $name . 'の会期情報。';
        if ($usual !== '') $description .= '例年の開苑期間は' . $usual . 'です。';
        $description .= '開苑時間、入苑料、アクセスなどをご案内します。';

        return [
            'title' => $name . '｜上野東照宮ぼたん苑',
            'description' => $description,
        ];
    }
'''

old_start = '''        $language = self::information_page_language();
        if ($language !== 'en' && $language !== 'zh-Hant') return;
        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);
'''
new_start = '''        $language = self::information_page_language();
        $is_event_page = self::current_event_page_season() !== '';
        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return;
        ob_start([__CLASS__, 'filter_multilingual_theme_og_output']);
'''

old_filter = '''        $language = self::information_page_language();
        if ($language !== 'en' && $language !== 'zh-Hant') return $html;

        $aioseo_marker = '<!-- All in One SEO';
'''
new_filter = '''        $language = self::information_page_language();
        $is_event_page = self::current_event_page_season() !== '';
        if ($language !== 'en' && $language !== 'zh-Hant' && !$is_event_page) return $html;

        $aioseo_marker = '<!-- All in One SEO';
'''

for path in php_paths:
    text = path.read_text(encoding='utf-8')
    assert text.count(old_helper) == 1, f'helper anchor mismatch: {path}'
    assert text.count(old_start) == 1, f'start anchor mismatch: {path}'
    assert text.count(old_filter) == 1, f'filter anchor mismatch: {path}'
    text = text.replace(old_helper, new_helper)
    text = text.replace(old_start, new_start, 1)
    text = text.replace(old_filter, new_filter, 1)
    text = text.replace('Version: 3.2.84', 'Version: 3.2.85', 1)
    text = text.replace("const VERSION = '3.2.84';", "const VERSION = '3.2.85';", 1)
    text = text.replace('Garden Opening Status 3.2.84 multilingual SEO active', 'Garden Opening Status 3.2.85 multilingual SEO active', 1)
    path.write_text(text, encoding='utf-8')

changelog = '''開催情報・開催状況管理 3.2.85\n\n3.2.85:\n- 固定会期ページのAIOSEO title / descriptionを保存済み本文から推測させず、公開状態から生成するよう修正\n- 情報解禁前は確定日をheadへ出さず、例年会期ベースのdescriptionを出力\n- 情報解禁後は3.2.83の確定日入りtitle / descriptionへ切り替え\n- 固定会期ページでもAIOSEOより前のテーマOGを除去し、非公開確定日のOG漏洩を防止\n\n'''
for path in readme_paths:
    text = path.read_text(encoding='utf-8')
    assert not text.startswith('開催情報・開催状況管理 3.2.85')
    path.write_text(changelog + text, encoding='utf-8')
