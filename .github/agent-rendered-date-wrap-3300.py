from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

for path in paths:
    s = path.read_text(encoding='utf-8')
    assert 'Version: 3.2.99' in s
    assert "const VERSION = '3.2.99';" in s
    assert "add_action('wp_footer', [__CLASS__, 'seasonal_mobile_bottom_spacer'], 101);" in s
    assert 'public static function seasonal_mobile_bottom_spacer()' in s

    s = s.replace('Version: 3.2.99', 'Version: 3.3.0', 1)
    s = s.replace("const VERSION = '3.2.99';", "const VERSION = '3.3.0';", 1)
    s = s.replace('Garden Opening Status 3.2.99 multilingual SEO active', 'Garden Opening Status 3.3.0 multilingual SEO active', 1)
    s = s.replace(
        "        add_action('wp_footer', [__CLASS__, 'seasonal_mobile_bottom_spacer'], 101);\n",
        "        add_action('wp_footer', [__CLASS__, 'seasonal_mobile_bottom_spacer'], 101);\n        add_action('wp_footer', [__CLASS__, 'seasonal_confirmed_date_wrap'], 103);\n",
        1,
    )

    marker = "    public static function seasonal_mobile_bottom_spacer() {\n"
    method = r'''    public static function seasonal_confirmed_date_wrap() {
        $season = self::current_event_page_season();
        if (is_admin() || $season === '') return;

        $options = self::options(false);
        $event = self::event_from_options($options, $season);
        if (sanitize_key((string)($event['date_display_mode'] ?? 'usual')) !== 'confirmed') return;
        if (!self::event_released($event, self::now())) return;

        $start_date = trim((string)($event['start'] ?? ''));
        $end_date = trim((string)($event['end'] ?? ''));
        if ($start_date === '' || $end_date === '') return;

        $start_text = self::format_date_with_weekday($start_date) . '～';
        $end_text = self::format_date_with_weekday($end_date);
        $range_text = $start_text . $end_text;
        ?>
        <script id="gos-seasonal-confirmed-date-wrap">
        (function(){
          var range=<?php echo wp_json_encode($range_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var start=<?php echo wp_json_encode($start_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          var end=<?php echo wp_json_encode($end_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          if(!range||document.querySelector('.gos-event-page-info__confirmed-date-start'))return;
          var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,{acceptNode:function(node){
            if(!node.nodeValue||node.nodeValue.indexOf(range)===-1)return NodeFilter.FILTER_REJECT;
            var p=node.parentElement;
            if(!p||/^(SCRIPT|STYLE|TEXTAREA|CODE|PRE)$/i.test(p.tagName))return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
          }});
          var node=walker.nextNode();
          if(!node)return;
          var text=node.nodeValue,pos=text.indexOf(range);
          var frag=document.createDocumentFragment();
          if(pos>0)frag.appendChild(document.createTextNode(text.slice(0,pos)));
          var a=document.createElement('span');a.className='gos-event-page-info__confirmed-date-start';a.textContent=start;frag.appendChild(a);
          var b=document.createElement('span');b.className='gos-event-page-info__confirmed-date-end';b.textContent=end;frag.appendChild(b);
          if(pos+range.length<text.length)frag.appendChild(document.createTextNode(text.slice(pos+range.length)));
          node.parentNode.replaceChild(frag,node);
        })();
        </script>
        <?php
    }

'''
    assert marker in s
    s = s.replace(marker, method + marker, 1)
    path.write_text(s, encoding='utf-8')

readme = Path('garden-opening-status/readme.txt')
rt = readme.read_text(encoding='utf-8')
assert rt.startswith('開催情報・開催状況管理 3.2.99')
entry = '''開催情報・開催状況管理 3.3.0\n\n3.3.0:\n- 固定会期ページの実表示経路でも、確定会期が公開されている場合だけスマホで終了日を2行目へ送るよう修正しました。\n- 実際に描画された確定会期文字列を対象にするため、固定ページ側の出力方式に依存しません。例年表示・PC表示・会期文言・SEO・公開日制御には変更ありません。\n\n'''
readme.write_text(entry + rt, encoding='utf-8')
copyfile(readme, Path('plugins/garden-opening-status/readme.txt'))
