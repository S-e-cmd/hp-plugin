from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

for path in paths:
    s = path.read_text(encoding='utf-8')
    assert 'Version: 3.3.0' in s
    assert "const VERSION = '3.3.0';" in s
    assert 'public static function seasonal_confirmed_date_wrap()' in s

    s = s.replace('Version: 3.3.0', 'Version: 3.3.1', 1)
    s = s.replace("const VERSION = '3.3.0';", "const VERSION = '3.3.1';", 1)
    s = s.replace('Garden Opening Status 3.3.0 multilingual SEO active', 'Garden Opening Status 3.3.1 multilingual SEO active', 1)

    dead_block = """        $date_text = self::localized_event_date_text($event, $lang);\n        $date_value = $date_text;\n        if (\n            $lang === 'ja' &&\n            sanitize_key((string)($event['date_display_mode'] ?? 'usual')) === 'confirmed' &&\n            self::event_released($event, self::now())\n        ) {\n            $start_date = trim((string)($event['start'] ?? ''));\n            $end_date = trim((string)($event['end'] ?? ''));\n            if ($start_date !== '' && $end_date !== '') {\n                $date_value = [\n                    '__confirmed_mobile_range' => [\n                        self::format_date_with_weekday($start_date) . '～',\n                        self::format_date_with_weekday($end_date),\n                    ],\n                ];\n            }\n        }\n"""
    replacement = """        $date_text = self::localized_event_date_text($event, $lang);\n"""
    assert dead_block in s
    s = s.replace(dead_block, replacement, 1)

    old_row = """        if ($date_text !== '') $rows[] = [$labels[$lang]['date'], $date_value, ''];\n"""
    new_row = """        if ($date_text !== '') $rows[] = [$labels[$lang]['date'], $date_text, ''];\n"""
    assert old_row in s
    s = s.replace(old_row, new_row, 1)

    old_render = """                            <?php if (is_array($value) && isset($value['__confirmed_mobile_range'])): ?>\n                                <span class=\"gos-event-page-info__confirmed-date-start\"><?php echo esc_html($value['__confirmed_mobile_range'][0]); ?></span><span class=\"gos-event-page-info__confirmed-date-end\"><?php echo esc_html($value['__confirmed_mobile_range'][1]); ?></span>\n                            <?php elseif (is_array($value)): ?>\n"""
    new_render = """                            <?php if (is_array($value)): ?>\n"""
    assert old_render in s
    s = s.replace(old_render, new_render, 1)

    # Keep the confirmed-date CSS classes: 3.3.0's working DOM-based wrapper uses them.
    assert '.gos-event-page-info__confirmed-date-start,.gos-event-page-info__confirmed-date-end{display:inline}' in s
    assert '.gos-event-page-info__confirmed-date-start,.gos-event-page-info__confirmed-date-end{display:block}' in s
    assert "'__confirmed_mobile_range'" not in s
    path.write_text(s, encoding='utf-8')

readme = Path('garden-opening-status/readme.txt')
rt = readme.read_text(encoding='utf-8')
assert rt.startswith('開催情報・開催状況管理 3.3.0')
entry = '''開催情報・開催状況管理 3.3.1\n\n3.3.1:\n- 3.2.99で追加した、実際の固定会期ページでは使われていなかったショートコード側の確定会期分割処理を削除しました。\n- 3.3.0で実際に効いている描画後の確定会期文字列分割処理だけを残しています。表示仕様・例年表示・PC表示・SEOには変更ありません。\n\n'''
readme.write_text(entry + rt, encoding='utf-8')
copyfile(readme, Path('plugins/garden-opening-status/readme.txt'))
