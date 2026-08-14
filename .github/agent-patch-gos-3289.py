from pathlib import Path

PHP_PATHS = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
README_PATHS = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

for path in PHP_PATHS:
    text = path.read_text(encoding='utf-8')
    text = text.replace('Version: 3.2.88', 'Version: 3.2.89', 1)
    text = text.replace("const VERSION = '3.2.88';", "const VERSION = '3.2.89';", 1)
    text = text.replace('Garden Opening Status 3.2.88 multilingual SEO active', 'Garden Opening Status 3.2.89 multilingual SEO active', 1)

    old_name = """        $name = trim((string)($event['label'] ?? ''));\n        if ($name === '') return [];\n\n        $now = self::now();\n"""
    new_name = """        $name = trim((string)($event['label'] ?? ''));\n        if ($name === '') return [];\n        $is_spring = $season === 'spring';\n\n        $now = self::now();\n"""
    if old_name not in text:
        raise SystemExit(f'name anchor not found in {path}')
    text = text.replace(old_name, new_name, 1)

    old_confirmed = """                return [\n                    'title' => $name . '｜' . $range . '｜上野東照宮ぼたん苑',\n                    'description' => $description . '開苑時間、入苑料、アクセスなどをご案内します。',\n                ];\n"""
    new_confirmed = """                if ($is_spring) {\n                    return [\n                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',\n                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、牡丹をはじめとする春の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',\n                    ];\n                }\n                return [\n                    'title' => $name . '｜' . $range . '｜上野東照宮ぼたん苑',\n                    'description' => $description . '開苑時間、入苑料、アクセスなどをご案内します。',\n                ];\n"""
    if old_confirmed not in text:
        raise SystemExit(f'confirmed SEO anchor not found in {path}')
    text = text.replace(old_confirmed, new_confirmed, 1)

    old_usual = """        $usual = trim((string)($event['usual_period'] ?? ''));\n        $description = $name . 'の会期情報。';\n        if ($usual !== '') $description .= '例年の開苑期間は' . $usual . 'です。';\n        $description .= '開苑時間、入苑料、アクセスなどをご案内します。';\n\n        return [\n            'title' => $name . '｜上野東照宮ぼたん苑',\n            'description' => $description,\n        ];\n"""
    new_usual = """        $usual = trim((string)($event['usual_period'] ?? ''));\n        if ($is_spring) {\n            return [\n                'title' => '東京・上野で牡丹を楽しむ｜' . $name . '｜上野東照宮ぼたん苑',\n                'description' => '東京・上野の上野東照宮ぼたん苑で開催する' . $name . '。日本庭園で牡丹をはじめとする春の花を楽しめます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',\n            ];\n        }\n\n        $description = $name . 'の会期情報。';\n        if ($usual !== '') $description .= '例年の開苑期間は' . $usual . 'です。';\n        $description .= '開苑時間、入苑料、アクセスなどをご案内します。';\n\n        return [\n            'title' => $name . '｜上野東照宮ぼたん苑',\n            'description' => $description,\n        ];\n"""
    if old_usual not in text:
        raise SystemExit(f'usual SEO anchor not found in {path}')
    text = text.replace(old_usual, new_usual, 1)

    path.write_text(text, encoding='utf-8')

entry = """開催情報・開催状況管理 3.2.89\n\n3.2.89:\n- 春の固定会期ページ /schedule/spring/ の表示本文を変更せず、「東京・上野・牡丹・春の花」文脈をtitle / descriptionへ追加\n- 通常時は例年会期を維持し、確定会期公開後は既存の日付連動を維持したままtitle / descriptionへ東京・上野の文脈を追加\n- AIOSEO経由のOG / Twitter / WebPage schemaへの既存同期を維持\n- Event schema、秋・冬、トップ、会期一覧、英語・繁体字、本文・見出し・レイアウトには変更なし\n\n"""
for path in README_PATHS:
    text = path.read_text(encoding='utf-8')
    if not text.startswith('開催情報・開催状況管理 3.2.88'):
        raise SystemExit(f'unexpected readme head in {path}')
    path.write_text(entry + text, encoding='utf-8')
