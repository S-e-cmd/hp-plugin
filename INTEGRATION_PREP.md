# モバイル表示機能の統合

旧「モバイル表示管理」の機能は、開催情報管理へ統合する。

## 保存データ

既存サイトの設定をそのまま引き継ぐため、保存先は変更しない。

- PCスライダー画像: `dp_options` / `slider_image1`〜`slider_image3`
- スマホスライダー画像・位置: `mlm_options['slider']`
- スマホスライダー有効状態: `mlm_options['top_enabled']`
- モバイル表示全体設定: `mlm_options`
- ページ別設定: `_mlm_*` 投稿メタ

設定値のコピーや一括移行は不要。

## 開催情報管理へ移した機能

- PC/スマホのトップスライダー画像管理
- スマホ画像の左右・上下位置
- スマホ切替幅
- トップスライダーのスマホ画像描画
- 本文文字倍率
- 見出し文字倍率
- 左右余白
- ボタン最小高さ
- 投稿・固定ページ・お知らせ単位のスマホ上書き
- スマホでのアイキャッチ非表示
- スマホ実画面プレビュー
- 管理バーのスマホプレビュー導線

## 構成

```text
plugins/garden-opening-status/
├─ garden-opening-status.php        # WordPressプラグイン入口
├─ garden-opening-status-core.php   # 3.3.2既存本体
└─ includes/integration/
   ├─ bootstrap.php
   ├─ class-gos-legacy-slider-source.php
   ├─ class-gos-slider-integration-state.php
   ├─ class-gos-slider-settings.php
   ├─ class-gos-slider-admin.php
   ├─ class-gos-slider-frontend.php
   ├─ class-gos-mobile-layout.php
   └─ class-gos-slider-integration.php
```

既存3.3.2本体の内容は `garden-opening-status-core.php` へそのまま保持し、入口ファイルから統合モジュールを読み込む。既存本体のロジックへ統合コードを混在させない。

## 旧プラグイン

`plugins/mobile-layout-manager/` は統合後の重複を避けるため削除した。
Git履歴には残るため、旧実装の確認が必要な場合は過去コミットを参照できる。

## 表示契約

テーマ側でPC画像が未設定のスロットは、スマホ画像だけが設定されていても新しいスライドとして追加しない。

スマホ画像はテーマ側スライダーの再描画後にも再適用する。MutationObserverとスライダーイベント監視は、旧実装で必要だった挙動を維持する。
