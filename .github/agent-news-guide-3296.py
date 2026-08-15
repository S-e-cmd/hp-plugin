from pathlib import Path
from shutil import copyfile

paths = [
    Path('garden-opening-status/garden-opening-status.php'),
    Path('plugins/garden-opening-status/garden-opening-status.php'),
]

old = """          function insertCard(){
            if(!showCard || document.querySelector('.gos-permanent-guide-card'))return;
            var heading=null;
            Array.prototype.some.call(document.querySelectorAll('h1,h2,h3,h4'),function(h){if(text(h)==='お知らせ'){heading=h;return true}return false});
            if(!heading)return;
            heading.insertAdjacentHTML('afterend',cardHtml);
          }
"""
new = """          function insertCard(){
            if(!showCard || document.querySelector('.gos-permanent-guide-card'))return;

            if(isNewsArchive){
              var firstNewsItem=null;
              Array.prototype.some.call(document.querySelectorAll('article,.article02,.news-item,.post-item,.archive-item'),function(item){
                if(item.closest('header,nav,footer,.gos-permanent-guide-card'))return false;
                var hasLink=!!item.querySelector('a[href]');
                var hasDate=!!item.querySelector('time,.entry-date,.post-date,.article-date') || /\\d{4}[.\\/-]\\d{1,2}[.\\/-]\\d{1,2}/.test(item.textContent||'');
                if(!hasLink || !hasDate)return false;
                firstNewsItem=item;
                return true;
              });
              if(firstNewsItem && firstNewsItem.parentNode){
                var holder=document.createElement('div');
                holder.innerHTML=cardHtml;
                var newsCard=holder.firstElementChild;
                if(newsCard){
                  newsCard.classList.add('gos-permanent-guide-card--news');
                  firstNewsItem.parentNode.insertBefore(newsCard,firstNewsItem);
                  return;
                }
              }
            }

            var heading=null;
            Array.prototype.some.call(document.querySelectorAll('h1,h2,h3,h4'),function(h){if(text(h)==='お知らせ'){heading=h;return true}return false});
            if(!heading)return;
            heading.insertAdjacentHTML('afterend',cardHtml);
          }
"""

for p in paths:
    s = p.read_text()
    assert "Version: 3.2.95" in s
    assert "const VERSION = '3.2.95';" in s
    assert old in s
    s = s.replace('Version: 3.2.95', 'Version: 3.2.96', 1)
    s = s.replace("const VERSION = '3.2.95';", "const VERSION = '3.2.96';", 1)
    s = s.replace('Garden Opening Status 3.2.95 multilingual SEO active', 'Garden Opening Status 3.2.96 multilingual SEO active', 1)
    s = s.replace("        .gos-permanent-guide-card>a:hover{text-decoration:underline!important}\n", "        .gos-permanent-guide-card>a:hover{text-decoration:underline!important}\n        .gos-permanent-guide-card--news{width:100%;max-width:none;margin:0 0 28px}\n", 1)
    s = s.replace("          var showCard=<?php echo $show_card ? 'true' : 'false'; ?>;\n", "          var showCard=<?php echo $show_card ? 'true' : 'false'; ?>;\n          var isNewsArchive=<?php echo (!$is_guide && ($path === 'news' || is_post_type_archive() || is_home())) ? 'true' : 'false'; ?>;\n", 1)
    s = s.replace(old, new, 1)
    p.write_text(s)

assert paths[0].read_bytes() == paths[1].read_bytes()

readmes = [Path('garden-opening-status/readme.txt'), Path('plugins/garden-opening-status/readme.txt')]
r = readmes[0]
t = r.read_text()
assert t.startswith('開催情報・開催状況管理 3.2.95')
entry = """開催情報・開催状況管理 3.2.96

3.2.96:
- お知らせ一覧の常設案内カードをメインビジュアル内ではなく、記事一覧の直前へ配置するよう変更しました。
- ニュース一覧ではカード幅を記事一覧コンテナに合わせます。トップページ側の常設案内カード、常設案内本体、SEO設定には変更ありません。

"""
r.write_text(entry + t)
copyfile(r, readmes[1])
