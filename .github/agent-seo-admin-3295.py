from pathlib import Path
p=Path('garden-opening-status/garden-opening-status.php')
s=p.read_text()

s=s.replace('Version: 3.2.94','Version: 3.2.95',1).replace("const VERSION = '3.2.94';","const VERSION = '3.2.95';",1).replace('Garden Opening Status 3.2.94 multilingual SEO active','Garden Opening Status 3.2.95 multilingual SEO active',1)

# defaults: add SEO settings
anchor="""            'permanent_guide' => [
                'enabled' => 1,
                'url' => home_url('/news/notice/'),
                'show_home' => 1,
                'show_news_archive' => 1,
                'exclude_from_news_list' => 1,
                'show_modified_date' => 1,
                'eyebrow' => 'ご来苑前にご確認ください',
            ],
            'events' => [
"""
repl="""            'permanent_guide' => [
                'enabled' => 1,
                'url' => home_url('/news/notice/'),
                'show_home' => 1,
                'show_news_archive' => 1,
                'exclude_from_news_list' => 1,
                'show_modified_date' => 1,
                'eyebrow' => 'ご来苑前にご確認ください',
            ],
            'seo' => self::seo_defaults(),
            'events' => [
"""
assert anchor in s
s=s.replace(anchor,repl,1)

# add SEO defaults/helpers before defaults()
anchor="""    private static function defaults() {
"""
add="""    private static function seo_defaults() {
        return [
            'home' => [
                'title' => '東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑',
                'description' => '東京・上野公園にある上野東照宮ぼたん苑。回遊形式の日本庭園で、春と冬の牡丹、秋のダリアなど季節の花を楽しめます。上野観光・東京の庭園散策にもおすすめです。',
            ],
            'schedule' => [
                'title' => '東京・上野で楽しむ季節の花｜春の牡丹・秋のダリア・冬の牡丹',
                'description' => '東京・上野の上野東照宮ぼたん苑では、春の牡丹、秋のダリア、冬咲き牡丹を季節ごとに公開しています。各会期の開催情報、開苑時期、詳細をご案内します。',
            ],
            'spring' => [
                'title' => '東京・上野で牡丹を楽しむ｜{event}｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する{event}。日本庭園で牡丹をはじめとする春の花を楽しめます。{usual_period_sentence}開苑時間、入苑料、アクセスをご案内します。',
                'confirmed_title' => '{event}｜{date_range}｜東京・上野の上野東照宮ぼたん苑',
                'confirmed_description' => '{confirmed_sentence}東京・上野の上野東照宮ぼたん苑で、牡丹をはじめとする春の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
            ],
            'autumn' => [
                'title' => '東京・上野でダリアを楽しむ｜{event}｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する{event}。秋の庭園で多彩なダリアを楽しめます。{usual_period_sentence}開苑時間、入苑料、アクセスをご案内します。',
                'confirmed_title' => '{event}｜{date_range}｜東京・上野の上野東照宮ぼたん苑',
                'confirmed_description' => '{confirmed_sentence}東京・上野の上野東照宮ぼたん苑で、多彩なダリアをはじめとする秋の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
            ],
            'winter' => [
                'title' => '東京・上野で冬咲き牡丹を楽しむ｜{event}｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で楽しむ冬咲き牡丹。冬の日本庭園で牡丹を観賞できます。{usual_period_sentence}開苑時間、入苑料、アクセスをご案内します。',
                'confirmed_title' => '{event}｜{date_range}｜東京・上野の上野東照宮ぼたん苑',
                'confirmed_description' => '{confirmed_sentence}東京・上野の上野東照宮ぼたん苑で、冬咲き牡丹を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
            ],
            'access' => [
                'title' => '上野東照宮ぼたん苑へのアクセス｜上野駅・上野公園からの行き方',
                'description' => '東京・上野公園にある上野東照宮ぼたん苑へのアクセスをご案内します。上野駅からの行き方、周辺交通、所在地など、来苑前に確認したい情報をまとめています。',
            ],
            'notice' => [
                'title' => '上野東照宮ぼたん苑の入苑案内｜料金・支払い・撮影・注意事項',
                'description' => '上野東照宮ぼたん苑のご入苑案内です。入苑料のお支払い、入苑券、撮影、駐車場・駐輪場など、ご来苑前に確認したい注意事項をご案内します。',
            ],
            'contact' => [
                'title' => '上野東照宮ぼたん苑へのお問い合わせ｜入苑・取材・各種ご相談',
                'description' => '上野東照宮ぼたん苑へのお問い合わせはこちらから。入苑に関するご質問、取材・メディア関連、その他のご相談についてご案内しています。',
            ],
            'english' => [
                'title' => 'Ueno Toshogu Peony Garden | Peonies, Dahlias & Seasonal Flowers in Tokyo',
                'description' => 'Visit Ueno Toshogu Peony Garden in Ueno, Tokyo. Enjoy spring and winter peonies, autumn dahlias, seasonal flowers, a traditional Japanese garden atmosphere, and easy access from Ueno Station.',
            ],
            'chinese' => [
                'title' => '上野東照宮牡丹園｜東京上野賞牡丹、大麗花與四季花卉',
                'description' => '上野東照宮牡丹園位於東京上野公園，可欣賞春季與冬季牡丹、秋季大麗花及四季花卉，感受日式庭園景致。從JR上野站公園出口步行約5分鐘。',
            ],
        ];
    }

    private static function seo_value($key, $field) {
        $defaults = self::seo_defaults();
        $fallback = (string)($defaults[$key][$field] ?? '');
        $options = self::options(false);
        $saved = trim((string)($options['seo'][$key][$field] ?? ''));
        return $saved !== '' ? $saved : $fallback;
    }

    private static function replace_seo_tokens($text, $tokens) {
        return strtr((string)$text, $tokens);
    }

"""+anchor
assert anchor in s
s=s.replace(anchor,add,1)

