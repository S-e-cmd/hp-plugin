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
    s = path.read_text()
    assert s.count(' * Version: 3.3.1') == 1, path
    assert s.count("    const VERSION = '3.3.1';") == 1, path
    s = s.replace(' * Version: 3.3.1', ' * Version: 3.3.2', 1)
    s = s.replace("    const VERSION = '3.3.1';", "    const VERSION = '3.3.2';", 1)

    hook = "        add_action('admin_init', [__CLASS__, 'handle_save']);\n"
    assert s.count(hook) == 1, path
    s = s.replace(hook, hook + "        add_action('gos_v3_scheduled_publish', [__CLASS__, 'scheduled_publish_cache_purge'], 10, 1);\n", 1)

    save_old = """        update_option(self::OPTION, $clean, false);\n        update_option(self::VERSION_OPTION, self::VERSION, false);\n\n        // 公開ページは最終HTMLへ反映するため、固定ページ本文は変更しない。\n        self::purge_public_caches($clean);\n"""
    save_new = """        update_option(self::OPTION, $clean, false);\n        update_option(self::VERSION_OPTION, self::VERSION, false);\n\n        // 指定日時公開は公開時刻にもキャッシュを更新できるよう、会期ごとに単発cronを張り直す。\n        self::schedule_publish_cache_purges($clean);\n\n        // 公開ページは最終HTMLへ反映するため、固定ページ本文は変更しない。\n        self::purge_public_caches($clean);\n"""
    assert s.count(save_old) == 1, path
    s = s.replace(save_old, save_new, 1)

    marker = "    private static function purge_public_caches($options) {\n"
    assert s.count(marker) == 1, path
    methods = r'''    private static function schedule_publish_cache_purges($options) {
        $now = self::now();
        foreach (self::event_keys() as $season) {
            $args = [$season];
            while ($timestamp = wp_next_scheduled('gos_v3_scheduled_publish', $args)) {
                wp_unschedule_event($timestamp, 'gos_v3_scheduled_publish', $args);
            }

            $event = self::event_from_options($options, $season);
            if (sanitize_key((string)($event['publish_mode'] ?? 'immediate')) !== 'scheduled') continue;
            $at = self::publish_dt($event['publish_at'] ?? '');
            if (!$at || $at <= $now) continue;

            wp_schedule_single_event($at->getTimestamp(), 'gos_v3_scheduled_publish', $args, true);
        }
    }

    public static function scheduled_publish_cache_purge($season = '') {
        $season = sanitize_key((string)$season);
        if (!self::is_event_key($season)) return;

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        if (sanitize_key((string)($event['publish_mode'] ?? 'immediate')) !== 'scheduled') return;
        if (!self::event_released($event, self::now())) return;

        self::purge_public_caches($options);
    }

'''
    s = s.replace(marker, methods + marker, 1)
    path.write_text(s)

entry = '''開催情報・開催状況管理 3.3.2

3.3.2:
- 「指定日時に公開」を保存した際、各会期の公開日時に単発WP-Cronを予約し、公開時刻到達後に公開ページのキャッシュを自動削除するようにしました。
- 設定を保存し直した場合は古い公開予約を会期ごとに解除して張り直します。即時公開・手動公開の判定、会期表示、SEO、本文には変更ありません。

'''
for path in readme_paths:
    s = path.read_text()
    assert s.startswith('開催情報・開催状況管理 3.3.1\n'), path
    path.write_text(entry + s)
