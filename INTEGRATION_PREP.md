# 機能統合

開催情報管理へ、旧モバイル表示管理の機能を統合した。

## 統合済み

- トップスライダーのPC画像管理
- トップスライダーのスマホ画像管理
- スマホ画像の左右・上下位置
- スマホ側スライダー有効状態
- スマホ切替幅
- 本文文字倍率
- 見出し文字倍率
- 左右余白
- ボタン最小高さ
- 投稿・固定ページ・newsごとのスマホ表示設定
- スマホ実画面プレビュー

## 保存互換

既存設定を移行せず引き継ぐ。

- PC画像: `dp_options` / `slider_image1`〜`slider_image3`
- モバイル設定: `mlm_options`
- 投稿単位設定: `_mlm_*`

そのため、既存サイトの設定値を作り直す必要はない。

## 構成

```text
plugins/garden-opening-status/
├─ garden-opening-status.php
└─ includes/
   ├─ garden-opening-status-core.php
   └─ integration/
      ├─ bootstrap.php
      ├─ class-gos-legacy-slider-source.php
      ├─ class-gos-slider-integration-state.php
      ├─ class-gos-slider-settings.php
      ├─ class-gos-slider-admin.php
      ├─ class-gos-slider-frontend.php
      ├─ class-gos-mobile-layout.php
      └─ class-gos-slider-integration.php
```

旧 `plugins/mobile-layout-manager/` は削除済み。

## 管理画面

開催情報管理配下に次を追加。

- トップスライダー
- モバイル表示
- スマホプレビュー

旧プラグインを別途有効化する必要はない。