# multilingual config and desc
s=s.replace("'title' => 'Ueno Toshogu Peony Garden | Peonies & Dahlias in Tokyo',","'title' => self::seo_value('english', 'title'),",1)
s=s.replace("'title' => '上野東照宮牡丹園｜東京上野賞牡丹・大麗花',","'title' => self::seo_value('chinese', 'title'),",1)
s=s.replace("return 'Ueno Toshogu Peony Garden in central Tokyo presents the Wintertime Peony Festival, Springtime Peony Festival, and Special Festival - Autumn Dahlia Garden.';","return self::seo_value('english', 'description');",1)
s=s.replace("return '上野東照宮牡丹園位於東京都心，舉辦冬季牡丹園、春季牡丹節及特別祭典-秋季大麗花園，並提供參觀與交通資訊。';","return self::seo_value('chinese', 'description');",1)

# configs fixed pages
repls={
"'title' => '東京・上野の日本庭園｜季節の花を楽しむ上野東照宮ぼたん苑',":"'title' => self::seo_value('home', 'title'),",
"'description' => '東京・上野公園にある上野東照宮ぼたん苑。回遊形式の日本庭園で、春と冬の牡丹、秋のダリアなど季節の花を楽しめます。上野観光・東京の庭園散策にもおすすめです。',":"'description' => self::seo_value('home', 'description'),",
"'title' => '東京・上野で楽しむ季節の花｜春の牡丹・秋のダリア・冬の牡丹',":"'title' => self::seo_value('schedule', 'title'),",
"'description' => '東京・上野の上野東照宮ぼたん苑では、春の牡丹、秋のダリア、冬咲き牡丹を季節ごとに公開しています。各会期の開催情報、開苑時期、詳細をご案内します。',":"'description' => self::seo_value('schedule', 'description'),",
"'title' => '上野東照宮ぼたん苑へのアクセス｜上野駅・上野公園からの行き方',":"'title' => self::seo_value('access', 'title'),",
"'description' => '東京・上野公園にある上野東照宮ぼたん苑へのアクセスをご案内します。上野駅からの行き方、周辺交通、所在地など、来苑前に確認したい情報をまとめています。',":"'description' => self::seo_value('access', 'description'),",
"'title' => '上野東照宮ぼたん苑の入苑案内｜料金・支払い・撮影・注意事項',":"'title' => self::seo_value('notice', 'title'),",
"'description' => '上野東照宮ぼたん苑のご入苑案内です。入苑料のお支払い、入苑券、撮影、駐車場・駐輪場など、ご来苑前に確認したい注意事項をご案内します。',":"'description' => self::seo_value('notice', 'description'),",
"'title' => '上野東照宮ぼたん苑へのお問い合わせ｜入苑・取材・各種ご相談',":"'title' => self::seo_value('contact', 'title'),",
"'description' => '上野東照宮ぼたん苑へのお問い合わせはこちらから。入苑に関するご質問、取材・メディア関連、その他のご相談についてご案内しています。',":"'description' => self::seo_value('contact', 'description'),",
}
for a,b in repls.items():
    assert a in s,a
    s=s.replace(a,b,1)

