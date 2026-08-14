from pathlib import Path

php_paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]
readme_paths = [
    Path('garden-opening-status/readme.txt'),
    Path('plugins/garden-opening-status/readme.txt'),
]

old_overview = '''    private static function event_overview_date_text($event) {
        $mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));

        if ($mode === 'hidden' || $mode === 'none') {
            return '';
        }

        if ($mode === 'confirmed') {
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            return self::format_date_range($start, $end);
        }

        return trim((string)($event['usual_period'] ?? ''));
    }
'''
new_overview = '''    private static function event_overview_date_text($event) {
        $mode = sanitize_key((string)($event['date_display_mode'] ?? 'usual'));

        if ($mode === 'hidden' || $mode === 'none') {
            return '';
        }

        if ($mode === 'confirmed') {
            // Confirmed dates may be stored before the information embargo lifts.
            // Until the event is released, keep public pages on the usual period.
            if (!self::event_released($event, self::now())) {
                return trim((string)($event['usual_period'] ?? ''));
            }
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            return self::format_date_range($start, $end);
        }

        return trim((string)($event['usual_period'] ?? ''));
    }
'''

old_localized = '''        if ($mode === 'confirmed') {
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            if ($start === '' && $end === '') return '';
            $format = static function($value) use ($lang) {
'''
new_localized = '''        if ($mode === 'confirmed') {
            // Match the Japanese fixed-page rule: unreleased confirmed dates are
            // never exposed through English / Traditional Chinese event blocks.
            if (!self::event_released($event, self::now())) {
                $mode = 'usual';
            }
        }

        if ($mode === 'confirmed') {
            $start = trim((string)($event['start'] ?? ''));
            $end = trim((string)($event['end'] ?? ''));
            if ($start === '' && $end === '') return '';
            $format = static function($value) use ($lang) {
'''

for path in php_paths:
    text = path.read_text(encoding='utf-8')
    assert text.count('Version: 3.2.83') == 1, path
    assert text.count("const VERSION = '3.2.83';") == 1, path
    assert text.count('Garden Opening Status 3.2.83 multilingual SEO active') == 1, path
    assert text.count(old_overview) == 1, f'overview anchor mismatch: {path}'
    assert text.count(old_localized) == 1, f'localized anchor mismatch: {path}'

    text = text.replace('Version: 3.2.83', 'Version: 3.2.84', 1)
    text = text.replace("const VERSION = '3.2.83';", "const VERSION = '3.2.84';", 1)
    text = text.replace('Garden Opening Status 3.2.83 multilingual SEO active', 'Garden Opening Status 3.2.84 multilingual SEO active', 1)
    text = text.replace(old_overview, new_overview, 1)
    text = text.replace(old_localized, new_localized, 1)
    path.write_text(text, encoding='utf-8')

changelog = '''開催情報・開催状況管理 3.2.84

3.2.84:
- 確定会期を入力済みでも情報解禁前は、固定会期ページに確定日を表示しないよう修正
- 未解禁時は「確定日を表示」設定でも例年会期へフォールバック
- English / Chinese の会期情報も同じ公開判定へ統一し、確定日の事前露出を防止
- 情報解禁後の確定日表示、AIOSEO title / description、Event schema連動は3.2.83の挙動を維持

'''
for path in readme_paths:
    text = path.read_text(encoding='utf-8')
    assert text.startswith('開催情報・開催状況管理 3.2.83\n'), path
    path.write_text(changelog + text, encoding='utf-8')

assert php_paths[0].read_bytes() == php_paths[1].read_bytes(), 'PHP mirrors differ'
assert readme_paths[0].read_bytes() == readme_paths[1].read_bytes(), 'readme mirrors differ'

# The temporary workflow and patch script must not remain in the branch diff.
Path('.github/workflows/agent-unreleased-dates-3284.yml').unlink(missing_ok=True)
Path('.github/agent-patch-gos-3284.py').unlink(missing_ok=True)
