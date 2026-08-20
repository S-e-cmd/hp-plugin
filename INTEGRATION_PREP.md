# 機能統合に向けた準備

開催情報管理とモバイル表示管理はいきなり統合しない。まずトップスライダー部分だけについて、既存の保存先と責務を壊さずに受け渡せる形を作る。

## 今回の対象

最初の統合対象はトップスライダー管理だけ。

- PC画像: テーマの `dp_options` / `slider_image1`〜`slider_image3`
- スマホ画像・位置: `mlm_options['slider']`
- スマホ側有効状態: `mlm_options['top_enabled']`
- 切替幅: `mlm_options['breakpoint']`

投稿・固定ページごとのモバイル補正、文字倍率、余白、ボタン高さ、`_mlm_*` 投稿メタは今回の統合対象に含めない。

## 現在の表示契約

PC画像が未設定のslotはテーマ側でスライド自体が生成されない。スマホ画像だけを設定しても独立したスライドとして追加しない。

モバイル表示管理は現在、スマホ画像をCSSとJavaScriptの両方から再適用している。テーマ側の再描画対策なので、統合準備段階ではこの表示処理を変更しない。

## 統合用の受け渡し口

開催情報管理側には、既存設定を直接触る箇所を増やさないための最小限の読み取り口だけ置く。

```text
plugins/garden-opening-status/includes/integration/
├─ bootstrap.php
├─ class-gos-legacy-slider-source.php
├─ class-gos-slider-integration-state.php
└─ class-gos-slider-integration.php
```

役割は以下だけ。

- `GOS_Legacy_Slider_Source`: `dp_options` と `mlm_options` を読む
- `GOS_Slider_Integration_State`: PC/スマホの差を吸収して同じ形へ揃える
- `GOS_Slider_Integration::read()`: 今後の統合側が使う入口
- `bootstrap.php`: 上記3クラスを読み込むだけ

診断・readiness・contract・view-modelのような準備専用レイヤーは持たない。必要になった機能は、実際の統合工程で必要な場所に追加する。

## 現段階で変えないもの

- `dp_options` の保存形式
- `mlm_options` の保存形式
- 公開画面のスライダー表示
- モバイル表示管理のフック
- 管理画面メニュー
- 旧プラグインの有効状態
- 開催情報管理3.3.2の既存機能

## 次の実装順

1. 開催情報管理本体からintegration bootstrapを読み込む
2. 開催情報管理に「トップスライダー管理」の画面枠を追加し、まず現在値を表示する
3. PC画像3枠を同画面から変更できるようにする
4. スマホ画像・位置設定を同画面へ移す
5. 表示処理の所有権を開催情報管理へ切り替える
6. モバイル表示管理からトップスライダー機能を削除する
7. モバイル表示管理に他の機能が残らなければプラグイン自体を外す

切替途中で新旧の両方が同じスライダーを上書きする状態は作らない。

現段階は「統合コードを増やす段階」ではなく、「現在の保存値を一つの入口から読める状態にして、次の画面統合へ進める段階」とする。