# seasonal confirmed/usual block
old="""                if ($is_spring) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、牡丹をはじめとする春の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
                if ($is_autumn) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、多彩なダリアをはじめとする秋の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
                if ($is_winter) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、冬咲き牡丹を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
"""
new="""                if ($is_spring || $is_autumn || $is_winter) {
                    $tokens = [
                        '{event}' => $name,
                        '{date_range}' => $range,
                        '{confirmed_sentence}' => $description,
                        '{usual_period_sentence}' => '',
                    ];
                    return [
                        'title' => self::replace_seo_tokens(self::seo_value($season, 'confirmed_title'), $tokens),
                        'description' => self::replace_seo_tokens(self::seo_value($season, 'confirmed_description'), $tokens),
                    ];
                }
"""
assert old in s
s=s.replace(old,new,1)
old="""        if ($is_spring) {
            return [
                'title' => '東京・上野で牡丹を楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する' . $name . '。日本庭園で牡丹をはじめとする春の花を楽しめます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }
        if ($is_autumn) {
            return [
                'title' => '東京・上野でダリアを楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する' . $name . '。秋の庭園で多彩なダリアを楽しめます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }
        if ($is_winter) {
            return [
                'title' => '東京・上野で冬咲き牡丹を楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で楽しむ冬咲き牡丹。冬の日本庭園で牡丹を観賞できます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }
"""
new="""        if ($is_spring || $is_autumn || $is_winter) {
            $tokens = [
                '{event}' => $name,
                '{date_range}' => '',
                '{confirmed_sentence}' => '',
                '{usual_period_sentence}' => $usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '',
            ];
            return [
                'title' => self::replace_seo_tokens(self::seo_value($season, 'title'), $tokens),
                'description' => self::replace_seo_tokens(self::seo_value($season, 'description'), $tokens),
            ];
        }
"""
assert old in s
s=s.replace(old,new,1)

# sanitize SEO after permanent guide
anchor="""        $out['permanent_guide'] = [
            'enabled' => !empty($guide['enabled']) ? 1 : 0,
            'url' => esc_url_raw($guide['url'] ?? ''),
            'show_home' => !empty($guide['show_home']) ? 1 : 0,
            'show_news_archive' => !empty($guide['show_news_archive']) ? 1 : 0,
            'exclude_from_news_list' => !empty($guide['exclude_from_news_list']) ? 1 : 0,
            'show_modified_date' => !empty($guide['show_modified_date']) ? 1 : 0,
            'eyebrow' => sanitize_text_field($guide['eyebrow'] ?? ''),
        ];

        foreach (self::event_keys() as $key) {
"""
repl="""        $out['permanent_guide'] = [
            'enabled' => !empty($guide['enabled']) ? 1 : 0,
            'url' => esc_url_raw($guide['url'] ?? ''),
            'show_home' => !empty($guide['show_home']) ? 1 : 0,
            'show_news_archive' => !empty($guide['show_news_archive']) ? 1 : 0,
            'exclude_from_news_list' => !empty($guide['exclude_from_news_list']) ? 1 : 0,
            'show_modified_date' => !empty($guide['show_modified_date']) ? 1 : 0,
            'eyebrow' => sanitize_text_field($guide['eyebrow'] ?? ''),
        ];
        $seo_input = is_array($input['seo'] ?? null) ? $input['seo'] : [];
        foreach (self::seo_defaults() as $seo_key => $seo_fields) {
            $src = is_array($seo_input[$seo_key] ?? null) ? $seo_input[$seo_key] : [];
            foreach ($seo_fields as $field => $default_value) {
                $out['seo'][$seo_key][$field] = sanitize_textarea_field($src[$field] ?? '');
            }
        }

        foreach (self::event_keys() as $key) {
"""
assert anchor in s
s=s.replace(anchor,repl,1)

