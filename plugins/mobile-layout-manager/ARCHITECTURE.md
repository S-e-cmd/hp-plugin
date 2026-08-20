# モバイル表示管理プラグイン内部構成

## 目的

既存テーマを変更せず、スマホ表示補正とトップスライダーのスマホ画像差し替えを担当します。

## 1.6.2 の責務分離

- `mobile-layout-manager.php`
  - プラグイン定義
  - trait読込
  - WordPressフック登録

- `includes/trait-mlm-config.php`
  - option既定値
  - 保存値読込
  - 入力値の検証・正規化
  - テーマ側スライダー情報参照

- `includes/trait-mlm-admin.php`
  - 管理画面
  - メディア選択UI
  - 実画面プレビュー
  - 投稿・固定ページ単位のスマホ設定

- `includes/trait-mlm-frontend.php`
  - スマホ表示CSS
  - トップスライダー画像差し替え
  - body class
  - プレビュー時キャッシュ制御

## 維持するデータ契約

- `mlm_options`
- `_mlm_enabled`
- `_mlm_font_scale`
- `_mlm_heading_scale`
- `_mlm_side_padding`
- `_mlm_hide_thumbnail`
- テーマ側 `dp_options` の `slider_image1` ～ `slider_image3` は参照のみ

## 将来の統合に備えた境界

トップスライダー機能を別プラグインへ移す場合でも、まず `slider` 設定とフロント差し替え処理だけを移動対象とします。本文文字倍率・余白・個別ページ設定・プレビューは別責務として扱い、同時移行しません。
