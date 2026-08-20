from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

for path in paths:
    s = path.read_text(encoding='utf-8')
    assert 'Version: 3.2.97' in s
    assert "const VERSION = '3.2.97';" in s
    assert "add_action('wp_footer', [__CLASS__, 'event_page_preview_fallback'], 100);" in s
    assert "@media(max-width:782px){body.gos-seasonal-event-page{padding-bottom:calc(76px + env(safe-area-inset-bottom, 0px))!important}}" in s
    assert "if (self::current_event_page_season() !== '') $classes[] = 'gos-seasonal-event-page';" in s

    s = s.replace('Version: 3.2.97', 'Version: 3.2.98', 1)
    s = s.replace("const VERSION = '3.2.97';", "const VERSION = '3.2.98';", 1)
    s = s.replace('Garden Opening Status 3.2.97 multilingual SEO active', 'Garden Opening Status 3.2.98 multilingual SEO active', 1)
    s = s.replace("        add_action('wp_footer', [__CLASS__, 'event_page_preview_fallback'], 100);\n", "        add_action('wp_footer', [__CLASS__, 'event_page_preview_fallback'], 100);\n        add_action('wp_footer', [__CLASS__, 'seasonal_mobile_bottom_spacer'], 101);\n", 1)
    s = s.replace("        if (self::current_event_page_season() !== '') $classes[] = 'gos-seasonal-event-page';\n", '', 1)
    s = s.replace("        @media(max-width:782px){body.gos-seasonal-event-page{padding-bottom:calc(76px + env(safe-area-inset-bottom, 0px))!important}}\n", '', 1)

    marker = "    private static function now() {\n"
    method = "    public static function seasonal_mobile_bottom_spacer() {\n        if (is_admin() || self::current_event_page_season() === '') return;\n        echo '<div class=\"gos-seasonal-mobile-bottom-spacer\" aria-hidden=\"true\"></div>';\n        echo '<style>@media(max-width:782px){.gos-seasonal-mobile-bottom-spacer{display:block!important;height:calc(76px + env(safe-area-inset-bottom, 0px))!important;min-height:calc(76px + env(safe-area-inset-bottom, 0px))!important;flex:none!important;clear:both!important}}@media(min-width:783px){.gos-seasonal-mobile-bottom-spacer{display:none!important}}</style>';\n    }\n\n"
    assert marker in s
    s = s.replace(marker, method + marker, 1)
    path.write_text(s, encoding='utf-8')

# keep readmes mirrored and prepend release note
readme = Path('garden-opening-status/readme.txt')
rt = readme.read_text(encoding='utf-8')
assert rt.startswith('開催情報・開催状況管理 3.2.97')
entry = '''開催情報・開催状況管理 3.2.98\n\n3.2.98:\n- 3.2.97のbody下余白方式を撤回し、春・秋・冬の会期ページ末尾へスマホ専用スペーサーを直接出力する方式へ変更しました。\n- テーマ側のスクロールコンテナ構造に依存せず、下部固定ナビ相当76px + safe-area分をスクロール終端に確保します。PC表示・会期情報・SEO・公開日制御には変更ありません。\n\n'''
readme.write_text(entry + rt, encoding='utf-8')
copyfile(readme, Path('plugins/garden-opening-status/readme.txt'))
