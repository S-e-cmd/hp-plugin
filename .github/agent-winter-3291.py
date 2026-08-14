from pathlib import Path
P=[Path('garden-opening-status/garden-opening-status.php'),Path('plugins/garden-opening-status/garden-opening-status.php')]
for p in P:
 s=p.read_text()
 s=s.replace('Version: 3.2.90','Version: 3.2.91',1).replace("const VERSION = '3.2.90';","const VERSION = '3.2.91';",1).replace('Garden Opening Status 3.2.90 multilingual SEO active','Garden Opening Status 3.2.91 multilingual SEO active',1)
 a="$is_autumn = $season === 'autumn';"; assert a in s; s=s.replace(a,a+"\n        $is_winter = $season === 'winter';",1)
 a="""                if ($is_autumn) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、多彩なダリアをはじめとする秋の花を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
"""
 b=a+"""                if ($is_winter) {
                    return [
                        'title' => $name . '｜' . $range . '｜東京・上野の上野東照宮ぼたん苑',
                        'description' => $description . '東京・上野の上野東照宮ぼたん苑で、冬咲き牡丹を楽しめます。開苑時間、入苑料、アクセスなどをご案内します。',
                    ];
                }
"""; assert a in s; s=s.replace(a,b,1)
 a="""        if ($is_autumn) {
            return [
                'title' => '東京・上野でダリアを楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で開催する' . $name . '。秋の庭園で多彩なダリアを楽しめます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }
"""
 b=a+"""        if ($is_winter) {
            return [
                'title' => '東京・上野で冬咲き牡丹を楽しむ｜' . $name . '｜上野東照宮ぼたん苑',
                'description' => '東京・上野の上野東照宮ぼたん苑で楽しむ冬咲き牡丹。冬の日本庭園で牡丹を観賞できます。' . ($usual !== '' ? '例年の開苑時期は' . $usual . 'です。' : '') . '開苑時間、入苑料、アクセスをご案内します。',
            ];
        }
"""; assert a in s; p.write_text(s.replace(a,b,1))
E="""開催情報・開催状況管理 3.2.91

3.2.91:
- 冬の固定会期ページ /schedule/winter/ の表示本文を変更せず、「東京・上野・冬咲き牡丹・冬の花」文脈をtitle / descriptionへ追加
- 通常時は例年会期を維持し、確定会期公開後は既存の日付連動を維持したままtitle / descriptionへ東京・上野・冬咲き牡丹の文脈を追加
- AIOSEO経由のOG / Twitter / WebPage schemaへの既存同期を維持
- Event schema、春・秋、トップ、会期一覧、英語・繁体字、本文・見出し・レイアウトには変更なし

"""
for p in [Path('garden-opening-status/readme.txt'),Path('plugins/garden-opening-status/readme.txt')]:
 s=p.read_text(); assert s.startswith('開催情報・開催状況管理 3.2.90'); p.write_text(E+s)
