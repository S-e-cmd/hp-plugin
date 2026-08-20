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

## トップスライダーの現行契約

### PC画像

PC側の画像はテーマの `dp_options` を正本とする。

- slot 1: `slider_image1`
- slot 2: `slider_image2`
- slot 3: `slider_image3`

テーマ側でPC画像が未設定のslotは、現行テーマ上でスライド自体が生成されない。
そのため、スマホ専用画像だけが設定されていても、そのslotを単独で追加表示しない。

### スマホ画像

スマホ側は `mlm_options['slider']` を正本とする。

各slotは以下を持つ。

- `image_id`
- `position_x`
- `position_y`

加えて、トップスライダー専用の有効状態は `mlm_options['top_enabled']`、切替幅は `mlm_options['breakpoint']` を使用する。

### 現在の表示経路

モバイル表示管理は、スマホ画像の適用を2経路で補強している。

1. `wp_head` のCSSで背景画像・表示位置を指定
2. `wp_footer` のJavaScriptでテーマ側スライダーの再描画後にも再適用

JavaScript側は MutationObserver とスライダーイベントを監視し、テーマ側がstyleを書き戻した場合にもスマホ画像を再設定する。

統合時にこの二重経路を単純化する場合は、公開画面でテーマ側の再描画挙動を確認してから行う。
準備段階では変更しない。

## 読み取り境界

開催情報管理側に、将来の統合用として読み取り専用の境界を置く。

```text
plugins/garden-opening-status/includes/integration/
├─ bootstrap.php
├─ class-gos-legacy-slider-source.php
├─ class-gos-slider-integration-state.php
└─ class-gos-slider-integration-guard.php
```

### `GOS_Legacy_Slider_Source`

既存保存先を直接読み、現在の値をそのまま取得する。

- `dp_options`
- `mlm_options`

書き込みは一切行わない。

### `GOS_Slider_Integration_State`

将来の管理画面や移行処理が保存先の違いを直接意識しなくて済むよう、読み取り結果を次の形へ正規化する。

```text
slide
├─ pc
│  ├─ image_id
│  ├─ source = theme_dp_options
│  └─ editable = false
├─ mobile
│  ├─ image_id
│  ├─ position_x
│  ├─ position_y
│  ├─ source = mlm_options
│  └─ editable = false
├─ render_index
└─ renderable
```

現段階では `writes_enabled = false`、`legacy_required = true` とし、統合先からの保存や旧プラグイン停止を許可しない。

### `GOS_Slider_Integration_Guard`

Phase 1以降で誤って書き込み・表示切替へ進まないよう、現在の統合許可状態を明示する。

現段階の固定値:

- `writes_allowed = false`
- `frontend_takeover_allowed = false`
- `migration_allowed = false`
- `is_read_only() = true`

併せて、`dp_options` / `mlm_options` が配列として存在するか、旧モバイル表示管理クラスがロード済みかだけを読み取り確認できる。

### `bootstrap.php`

上記3クラスだけをまとめて読み込む入口。
`bootstrap.php` 自体にはWordPress hook登録・option更新・管理メニュー追加を置かない。

このbootstrapはまだ開催情報管理本体からrequireしていないため、準備ブランチを配置しただけでは公開画面・管理画面・保存データへ影響しない。

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

## 段階移行

### Phase 0: 現在

- 既存2プラグインは独立稼働
- integration用bootstrapと読み取りクラスを準備済み
- 開催情報管理本体からは未ロード
- 既存保存先を変更しない
- 公開フックを変更しない
- Guardで書き込み・frontend takeover・migrationを明示的に禁止

### Phase 1: 読み取り接続

開催情報管理からintegration `bootstrap.php` をロードするが、表示・保存にはまだ利用しない。

予定する本体変更は1か所だけ:

```php
require_once __DIR__ . '/includes/integration/bootstrap.php';
```

このrequire以外のフック追加・画面追加・保存処理変更は同じ変更に含めない。

完了条件:

- integration 3クラスがロードされる
- クラスロードだけで公開HTMLが変化しない
- option更新が発生しない
- WordPress管理メニューが増えない
- 旧モバイル表示管理の動作が完全に維持される
- Guardが `writes_allowed = false` のまま

### Phase 2: 読み取り専用UI

開催情報管理にトップスライダーの現在値を表示する。
まだ編集はできない。

完了条件:

- テーマオプションのPC画像と一致
- モバイル表示管理のスマホ画像・位置と一致
- 未設定slotの扱いが現行表示と一致
- UIからPOST/option更新経路を持たない

### Phase 3: PC画像編集

開催情報管理から `dp_options` の対象キーのみ更新できるようにする。
スマホ側はまだ旧プラグインを正本とする。

### Phase 4: スマホ設定移行

`mlm_options` のトップスライダー部分だけを開催情報管理側へ移行する。
投稿・固定ページのモバイル補正はこの段階でも移行しない。

### Phase 5: 旧トップスライダー機能停止

開催情報管理側の表示が安定した後、モバイル表示管理からトップスライダー処理だけを停止する。
モバイル表示管理そのものは、ページ補正が残る限り継続利用する。

## 二重適用防止

統合途中で、開催情報管理とモバイル表示管理の両方がスマホ画像を書き換える状態を作らない。

切替は次の順序を守る。

1. 新側が旧データを正しく読める
2. 新側の表示処理を実装する
3. 新側をまだ無効状態で検証する
4. 旧側のトップスライダー処理を停止
5. 新側を有効化

同一リリース内で4と5を切り替える場合も、明示的な所有権フラグを1つだけ持つ。

## 移行対象と非対象

最初の統合で移すもの:

- トップスライダーのスマホ画像
- スマホ画像のX/Y位置
- トップスライダーのスマホ有効状態
- 必要ならトップスライダー用breakpoint

最初の統合では移さないもの:

- 全体の本文文字倍率
- 見出し倍率
- 左右余白
- ボタン高さ
- 投稿・固定ページ単位の上書き設定
- `_mlm_*` 投稿メタ
- スマホ実画面プレビュー全体

これらを同時に移すと統合範囲が広がりすぎるため、トップスライダー移行とは分離する。

## 現段階で行わないこと

- `mlm_options` の移行
- 投稿メタの移行
- 管理画面メニューの統合
- フロントフックの移動
- スライダー描画方式の変更
- テーマの `dp_options` 書き換え実装
- 旧プラグイン停止処理
- 開催情報管理本体の大規模分割
- integration bootstrapからのhook登録
- integrationクラスからのoption更新

## 次段階

Phase 0の準備物は揃った。
次はPhase 1として、開催情報管理本体にintegration bootstrapの `require_once` だけを追加する。
その変更ではバージョンアップ、UI、保存処理、frontend処理を同時に変更しない。

この準備段階では、既存公開表示と保存データを変更しない。
