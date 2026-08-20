from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

for path in paths:
    s = path.read_text(encoding='utf-8')
    assert 'Version: 3.2.98' in s
    assert "const VERSION = '3.2.98';" in s

    s = s.replace('Version: 3.2.98', 'Version: 3.2.99', 1)
    s = s.replace("const VERSION = '3.2.98';", "const VERSION = '3.2.99';", 1)
    s = s.replace('Garden Opening Status 3.2.98 multilingual SEO active', 'Garden Opening Status 3.2.99 multilingual SEO active', 1)

    old = """        $date_text = self::localized_event_date_text($event, $lang);\n        $open_text = self::format_time($event['open_time'] ?? '');\n"""
    new = """        $date_text = self::localized_event_date_text($event, $lang);\n        $date_value = $date_text;\n        if (\n            $lang === 'ja' &&\n            sanitize_key((string)($event['date_display_mode'] ?? 'usual')) === 'confirmed' &&\n            self::event_released($event, self::now())\n        ) {\n            $start_date = trim((string)($event['start'] ?? ''));\n            $end_date = trim((string)($event['end'] ?? ''));\n            if ($start_date !== '' && $end_date !== '') {\n                $date_value = [\n                    '__confirmed_mobile_range' => [\n                        self::format_date_with_weekday($start_date) . '～',\n                        self::format_date_with_weekday($end_date),\n                    ],\n                ];\n            }\n        }\n        $open_text = self::format_time($event['open_time'] ?? '');\n"""
    assert old in s
    s = s.replace(old, new, 1)

    old = """        $rows = [];\n        if ($date_text !== '') $rows[] = [$labels[$lang]['date'], $date_text, ''];\n"""
    new = """        $rows = [];\n        if ($date_text !== '') $rows[] = [$labels[$lang]['date'], $date_value, ''];\n"""
    assert old in s
    s = s.replace(old, new, 1)

    old = """                            <?php if (is_array($value)): ?>\n                                <?php foreach ($value as $line): ?><span class=\"gos-event-page-info__line\"><?php echo esc_html($line); ?></span><?php endforeach; ?>\n                            <?php else: ?>\n                                <?php echo nl2br(esc_html($value)); ?>\n                            <?php endif; ?>\n"""
    new = """                            <?php if (is_array($value) && isset($value['__confirmed_mobile_range'])): ?>\n                                <span class=\"gos-event-page-info__confirmed-date-start\"><?php echo esc_html($value['__confirmed_mobile_range'][0]); ?></span><span class=\"gos-event-page-info__confirmed-date-end\"><?php echo esc_html($value['__confirmed_mobile_range'][1]); ?></span>\n                            <?php elseif (is_array($value)): ?>\n                                <?php foreach ($value as $line): ?><span class=\"gos-event-page-info__line\"><?php echo esc_html($line); ?></span><?php endforeach; ?>\n                            <?php else: ?>\n                                <?php echo nl2br(esc_html($value)); ?>\n                            <?php endif; ?>\n"""
    assert old in s
    s = s.replace(old, new, 1)

    old = """        .gos-event-page-info__line{display:block;margin:0 0 .2em}\n        @media(max-width:782px){\n"""
    new = """        .gos-event-page-info__line{display:block;margin:0 0 .2em}\n        .gos-event-page-info__confirmed-date-start,.gos-event-page-info__confirmed-date-end{display:inline}\n        @media(max-width:782px){\n"""
    assert old in s
    s = s.replace(old, new, 1)

    old = """            .gos-event-page-info__value{white-space:normal}\n            .gos-event-page-info__line{margin:0 0 .15em}\n"""
    new = """            .gos-event-page-info__value{white-space:normal}\n            .gos-event-page-info__confirmed-date-start,.gos-event-page-info__confirmed-date-end{display:block}\n            .gos-event-page-info__line{margin:0 0 .15em}\n"""
    assert old in s
    s = s.replace(old, new, 1)

    path.write_text(s, encoding='utf-8')

readme = Path('garden-opening-status/readme.txt')
rt = readme.read_text(encoding='utf-8')
assert rt.startswith('開催情報・開催状況管理 3.2.98')
entry = '''開催情報・開催状況管理 3.2.99\n\n3.2.99:\n- 春・秋・冬の会期情報で、確定会期が公開されている場合だけ、スマホ表示の終了日を2行目へ送るよう調整しました。\n- 例年表示は従来どおり1行のまま、PC表示も従来どおり1行です。会期情報の文言・SEO・公開日制御には変更ありません。\n\n'''
readme.write_text(entry + rt, encoding='utf-8')
copyfile(readme, Path('plugins/garden-opening-status/readme.txt'))
