# 機能統合に向けた準備

現時点では、開催情報管理とモバイル表示管理の機能統合は行わない。
大きな構造変更・保存形式変更・画面統合を避け、まず既存契約と責務境界を固定する。

## 対象

- `plugins/garden-opening-status/`
- `plugins/mobile-layout-manager/`

## 現在の責務

### 開催情報管理

- 春・秋・冬の会期情報
- 公開状態・予約公開
- トップページ開催状況
- SEO / 構造化データ
- 会期ページ表示補正
- Instagram表示
- 管理画面プレビュー

### モバイル表示管理

- スマホ表示全体の補正
- 投稿・固定ページ単位のスマホ表示補正
- トップスライダーのスマホ専用画像
- スマホ専用画像の表示位置
- スマホ実画面プレビュー

## 統合時に引き継ぐ既存契約

### モバイル表示管理

- option: `mlm_options`
- preview draft user meta: `_mlm_preview_draft`
- post meta:
  - `_mlm_enabled`
  - `_mlm_font_scale`
  - `_mlm_heading_scale`
  - `_mlm_side_padding`
  - `_mlm_hide_thumbnail`
- テーマ側PCスライダー参照:
  - option: `dp_options`
  - `slider_image1`
  - `slider_image2`
  - `slider_image3`

### 開催情報管理

- option: `garden_opening_status_options`
- version option: `garden_opening_status_version`
- layout option: `gos_v3_layout_templates`
- default layout option: `gos_v3_default_layout_template`

既存のoption名・投稿メタ名は、統合時に明示的な移行処理を用意するまで変更しない。

## 統合候補

最初の統合対象はトップスライダー管理に限定する。

想定する最終形:

```text
開催情報管理
└─ トップページ管理
   └─ スライダー1〜3
      ├─ PC画像
      ├─ スマホ画像
      └─ スマホ表示位置
```

PC画像は既存テーマの `dp_options` を正本として扱う。
スマホ専用画像は、統合完了までは `mlm_options` を正本として維持する。

## 現段階で行わないこと

- `mlm_options` の移行
- 投稿メタの移行
- 管理画面メニューの統合
- フロントフックの移動
- スライダー描画方式の変更
- テーマの `dp_options` 書き換え実装
- 旧プラグイン停止処理
- 開催情報管理本体の大規模分割

## 次段階へ進む条件

以下を確認してから実装へ進む。

1. PCスライダーの保存・表示契約を確定
2. スマホ専用画像の既存表示経路を確定
3. 二重適用を防ぐ切替条件を決定
4. `mlm_options` からの移行方法を決定
5. 旧プラグインを停止できる完了条件を決定

この準備段階では、既存公開表示と保存データを変更しない。