# admin page: add SEO section after permanent guide.
anchor="""                        <section class="gos3-card">
                            <h2>イベント・会期概要</h2>
"""
section="""                        <section class="gos3-card" id="gos3-seo-settings">
                            <h2>SEO設定</h2>
                            <p class="description">検索結果・OG・Twitter・schemaへ同期する文言です。空欄で保存した項目はプラグイン既定値を使用します。meta keywords欄はありません。</p>
                            <?php
                            $seo_labels = [
                                'home' => 'トップ', 'schedule' => '会期一覧', 'spring' => '春', 'autumn' => '秋', 'winter' => '冬',
                                'access' => 'アクセス', 'notice' => 'ご入苑案内', 'contact' => 'お問い合わせ', 'english' => '英語', 'chinese' => '繁体字',
                            ];
                            $seasonal_seo_keys = ['spring','autumn','winter'];
                            foreach ($seo_labels as $seo_key => $seo_label):
                                $seo = $o['seo'][$seo_key] ?? [];
                            ?>
                            <details class="gos3-seo-page" <?php echo $seo_key === 'home' ? 'open' : ''; ?>>
                                <summary><strong><?php echo esc_html($seo_label); ?></strong></summary>
                                <div class="gos3-fields">
                                    <label class="wide">SEOタイトル<input type="text" name="seo[<?php echo esc_attr($seo_key); ?>][title]" value="<?php echo esc_attr($seo['title'] ?? ''); ?>"></label>
                                    <label class="wide">説明文<textarea name="seo[<?php echo esc_attr($seo_key); ?>][description]" rows="3"><?php echo esc_textarea($seo['description'] ?? ''); ?></textarea></label>
                                    <?php if (in_array($seo_key, $seasonal_seo_keys, true)): ?>
                                        <label class="wide">確定会期公開時のSEOタイトル<input type="text" name="seo[<?php echo esc_attr($seo_key); ?>][confirmed_title]" value="<?php echo esc_attr($seo['confirmed_title'] ?? ''); ?>"></label>
                                        <label class="wide">確定会期公開時の説明文<textarea name="seo[<?php echo esc_attr($seo_key); ?>][confirmed_description]" rows="3"><?php echo esc_textarea($seo['confirmed_description'] ?? ''); ?></textarea></label>
                                        <p class="description wide">自動置換：<code>{event}</code> 催し名、<code>{date_range}</code> 確定会期、<code>{usual_period_sentence}</code> 例年時期の一文、<code>{confirmed_sentence}</code> 確定日を含む開催文。確定日そのものは従来どおり公開条件を満たした場合だけ出ます。</p>
                                    <?php endif; ?>
                                </div>
                            </details>
                            <?php endforeach; ?>
                        </section>

"""+anchor
assert anchor in s
s=s.replace(anchor,section,1)

p.write_text(s)
print('patched',p)

from shutil import copyfile
copyfile('garden-opening-status/garden-opening-status.php', 'plugins/garden-opening-status/garden-opening-status.php')
entry = """開催情報・開催状況管理 3.2.95

3.2.95:
- 管理画面にSEO設定を追加し、トップ・会期一覧・春・秋・冬・アクセス・ご入苑案内・お問い合わせ・英語・繁体字のtitle / descriptionを編集可能化
- 春・秋・冬は通常時に加えて確定会期公開時のtitle / descriptionも編集可能化し、{event} / {date_range} / {usual_period_sentence} / {confirmed_sentence}の自動置換を維持
- 空欄設定は3.2.94までの既定値へfallbackし、OG / Twitter / schemaは既存どおりSEO設定へ自動同期
- 英語SEOを「Peonies, Dahlias & Seasonal Flowers in Tokyo」と上野駅アクセスを含む説明文へ更新
- 繁体字SEOを「東京上野賞牡丹、大麗花與四季花卉」とJR上野站徒歩約5分を含む説明文へ更新
- meta keywords欄は追加せず、表示本文・見出し・フォーム・レイアウト、Event schemaの公開条件は変更なし

"""
r = Path('garden-opening-status/readme.txt')
t = r.read_text()
assert t.startswith('開催情報・開催状況管理 3.2.94')
r.write_text(entry + t)
copyfile('garden-opening-status/readme.txt', 'plugins/garden-opening-status/readme.txt')
