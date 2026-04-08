# 🍊mikanBox

**AI時代のパーツ組み立て型・超軽量CMS**

🍊mikanBoxは、数ページから数十ページ規模のWebサイトを、最速かつ安全に構築・運用するために設計されたファイルベースCMSです。データベース不要。PHPが使えるサーバーに置くだけで動きます。

[English README →](#english)

---

## 特長

- **ファイルベース（JSON）** — データベース不要。PHPの動くサーバーに置くだけ
- **モードレスUI** — ページ遷移なし、1画面で全作業完結。軽快な操作感
- **Markdown対応** — コンテンツを簡単に編集・再利用
- **コンポーネント構造** — 再利用・並び替えが可能なパーツ管理
- **コンポーネント単位のスコープCSS** — 干渉を気にせず小さい範囲でCSSを書ける
- **AI生成コードをそのまま配置可能** — 手作業でのデザイン不要
- **AIエージェント連携（MCP）** — AIがサイト構造を理解し、直接ファイルを読み書き
- **マルチモーダルAI対応** — AIが画像を生成し、そのままメディアフォルダへ送信・配置
- **静的（SSG）・動的・混在** — レスポンスの速い静的サイトにもできる
- **DB Less DB** — ページにデータを埋め込んでAPIで出力。ヘッドレスCMSにも
- **ポッドキャスト** — RSSを自動生成してポッドキャスト配信も可能

---

## デモ

[https://yoshihiko.com/mikanbox/demo/](https://yoshihiko.com/mikanbox/demo/)

---

## 動作要件

- PHPが使えるWebサーバー（データベース不要）

---

## インストール

1. `mikanBox` フォルダと `index.php` をサーバーにアップロード
2. セキュリティのため `mikanBox` フォルダ名を任意の名前に変更推奨（変更した場合は `index.php` 上部の `$core_dir` も同じ名前に変更）
3. `mikanBox/admin.php` にアクセスして管理者パスワードを設定

以上で完了です。

---

## 基本的な使い方

### 2つの運用スタイル

**継続的なコンテンツ**（ブログ、サービスページなど）

- Markdownで書いて、画像はファイル名だけで参照
- 継続更新・コンテンツの再利用に向いた構造

**短期ページ**（ランディングページ、イベントページなど）

- AIが生成したHTML/CSS/JSをそのままペーストして公開
- 手作業でのデザイン作業が不要

### デザイン（コンポーネント）

- **ページ・コンポーネント** — ページ全体のレイアウトを定義するラッパー
- **パーツ・コンポーネント** — ページや他のコンポーネントに埋め込む再利用パーツ

コンポーネントはHTMLとスコープ付きCSSを持ち、入れ子にもできます。

### 静的サイト生成（SSG）

ワンクリックで全ページを静的HTMLとして書き出せます。静的・動的ページを混在させることも可能です。

### ページの公開ステータス

| ステータス | 動作 |
| :------- | :------- |
| 下書き| 非公開（管理者のみ閲可）|
| 公開（動的） | PHPで動的に配信 |
| 公開（静的） | 静的HTMLとして書き出し |
| DB | ページ自体は非公開。データをAPIとして公開 |

---

## AIとの連携

🍊mikanBoxはAIツールとの相性を重視して設計されています。

- AIが生成したHTMLページをそのままコンテンツ欄にペーストして公開できる
- コードベースがコンパクトで構造が単純なため、AIが仕様を理解しやすい
- MCP対応により、AIエージェントがサイトの構造を理解し、直接コンテンツやパーツを編集・更新可能
- AIからのマルチモーダル入力（画像など）を直接受け取り、メディアフォルダへのアップロードとページへの配置を自動化
- 仕様が単純なので、AIに説明不要で専用のデザインや機能・プラグイン相当のパーツを作らせられる

### MCPへの対応

Model Context Protocol (MCP) に対応しており、AIエージェントがサーバー上のファイルを安全に操作するためのブリッジを提供します。これにより、ユーザーが管理画面を操作することなく、AIとの対話だけでページの新設やデザインの修正、コンポーネントの作成が完結します。

---

## 情報の埋め込みとAPI

### 別のページや外部のMarkdownの読み込み

別のページを読み込んだり、GitHubなど外部サイトのMarkdownファイルを読み込めます。複雑なページのなかにニュースなどの更新部分を埋め込むのに便利です。

### ページにデータを埋め込む

```
{{DATA:price:GHOST}}4800{{/DATA}}
```

同じページから参照：`{{POST_MD::price}}`  
別ページから参照：`{{POST_MD:pageID:price}}`

### テーブル形式のデータ（DB Less DB）

```
{{DATAROW:row1}}
  {{DATA:name}}商品A{{/DATA}}
  {{DATA:price}}4800{{/DATA}}
{{/DATAROW}}
```

参照：`{{POST_MD:pageID#row1:name}}`

### APIとして公開

ページのステータスを **DB** にすると、データをJSON APIとして外部公開できます。

```
https://yoursite.com/api/pageID
```

### CSVインポート

ExcelなどのCSVファイルを `{{DATAROW}}` 形式に一括変換する機能をサイトメニューに内蔵しています。

---

## ポッドキャスト配信

ページのカテゴリを `podcast` にして音声ファイルを埋め込むと、以下のURLにRSSフィードが自動生成されます。

```
https://yoursite.com/podcast.xml
```

あとはApple Podcasts、Spotify、Amazon Musicなどにそのフィードを登録するだけです。

---

## サイトマップ・RSS

| URL | 内容 |
| :---| :---|
| `/sitemap.xml` | XMLサイトマップ |
| `/rss.xml`     | RSSフィード|
| `/podcast.xml` | ポッドキャスト用RSS（podcastカテゴリのみ） |

---

## セキュリティ

- データベース不使用のためSQLインジェクションの攻撃対象がない
- 小さなコードベース・プラグイン依存なし
- ローカルで運用して静的ファイルをアップロードするとPHPファイルやJSONをパブリックディレクトリに置かずに済み、改ざんリスクを最小化
- 管理画面のディレクトリ名を変更することでURLを推測されにくくできる
- `.htaccess` による管理ファイルへの直接アクセス制限

---

## 想定する用途・規模

**適している用途：** 個人サイト、小規模店舗・企業サイト、イベントページ、ポートフォリオ（目安：50ページ以内）

---

## ドキュメント

- [日本語ヘルプ](https://yoshihiko.com/mikanbox/help_ja.html)
- [English Help](https://yoshihiko.com/mikanbox/help_en.html)

---

## ライセンス

MIT License — Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)

---

<a name="english"></a>

# 🍊 mikanBox — English Summary

**AI-era, parts-assembly, ultra-lightweight CMS**

A file-based CMS for building small-to-medium websites (up to ~50 pages) with no database required. Upload to any PHP-enabled server and you're ready to go.

**Key features:** Modeless single-screen UI · Markdown + HTML/CSS/JS · Scoped CSS per component · AI-generated code works as-is · Static site generation (SSG) · DB Less DB (embedded data + API) · Podcast RSS

[Full documentation in English →](README_en.md)
