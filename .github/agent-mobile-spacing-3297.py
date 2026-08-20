from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

body_old = """    public static function body_class($classes) {
        if (self::is_permanent_guide_page()) $classes[] = 'gos-permanent-guide-page';
        if (self::is_any_preview()) {
"""
body_new = """    public static function body_class($classes) {
        if (self::is_permanent_guide_page()) $classes[] = 'gos-permanent-guide-page';
        if (self::current_event_page_season() !== '') $classes[] = 'gos-seasonal-event-page';
        if (self::is_any_preview()) {
"""

style_old = """    public static function front_styles() {
        echo '<style id=\"gos-event-page-info-style\">
"""
style_new = """    public static function front_styles() {
        echo '<style id=\"gos-event-page-info-style\">
        @media(max-width:782px){body.gos-seasonal-event-page{padding-bottom:calc(76px + env(safe-area-inset-bottom, 0px))!important}}
"""

for p in paths:
    s = p.read_text()
    assert 'Version: 3.2.96' in s
    assert "const VERSION = '3.2.96';" in s
    assert body_old in s
    assert style_old in s
    s = s.replace('Version: 3.2.96', 'Version: 3.2.97', 1)
    s = s.replace("const VERSION = '3.2.96';", "const VERSION = '3.2.97';", 1)
    s = s.replace('Garden Opening Status 3.2.96 multilingual SEO active', 'Garden Opening Status 3.2.97 multilingual SEO active', 1)
    s = s.replace(body_old, body_new, 1)
    s = s.replace(style_old, style_new, 1)
    p.write_text(s)

assert paths[0].read_bytes() == paths[1].read_bytes()

readmes = [Path('garden-opening-status/readme.txt'), Path('plugins/garden-opening-status/readme.txt')]
r = readmes[0]
t = r.read_text()
assert t.startswith('開催情報・開催状況管理 3.2.96')
entry = """開催情報・開催状況管理 3.2.97

3.2.97:
- 春・秋・冬の会期ページだけ、スマホ下部固定ナビに本文が隠れないよう下余白を追加しました。
- 余白は固定ナビ相当76pxに端末のsafe-areaを加算し、PC表示・会期情報・SEO・公開日制御には変更ありません。

"""
r.write_text(entry + t)
copyfile(r, readmes[1])
