<nav class="sidebar">

## 目次

- <a href="#concept">コンセプト・特長</a>
- <a href="#design-philosophy">設計方針</a>
- <a href="#security-policy">セキュリティ方針</a>
- <a href="#setup-dist">初期設定・配布について</a>
- <a href="#overview">全体像</a>
- <a href="#common">共通</a>
- <a href="#page-mgmt">ページ</a>
- <a href="#page-edit">ページ編集</a>
- <a href="#visual-edit">ビジュアル編集</a>
- <a href="#site">サイト</a>
- <a href="#design-mgmt">デザイン</a>
- <a href="#design-edit">デザイン編集</a>
- <a href="#media-mgmt">メディア</a>
- <a href="#markdown">Markdown</a>
- <a href="#components">コンポーネント（独自タグ）</a>
- <a href="#tags">利用可能なタグ一覧</a>
- <a href="#init-comps">初期コンポーネント一覧</a>
- <a href="#data-embedding">データの埋め込み、読み込み、API</a>
- <a href="#output-formats">出力形式（Markdown版・データ）</a>
- <a href="#catalog-pager">目録データでページ一覧を作る</a>
- <a href="#podcast">ポッドキャスト配信</a>
- <a href="#site-mapfeed">サイトマップ・RSS・API</a>
- <a href="#ai-guide">AIで作ったデザインを公開する</a>
- <a href="#announcement">お知らせ</a>
- <a href="#license">ライセンス</a>

</nav>
<main>

<div class="manual-editions" role="group" aria-label="表示する版"><strong>表示する版</strong> <button type="button" data-edition-choice="flat">flat</button> <button type="button" data-edition-choice="sqlite">sqlite</button> <button type="button" data-edition-choice="cloud">cloud</button> <button type="button" data-edition-choice="all">all</button> <label><input type="checkbox" id="manual-details"> 詳細も表示</label></div>

<style>
.manual-version {
  font-size: 0.5em;
  font-weight: 400;
  white-space: nowrap;
}
.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75em;
  font-weight: 600;
  white-space: nowrap;
  line-height: 1.6;
}
.badge-sqlite { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.badge-flat { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe; }
.badge-page { background:#c9e8d7; color:#2d6a4f; border:1px solid #a8d4be; }
.badge-part { background:#dde3ea; color:#1e293b; border:1px solid #c4cdd6; }
.badge-ai { background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8; }
body[data-edition="flat"] nav.sidebar a.cloud,
body[data-edition="sqlite"] nav.sidebar a.cloud {
  display: none;
}
.mat-icon {
  vertical-align: -0.2em;
  font-size: 1em;
}
.manual-editions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:12px 0; }
.manual-editions button, .manual-more { font:inherit; cursor:pointer; border:1px solid #9ca3af; background:#fff; border-radius:5px; padding:4px 10px; }
.manual-editions button[aria-pressed="true"] { background:#1f2937; color:#fff; }
.manual-hidden { display:none !important; }
.manual-details-ready:not(.manual-show-details) .detail:not(.manual-open) { display:none !important; }
.manual-more { display:block; margin:12px 0; }
.manual-show-details .manual-more { display:none !important; }
.manual-editions label { margin-left:8px; }
.manual-edition-badge { font-size:.7em; margin-left:.5em; padding:1px 6px; border:1px solid #9ca3af; border-radius:4px; color:#374151; }
</style>

# 🍊mikanBox 操作マニュアル <small class="manual-version">flat・SQLite v2.30.5 / Cloud v0.21.3</small>

🍊mikanBoxには、SQLiteデータベースを使う**🍊mikanBox**（SQLite版）、データベースを使わないJSONファイルベースの**🍊mikanBox flat**（flat版）、Cloudflare上で管理も公開も行う**🍊mikanBox Cloud**（Cloud版）があります。基本的な使い方は共通なので、このヘルプは3つの版に対応しています。ページ上部のボタン（flat／sqlite／cloud／all）で、読みたい版に表示を切り替えられます。版によって内容が異なる箇所には、バッジで区別を示します。

## コンセプト・特長 {#concept}

🍊mikanBoxは、「AI時代のパーツ組み立て型・超軽量CMS」です。数ページから数十ページ規模のWebサイトを、最速かつ安全に構築・運用するために設計され、ローカルで静的サイトを構築するという使い方でも使いやすい🍊mikanBox flat（JSON版）と、より規模の大きいサイトを快適に運用できる🍊mikanBox（SQLite版）、Cloudflareで運用する🍊mikanBox Cloud（Cloud版）があります。

- ファイルベース（JSON） — データベース不要。PHPの動くサーバーに置くだけ {.flat}
- SQLite（単一ファイルDB） — データは1つのSQLite DBファイルにまとまり、PHPの動くサーバーに置くだけ {.sqlite}
- Cloudflareで運用 — サイトの編集から静的サイトの公開までCloudflare上で行えます。画像の保存にR2を使用しないので、Cloudflareのユーザー登録だけで、無料枠からはじめられる {.cloud}
- モードレスUI — ページ遷移なし、1画面で全作業完結。軽快な操作感
- Markdown対応 —  コンテンツを簡単に編集可能、情報のアーカイブとしても適している
- カテゴリで絞り込み — ページや画像をカテゴリで絞り込み、ワークスペースとして利用できる
- ファイル名だけで画像表示 — ファイルのパスを意識せずに画像を張り込める
- コンポーネント構造 — 組み合わせて利用できるページテンプレートやパーツ
- コンポーネント単位のCSS管理 — 小さい範囲でCSSが書けて、干渉を気にしなくてよい
- AI生成コードを直接配置可能 — 手作業でのデザインなしでの運用にも対応
- AIエージェント連携（MCP） — AIがサイト構造やデザイン基調などを理解し、直接ファイルを読み書き
- DESIGN.mdの管理 — AIへの指示もサイトで管理し、AIに伝えられる
- マルチモーダルAI対応 — AIが画像を生成し、そのままメディアフォルダへ送信・配置
- 静的（SSG）も、動的も、それらの混在も — 静的・動的の混在が可能です。 {.flat .sqlite}
- 公開サイトを管理サイトを分けた安全性の高い運用 {.cloud}
- サイト改ざんのチェック機能付き、操作の履歴も見やすく管理
- DB Less DB — ページにデータを埋め込んでAPIで出力。ヘッドレスCMSにも
- ポッドキャスト — RSSを自動生成してポッドキャスト配信も可能

---

## 設計方針 {#design-philosophy}

### 🍊mikanBox flat：小さなサイト向け {.flat}
🍊mikanBox flatは数千ページ規模のサイトは想定していません。数ページのサイトだけど情報の更新はしたい、そんなサイトにDBを使った重いシステムを導入する必要はないのでは。あえてデータベースを使わないことで、以下の手軽さを実現しています。

- 設置が簡単
- 構造がシンプルで覚えやすい
- バックアップ・サーバー移設が簡単

また、🍊mikanBox flatは、手元のパソコンでも運用しやすいCMSです。サイトのフォルダをAIの作業フォルダとして開くと、AIがJSONファイルを直接読み書きでき、サイトの制作からレンタルサーバーやCloudflareへのアップロードまで任せられます。公開先には書き出した静的ファイルだけを置けるため、管理画面や編集中のデータを手元に置いたままサイトを運用できます。








### 🍊mikanBox：ページ数の多いサイト向け {.sqlite}
🍊mikanBox（SQLite版）は、もう少し大きなサイトも運用したいご要望にお応えします。しかも、単一ファイルのSQLiteなので、手軽さはほとんどそのままです。

- 設置が簡単
- バックアップ・サーバー移設が簡単
- ページ数の多いサイトにも対応
- 複数ユーザーによる編集や承認システムに対応
- 編集履歴の管理に対応

### 🍊mikanBox Cloud：Cloudflareで運用 {.cloud}

🍊mikanBox Cloudは、Cloudflare上でサイトを運用したい人向けです。記事や設定はD1に保存し、登録したブラウザから編集します。管理用と公開用のWorkerを分け、公開側にはビルドした静的ファイルを配信します。

- 高速で安全性の高いCloudflareで運用できる
- 静的サイトをネット上で、複数の編集者で運用できる
- 公開後の閲覧で管理用WorkerやD1を使わない
- R2を使用しないので、支払いの登録なしに、無料枠からはじめられる

### 柔軟な設計

ページやコンポーネントがCSSと結びついているのでわかりやすく、また、干渉を気にしなくてよい設計になっています。コンポーネントの使い方次第で、自由で柔軟なサイト設計が行えます。

- ページやコンポーネントがCSSとJavaScriptを持てる
- CSSをスコープ化して、干渉を防止
- コンポーネントの入れ子が可能
- WordPressのテーマにあたるものが手軽に作れ、AIでも作れる
- 画像などのメディアファイルのパスも自動で解決
- ページにデータを簡単に埋め込める
- 他ページ、他サイトのデータを読み込んで利用できる
- データの持ち方が多様で、工夫次第で様々使い方ができる

### AIフレンドリー

- MCPを通じて、記事の作成からデザインまで、AIにリモートコントロールさせられる
- DESIGN.mdなど、AIへの指示を管理して、AIに読ませたり、逆にデザインからデザインシステムを生成させることが可能
- コードがコンパクトでシンプル。AIが理解しやすいので、専用のデータ構造にあわせて、ページ制作、デザインしてもらうことも簡単
- AIで生成したページをそのまま貼り込んで使いやすい。凝ったデザインも、少ない作業でそのまま公開
- GitHubなど外部のMarkdownファイルをソースにできる `{{EXT_MD:url}}` タグを活用することで、管理画面を開かなくても「AIエージェントに指示してレポジトリを更新してもらう」だけでWebサイトが更新される運用スタイルも可能 {.flat .sqlite}
- コードが小さく、AIが理解しやすいので、機能追加はプラグインではなく、AIでソースを書き換えることも可能

---

## セキュリティ方針 {#security-policy}

### 守る情報と公開側の入口を減らす

🍊mikanBoxは、情報を発信する小規模なサイトを想定しています。訪問者向けの会員登録や決済機能を持たないため、運用でも個人情報を集めなければ、サイトが預かる情報を少なくできます。機能を追加するプラグインやテーマを導入する仕組みは持たず、外部ライブラリへの依存も抑えています。

### 管理画面へのアクセスと認証

管理画面では、別のサイトからの書き込みを防ぐCSRF対策を備えています。公開環境で管理画面をHTTPで開くとHTTPSに転送し、通信経路を保護します。パスワードや端末登録コードなどを繰り返し間違えると、一定時間入力を制限します。

- **flat版のパスワード認証** サーバーへの設置時は、初回設定トークンを持つ人が管理用パスワードを設定します。パスワードはハッシュ値で保存します。[初回設定とログイン](#first-setup)を参照してください。 {.flat}
- **SQLite版・Cloud版の端末認証** パスワードではなく、端末を認証します。鍵はブラウザに保存し、サーバーにはハッシュ値だけを残します。別の端末を追加するときは、一度だけ使えるコードを発行します。端末を紛失したときは、別の登録済み端末から停止できます。[端末とユーザー](#site-users)を参照してください。 {.sqlite .cloud}

### 編集する場所と公開する場所を分ける

flat版・SQLite版は、手元のPCで編集とプレビューができます。手元で静的サイトを書き出し、レンタルサーバーやCloudflareにファイルを送ることができます。ローカルのフォルダをAIの作業用フォルダとして開けば、画像を含んだコンテンツの制作、ページ制作から、公開先へのファイル転送まで、AIに任せることができます。公開サーバーには書き出したファイルだけを置くので、管理画面や編集中のデータを公開せず、閲覧時にもPHPを実行しません。[静的サイト生成](#site-ssg)を参照してください。 {.flat .sqlite}

Cloud版では、管理画面・MCP・D1へのアクセスを管理用Workerに置き、公開用Workerは別のアドレスで静的ファイルを配信します。 {.cloud}

#### 同じサーバーで管理側に認証をかける例 {#php-separated-publishing .flat .sqlite}

管理画面が `example.com/blog/mikanBox/admin.php` にあり、同じ `/blog/` で公開するなら、Webサーバーの認証を `/blog/mikanBox/` に設定し、公開ページと兄弟フォルダの `/blog/media/` は認証なしで配信できます。

公開側を `example.com/pub/` に分ける場合は、`/blog/` を管理・プレビュー専用にしてホストまたはパス全体を認証で保護し、`/pub/` は静的ファイルだけを配信します。管理用MCPも管理側に置きます。

| 用途 | 公開アドレス | ファイルの配置例 |
| --- | --- | --- |
| 管理・プレビュー | `https://example.com/blog/` | `/srv/www/blog/` に `index.php` と `mikanBox/` |
| 公開ページ | `https://example.com/pub/` | `/srv/www/pub/` に静的ビルドの出力 |
| メディア | `https://example.com/pub/media/` | `/srv/www/pub/media/` にアップロードしたファイル |

「サーバー内で静的ページを生成」で出力先を `../pub`、公開先ルートURLを `https://example.com/pub` にし、[メディアも出力先の media/ に置く](#ssg-media-in-output)を選びます。`pub.example.com` から同じ公開フォルダを配信するなら、公開先ルートURLを `https://pub.example.com` にします。

公開側ではPHPやCGIの実行とディレクトリ一覧を無効にします。管理側のPHPに公開先とメディアへの書き込み権限を与え、公開側には配信に必要な読み取り権限を与えます。管理プログラム・データ・セットアップトークンは公開側の配信範囲から外します。SVGにはメディア用の `.htaccess` を適用するか、同等の `Content-Security-Policy: sandbox` を付けます。ログアウトしたブラウザで公開ページと画像を開けること、管理側で認証が求められることを確認してください。公開側に置いたメディアはアップロード時点で配信されます。

### データ、アップロード、AI連携の保護

- **データフォルダ。** PHP版は、Apacheのアクセス拒否設定と🍊mikanBoxの入口処理でデータフォルダを保護します。管理画面に警告が出たときは、Webからフォルダを読めない設定に直します。flat版はJSONファイルを使い、SQLite版はSQL文と入力値をプリペアドステートメントで分けています。 {.flat .sqlite}
- **アップロード。** SVGはアップロード時にスクリプトなどを取り除きます。JS・CSSも形式や内容の条件を検査します。公開ページで動くコードは、提供元と内容を確かめてから使ってください。[メディア](#media-mgmt)を参照してください。
- **管理用MCP。** APIキーは発行時に一度だけ表示し、サーバーにはハッシュ値だけを保存します。AIツールごとにキーを分けると、操作記録で利用元を確認でき、不要になったキーだけを無効にできます。接続を使わない間は、管理用MCP全体も停止できます。[MCP APIキー](#site-mcp-key)を参照してください。

### 更新元を確かめ、変更をたどる

PHP版のシステム更新とCloud版のGitHub更新は、公開リリースのタグが指すコミットを確定してから取得します。Cloud版の管理画面から更新するときは、公式リリースのタグとコミットを照合し、配布ファイルのSHA-256を確認してから適用します。🍊mikanBoxの開発リポジトリでは、設定済みのGitフックがプッシュ前に秘密情報の混入を検査します。

[最近の操作記録](#site-audit)では、管理画面・MCPなどを通じたページ、コンポーネント、メディア、設定の変更を確認できます。[改ざんをチェック](#site-integrity)を実行すると、公開ファイルや保存データを記録した状態と照合し、記録された操作だけでは説明できない差分を調べられます。PHP版ではプログラムファイルも対象です。

---

## 初期設定・配布について {#setup-dist}

ここでは、🍊mikanBoxを設置して、管理画面に入れるようになるまでを説明します。設置したあとの更新（アップデート）は、管理画面の「サイト」の「バージョン」から行います（[更新のしかた](#setup-update)）。

### 動作環境 {#requirements .flat .sqlite}

- PHP 8.0以上（新しく設置するなら、サポート中のPHPの最新版）。SQLite版は、SQLite3拡張も必要です（多くのレンタルサーバーで標準で使えます）。
- `.htaccess` が有効なApache。データフォルダをブラウザから開けなくするために使います。nginxなどで管理画面を動かすことは想定していません（書き出した静的ファイルの配信先には使えます）。
- HTTPS。手元のパソコンで `localhost` として動かすときは不要です。

### PHP版をサーバーに設置する {#php-install .flat .sqlite}

#### 1. 配布物を入手する

GitHubのリリースページ（flat版は [mikanBox-flat のリリース](https://github.com/yoshihik0/mikanBox-flat/releases)、SQLite版は [mikanBox のリリース](https://github.com/yoshihik0/mikanBox/releases)）から、最新版の **Source code (zip)** をダウンロードして展開します。

展開したフォルダには、次のものが入っています。

| 名前 | 中身 | サーバーに置くか |
|---|---|---|
| `index.php` | 公開ページを表示する入口 | 置く |
| `mikanBox/` | 管理画面とプログラム本体 | 置く |
| `setup-token/` | 初期設定トークンを書くファイル | 置く（最初の設定のため） |
| `README.md` など | 説明とライセンス | 置かなくてよい |

記事や設定を入れる `mikanData/` と、画像などを入れる `media/` は、配布物には入っていません。最初にアクセスしたときに自動で作られます。

#### 2. 初期設定トークンを書く

初期設定トークンは、サーバーに置いた直後に、ほかの人が先に管理者になってしまうのを防ぐための合言葉です。最初の設定で一度だけ使います。

1. 20文字以上のランダムな文字列を作ります。同じ文字ばかりの文字列は使えません。パスワード生成機能などで作ると確実です。
2. `setup-token/setup-token.txt` を開き、説明の行（`#` で始まる行）の下に、その文字列を1行で書きます。BOMなしのUTF-8で保存します。
3. 文字列を手元に控えます。管理画面で入力します。

#### 3. サーバーへアップロードする

`index.php`、`mikanBox` フォルダ、`setup-token` フォルダを、FTPなどでサーバーの同じ場所へアップロードします。サイトのトップにするならドキュメントルートへ、`https://example.com/blog/` で公開するなら `blog` フォルダへ置きます。

- 管理フォルダの名前は、推測されにくい名前に変えてもかまいません。変えたときは、`index.php` の上の方にある `$core_dir` も同じ名前に書き換えます。
- 書き込み権限: `index.php` と同じ場所に `mikanData` と `media` を作るので、PHPがその場所に書き込める必要があります。

#### 4. 管理画面を開いて最初の設定をする

ブラウザで `https://example.com/mikanBox/admin.php`（管理フォルダ名を変えたときはその名前）を開きます。最初のアクセスで `mikanData` が作られ、サンプルのページとコンポーネントが入ります。そのあと、初回設定の画面が出ます。

- **flat版** — 初期設定トークンと、管理画面のパスワード（7文字以上）を入力します（[最初のパスワードを設定する](#flat-setup)）。 {.flat}
- **SQLite版** — 初期設定トークン、ユーザー名、表示名、端末の名前を入力して、この端末を登録します（[最初の端末を登録する](#device-setup)）。 {.sqlite}

設定が終わると、`setup-token.txt` は説明の行だけに戻ります。トークンはもう使いません。サイト直下に `.htaccess` がなければ、このとき作られます。

#### 5. データフォルダが守られているか確かめる

管理画面は、開くたびにデータフォルダの中のファイルがブラウザから読めないかを確かめます。読めてしまうときは、画面の上に「データフォルダの中のファイルが、ブラウザから直接読める状態です」という警告が出ます。この状態では、設定や下書き、MCP APIキーなどが外から読まれるおそれがあるので、公開する前に直してください。

- サーバーがApacheで、`.htaccess` が有効になっているかを確かめます（レンタルサーバーの管理画面や、サーバーの管理者に確認します）。
- `.htaccess` が使えないサーバーでは、サーバーの設定でデータフォルダ（`mikanData`）へのアクセスを拒否するか、`.htaccess` が有効なサーバーへ移します。
- 直したら管理画面を開き直し、警告が消えたことを確かめます。

#### 6. 設置したら最初にすること

1. 「サイト」画面の[サイト共通設定](#site-settings)で、サイト名を入れて保存します。管理画面の表示言語は[言語](#site-lang)で変えられます。
2. 「ページ」画面でサンプルのページを確かめます。トップページと大半の見本は「公開(動的)」で、設置直後から誰でも見られます。トップページ（ID `index`）を書き換え、使わない見本は削除するか下書きにします。埋め込み用の `sample_md` は初めから下書きです。
3. 公開するページの公開設定を確かめます。「公開(静的)」のページは、「静的サイトをビルド」を押すまでファイルになりません（[静的サイト生成](#site-ssg)）。
4. AIからサイトを操作するときは、[MCP APIキー](#site-mcp-key)を発行します。
5. ほかの人と編集するときは、[端末とユーザー](#site-users)で招待のコードを出します。 {.sqlite}

#### 手元のパソコンで動かす {#php-local .flat .sqlite}

サーバーに置かずに、手元のパソコンで動かすこともできます。必要なのはPHPだけです。手元で作って、書き出した静的ファイルだけを公開サーバーへ送る使い方もできます。

1. 配布物を展開します。`setup-token` は使いません。
2. ターミナルで、`index.php` があるフォルダへ移動して、次を実行します。

```sh
php -v                            # 8.0以上であることを確かめる
php -S localhost:8000 index.php
```

3. ブラウザで `http://localhost:8000/mikanBox/admin.php` を開きます。同じパソコンから開いているときは、初期設定トークンは要りません。flat版はパスワードを設定し、SQLite版はユーザー名と表示名を入れて登録します。
4. 終わるときは、ターミナルで Ctrl+C を押します。

コマンドの末尾の `index.php` は省かないでください。省くとデータフォルダがブラウザから読める状態になり、管理画面に警告が出ます。この簡易サーバーは自分のパソコン専用です。ほかの人に見せるときは、[静的サイトを書き出して](#site-ssg)公開します。

フォルダをAIのコーディングツールで開くと、AIがページやコンポーネントのデータを直接読み書きできます。 {.flat}

### 初回設定とログイン {#first-setup .flat .sqlite}

#### 最初のパスワードを設定する {#flat-setup .flat}

管理画面で初期設定トークンと、7文字以上のパスワードを入力します（手元のパソコンではパスワードだけ）。次回からは、設定したパスワードでログインします。パスワードの変更は、サイト画面の[パスワードの変更](#site-password)で行います。

#### 最初の端末を登録する {#device-setup .sqlite}

SQLite版にパスワードはありません。登録したブラウザが鍵になります。**普段使うブラウザ**で管理画面を開き、初期設定トークン、ユーザー名（半角英数字・`_`・`-`）、表示名、端末の名前を入力して「この端末を登録」を押します。以後、そのブラウザで管理画面を開けます。ほかのブラウザやパソコンを使うときは、[端末を追加](#add-device)します。

#### 2.8.xから更新する {#upgrade-to-devices .sqlite .detail}

更新すると、旧パスワードと秘密の質問の回答は削除されます。ユーザー名、表示名、ページ、デザイン、MCP APIキーは引き継ぎます。

1. 更新前に、配布物の `setup-token/setup-token.txt` に初期設定トークンを用意します。
2. 更新後に管理画面を開き、トークン、既存のユーザー名、端末の名前で最初の端末を登録します。
3. ほかの利用者には、登録済みの端末から[追加用のコード](#add-device)を発行します。

承認待ちページの共有URLを使っていた場合は、更新後のURLを渡し直します。

#### すべての端末を失ったとき {#device-recovery .sqlite}

1. 新しい初期設定トークンを `setup-token/setup-token.txt` に書いてアップロードします。前に使った値は使えません。
2. ログイン画面の「復旧（すべての端末を失った場合）」を開き、新しいトークン、既存のユーザー名、端末の名前を入力します。

復旧すると、それまでの端末、未使用のコード、プレビュー共有URL、MCP APIキーは使えなくなります。必要なものを発行し直してください。

### Cloud版を設置して公開する {#cloud-install .cloud}

Cloud版は、Cloudflareの上に、管理画面（管理用Worker）、公開サイト（公開用Worker）、記事や設定を入れるデータベース（D1）を作ります。必要なものは次のとおりです。

- Cloudflareアカウント（無料プランで始められます）
- 作業するパソコンに、Node.js（22以上）とnpm、ターミナル
- 管理画面と公開サイトのアドレスの案。`workers.dev` のアドレスで始めて、あとから独自ドメインに変えることもできます。

設置の方法は2つです。既存サイトの更新には、[バージョン（Cloud版）](#site-version-cloud)の手順を使います。

#### AIに任せる

1. Claude CodeなどのAIのコーディングツールを、空の作業フォルダで開きます。
2. 「`https://github.com/yoshihik0/mikanBox-cloud` からCloudflareにインストールしてください」と頼みます。
3. AIの案内に従って、Cloudflareにログインし、認可の画面で **Authorize** を押します。独自ドメインを使うかどうか、管理画面と公開サイトの名前を聞かれたら答えます。
4. 設置が終わると、AIが管理画面のアドレスと、初期設定トークン（`SETUP_TOKEN`）が入ったファイルの場所を伝えます。**普段使うブラウザ**で管理画面を開き、トークンを入力して[最初の端末を登録](#cloud-auth)します。トークンはチャットに貼らないでください。AIの内蔵ブラウザで登録すると、そのブラウザが端末になってしまいます。
5. 管理画面の「サイト」の[Cloudflare公開設定](#cloudflare-publish-settings)に、APIトークンを保存します（取得方法は欄の中に表示されます）。
6. 「ページ」画面で「静的サイトをビルド」を押し、完了まで画面を開いておきます。公開サイトを開き、公開ページと画像が表示され、下書きや承認待ちのページが開けないことを確かめます。

#### コマンドで自分で設置する {#cloud-install-commands .cloud .detail}

コマンドで設置するには、CloudflareとGitHubのアカウント、インターネット接続、Node.js 22以上、npm、ターミナルを用意します。独自ドメインを使う場合は、先にCloudflareでドメインを接続できるようにします。

1. [Cloud版のリリース](https://github.com/yoshihik0/mikanBox-cloud/releases)から、使う版の **Source code (zip)** をダウンロードして展開し、ターミナルでそのフォルダへ移動します。
2. `npm ci` で必要なツールを入れます。
3. `npx wrangler login` でCloudflareにログインします。認可画面で設置先のアカウントを確かめて **Authorize** を押します。ターミナルに戻り、`npx wrangler whoami` で設置先を確認します。
4. `npx wrangler d1 create mikanbox-cloud` でD1データベースを作り、表示されたIDを控えます。
5. `wrangler.production.example.json` を `wrangler.production.json`（管理用）に、`wrangler.public.example.json` を `wrangler.public.json`（公開用）にコピーし、[設定ファイルの値](#cloud-install-config)を書き換えます。
6. 公開先を確かめ、`npx wrangler deploy --config wrangler.public.json` で仮のページを置きます。**新規設置時の1回だけ実行します。** 既存サイトに実行すると、公開サイトが仮のページに置き換わります。
7. [秘密ファイルを作るコマンド](#cloud-install-secrets)で、設置先専用の `JWT_SECRET` と `SETUP_TOKEN` を `.env.production.secrets` に保存します。
8. `npx wrangler d1 migrations apply DB --remote --config wrangler.production.json` でD1を準備します。
9. `npm run deploy:cloud` で管理画面を配備します。
10. 普段使うブラウザで管理用の `/admin` を開き、`.env.production.secrets` の `SETUP_TOKEN` を入力して[最初の端末を登録](#cloud-auth)します。
11. 対象アカウントに限定した Workers Scripts Edit 権限のAPIトークンを[Cloudflare公開設定](#cloudflare-publish-settings)に保存します。
12. 「静的サイトをビルド」を押し、完了まで画面を開いておきます。公開ページと画像が表示され、下書きや承認待ちのページが開けないことを確かめます。

管理用・公開用Workerの名前、CloudflareのアカウントID、D1のIDを控えます。設定ファイルと秘密ファイルは更新や復旧に使います。GitHubなどへ公開せず、安全な場所に保管します。

##### 設定ファイルの値 {#cloud-install-config}

| 書き換える場所 | 入れる値 |
|---|---|
| 両方の `account_id`、管理用の `vars.CLOUDFLARE_ACCOUNT_ID` | `whoami` に表示されたCloudflareのアカウントID |
| 管理用の `name` と `vars.CLOUDFLARE_WORKER_NAME` | 管理用Workerの名前 |
| 公開用の `name` と管理用の `vars.CLOUDFLARE_PUBLIC_WORKER_NAME` | 公開用Workerの名前。管理用とは別の名前にします |
| 管理用の `vars.CLOUDFLARE_D1_ID` と `d1_databases[0].database_id` | 手順4で控えたD1のID |
| 管理用の `vars.PUBLIC_SITE_URL` | 公開サイトのHTTPSアドレス。パスは付けません |

`workers.dev` を使うと、たとえば管理画面は `https://admin.example-sub.workers.dev/admin`、公開サイトは `https://mikan.example-sub.workers.dev/` になります。サブドメインは公開アドレスに含まれます。メールアドレスやログインIDの一部は使わないでください。

##### 秘密ファイルを作るコマンド {#cloud-install-secrets}

2つの値を安全な乱数で作り、ファイルに保存します。ファイルがすでにある場合は停止します。

```sh
node -e "const fs=require('node:fs'),c=require('node:crypto'); fs.writeFileSync('.env.production.secrets', 'JWT_SECRET='+c.randomBytes(32).toString('hex')+'\nSETUP_TOKEN='+c.randomBytes(32).toString('hex')+'\n', {flag:'wx',mode:0o600})"
```

##### 公開サイトのURLを変更する {#cloud-change-public-url}

1. 新しいドメインを公開用Workerに接続します。
2. 管理用の `wrangler.production.json` で `PUBLIC_SITE_URL` を変更します。
3. `npm run deploy:cloud` で管理用Workerを配備し直します。
4. 管理画面で「静的サイトをビルド」を押します。
5. 新しいURLでページ、画像、サイトマップ、RSSを確かめます。必要に応じて外部リンクや検索結果も移行します。

管理画面のURLも変更する場合は、端末の再認証が必要になることがあります。

<a id="cloud-device-auth"></a>

### 端末認証 {#cloud-auth .cloud}

Cloud版にパスワードはありません。登録したブラウザが鍵になります。**普段使うブラウザ**で管理画面（`/admin`）を開き、設置時の `SETUP_TOKEN`、ユーザー名、表示名、端末の名前を入力して「この端末を登録」を押します。`SETUP_TOKEN` は管理画面へ直接入力します。登録に使った値は再利用できません。ほかのブラウザやパソコンを使うときは、[端末を追加](#add-device)します。

#### すべての端末を失ったとき {#cloud-device-recovery .cloud}

1. 管理用Workerの `SETUP_TOKEN` を新しい値にします。コマンドでは、設置したフォルダで `npx wrangler secret put SETUP_TOKEN --config wrangler.production.json` を実行し、新しい値を入力します。 {.detail}
2. ログイン画面の「復旧」から、新しいトークンと既存のユーザー名で端末を登録します。

それまでの端末と未使用のコードは使えなくなり、MCP APIキーも消えます。必要なものを発行し直してください。

### 端末を追加する・人を招く {#add-device .sqlite .cloud}

登録済みのブラウザで、サイトの[端末とユーザー](#site-users)を開きます。

| やりたいこと | 操作 | コードの有効時間 |
|---|---|---|
| 自分の別のブラウザやパソコンを追加する | 「端末を追加する」でコードを出し、新しい端末のログイン画面に入力する | 3時間 |
| ほかの人を招く | 「ほかの人を招く」でコードを出して渡す。招待された人がユーザー名と表示名を決める | 6時間 |

コードはいずれも1回だけ使えます。ブラウザのCookieを消す前に、使える端末がほかにあることを確認してください。端末を失くしたら、別の登録済み端末から停止します。

端末の登録には、普段使うブラウザとプロファイルを使ってください。プライベートモードで登録すると、ウィンドウを閉じたあとに端末の情報が消えることがあります。

### 更新のしかた {#setup-update}

新しい版が出たかどうかは、管理画面の「サイト」の「バージョン」で分かります。

- **flat版・SQLite版** — 「バージョン」の「更新する」を押すと、プログラムだけが入れ替わります。記事・設定・画像には触れません（[バージョン](#site-version-php)）。 {.flat .sqlite}
- **Cloud版** — 「サイト」で変更内容を確認し、「更新する」を押します（[バージョン（Cloud版）](#site-version-cloud)）。更新の前に、「データ管理」で書き出して保存してください。 {.cloud}

### 複数サイトのログイン {#multi-site-login .flat .sqlite}

同じドメインに複数のサイトを設置した場合も、ログインはサイトごとに行います。編集中にログインの期限が切れたら、別のタブでログインしてから、元の画面で保存し直します。

#### 詳細：ブラウザに保存したパスワード {.flat .detail}

ブラウザにパスワードを保存するときは、サイト画面の「パスワードの変更」にある「保存用サイトID」で、どのサイトのパスワードかを見分けられます。

### デザインやコンテンツを他者へ渡す {.flat .sqlite}

サイトのコピーを人に渡すときは、受け取った人が自分で初回設定できるようにします。

- **flat版** — コピーの `mikanData/settings.json` で `password_hash` を空にします。受け取る人が自分で[パスワードを設定](#flat-setup)します。 {.flat}
- **SQLite版** — コピーの `mikanData` に空の `reset-accounts.txt` を置きます。次のアクセスで既存のユーザーと端末が解除されます。受け取る人が自分のトークンで[最初の端末を登録](#device-setup)します。 {.sqlite}

MCPを使う場合は、受け取る人が自分の[MCP APIキー](#site-mcp-key)を発行します。

---

## 全体像 {#overview}

🍊mikanBoxは、以下の4つのパートでサイトを構築・管理します。

### [description]{.material-symbols-outlined .mat-icon}ページ (Pages)

サイトの「実体」となるページ自体を管理します。URL（ID）を決め、タイトルやSEO情報を設定し、本文（Markdown/HTML）を執筆します。

### [save]{.material-symbols-outlined .mat-icon}サイト (Site)

サイトの共通設定に加えて、システムのアップデート、サイト改ざんのチェック、データのバックアップなど、サイト運営に関わる設定・管理機能をまとめています。

### [widgets]{.material-symbols-outlined .mat-icon}デザイン (Design)

ページ全体のデザインを設定する [ページ]{.type-badge .badge-page} 、共通部品を作る [パーツ]{.type-badge .badge-part} 、AIエージェントへの指示を書く [AI指示]{.type-badge .badge-ai} の3種類のコンポーネントがあります。ヘッダーやフッター、ナビゲーションなどの共通で使うものは パーツ として作成しておくことで、どこからでも利用できます。デザイン要素（コンポーネント）の管理が主な役割です。

### [image]{.material-symbols-outlined .mat-icon}メディア (Media)

基本的には画像の管理を行い、ファイルの確認や検索、リサイズ、貼り付け用のファイル名のコピーなどを行えます。また、ファイルとしてアップロードするJS・CSSファイルの管理もここで行います。flat版、SQLite版では、動画・音声もアップロードできます。

#### サイドメニュー

画面の左に、４つのパートを表す[[description]{.material-symbols-outlined} ページ]{.m-btn .m-btn-gray}・[[save]{.material-symbols-outlined} サイト]{.m-btn .m-btn-gray}・[[widgets]{.material-symbols-outlined} デザイン]{.m-btn .m-btn-gray}・[[image]{.material-symbols-outlined} メディア]{.m-btn .m-btn-gray} のアイコンが並びます。押すと、その画面へ移動します。これらを行き来しながら、サイトの編集を行います。ひとつの画面でスクロールしながら作業を行えるのが、🍊mikanBoxの特長です。

ページ編集やデザイン編集を開いているときだけ、[[edit]{.material-symbols-outlined} 編集]{.m-btn .m-btn-gray} が加わります。右端の「×」で、編集を閉じて一覧へ戻ります。

---

## 共通 {#common}

各パートの上に共通して表示されるものです。画面の上から順に説明します。

#### 管理画面のタブ

ブラウザのタブには「🍊＋サイト名」を表示します。🍊が管理画面の目印になります。

#### プレビュー {.flat .sqlite}

画面の右上にあります。サイトのトップページを、新しいタブで開きます。

#### ログアウト {.flat}

システムからログアウトします。

#### ヘルプ

各パートの見出しの横にあります。公式サイトのヘルプページを、新しいタブで開きます。

#### AIに質問 {#ask-ai}

各パートの見出しの横にあります。操作方法や設定について、開いた場所に対応する公開ヘルプを参照して、外部AIに質問できます。

1. **AIに質問**を押して、質問を入力します。
2. 表示されたプロンプトで、質問と参照先を確認します。
3. **プロンプトをコピー**を押します。
4. **ChatGPTを開く**または**Claudeを開く**を押し、AIの入力欄に貼り付けて送信します。

仕組みや制限を尋ねるときは、公開ヘルプから同じ言語の仕様を参照するようプロンプトに含めます。APIキー、パスワード、個人情報などは質問に入力しないでください。

### カテゴリでの絞り込み

画面の上部に、登録されているカテゴリがタグ（クラウド）状に並びます。ページ一覧とメディア一覧の絞り込みに使います。カテゴリはページ編集で指定し、ページごとに複数指定することもできます。

カテゴリで絞り込んだ状態でも編集作業ができるので、カテゴリをひとつのワークスペースのように使えます。情報量の多いサイトでも、快適に編集できます。データの書き出しでも、ここでのカテゴリの選択が反映されます。

「_」で始まるカテゴリ（`_nav`、`_sample` など）は、サイト運用のためのカテゴリを分類するための印です。一覧では通常のカテゴリのあとに薄く表示されます。詳しくはページ編集の[カテゴリ](#page-edit-category)を参照してください。

#### すべて{.m-btn .m-btn-gray}

絞り込みを解除し、すべてのページを表示します。

#### カテゴリ名{.m-btn .m-btn-gray}

クリックすると、そのカテゴリを含むページだけに絞り込みます。メディア一覧にも同じ絞り込みが反映されます。ファイル名の「カテゴリ名_」という接頭辞で分類できます。ファイル名の付け方や解除方法は、[メディアのカテゴリの絞り込み状態](#media-category-state)を参照してください。

#### [add]{.material-symbols-outlined} 新規{.m-btn .m-btn-gray}

新しいカテゴリ名を直接入力して追加します。まだどのページにも設定されていないカテゴリでも、この時点でクラウドに表示されます。

#### [delete]{.material-symbols-outlined} 削除{.m-btn .m-btn-gray}

クリックすると、各カテゴリのタグに削除用の「×」が表示される「削除モード」に切り替わります。「×」またはカテゴリ名をクリックすると、全ページからそのカテゴリを一括削除します。該当するページがあるときは、対象のページ数が表示されます。

---
## ページ {#page-mgmt}

作成済みのページを一覧で確認・管理する画面です。

#### [add]{.material-symbols-outlined} 新規作成{.m-btn .m-btn-blue}

新しいページを作成します。クリックするとページ編集エリアが開きます。カテゴリを絞り込んだ状態で新規作成すると、そのカテゴリがあらかじめ設定された状態でページ編集画面が開きます。

#### 出力形式の絞り込み

出力形式でページ一覧を絞り込みます。カテゴリの絞り込みや検索と組み合わせられ、ページ編集を開閉しても保たれます。

- すべての出力形式
- HTML
- HTML + Markdown
- Markdown
- データ
- mikanBox DB

#### ページの並び順

ページ一覧の表示順を選択します。初期設定の「表示順」では、トップページ（`index`）、承認待ちページの順に優先します。そのあと「表示順」の数値が小さい順、更新日時の新しい順、ID順に並べます。「表示順」以外を選んだ場合は、すべてのページを選んだ条件で並べます。

- 表示順（初期設定）
- 更新が新しい順
- 更新が古い順
- ID順
- タイトル順

#### キーワード検索

ページのID・タイトル・本文・概要を検索します。カテゴリや出力形式で絞り込んだ状態でも検索できます。

### ページ一覧

#### 編集・プレビュー

各ページの行に、アイコンだけのボタンが3つ並びます。

- [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **編集** — <a href="#page-edit">ページ編集</a>を開きます。
- [[edit_square]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **ビジュアル編集** — <a href="#visual-edit">ビジュアル編集</a>を開きます。出力形式で、HTMLを出すページだけで使えます。それ以外のページでは、灰色で押せません。
- [[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **公開ページを開く** — 公開先を新しいタブで開きます。公開されていないページ（下書き・承認待ち）では、灰色で押せません。

編集中の内容は、ページ編集画面の[プレビュー](#page-preview)で確認できます。詳しくは[ページ編集](#page-edit)を参照してください。

PHP版で[[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue}を使えるのは、「公開(動的)」または「公開(静的)」のページです。 {.flat .sqlite}

##### Cloud版の地球儀 {.cloud}

| 公開設定 | 公開先の状態 | 地球儀 | 開ける内容 |
|---|---|---|---|
| 公開 | 最新の変更まで反映（ビルド）済み | 青 | 最新の公開ページ |
| 公開 | ファイルがない | グレー | 開けません |
| 公開 | ファイルがあり、未反映の変更がある | グレー | 現在公開中のページ |
| 下書き・承認待ち | ファイルの有無にかかわらず | グレー | 開けません |

#### 公開設定 {#page-list-status}

公開状態を選ぶと、その場で保存されます。公開しないページは「下書き」にします。

SQLite版とCloud版では、確認中のページを「承認待ち」にできます。 {.sqlite .cloud}

SQLite版では、「承認待ち」のページを[プレビュー共有URL](#page-review-url)で確認してもらえます。 {.sqlite}

PHP版の「公開(動的)」では保存した内容が表示されます。ページ一覧で「公開(静的)」に切り替えると、そのページをビルドして出力先に書き出します。「公開(静的)」から別の状態に変えると、生成済みのファイルをその場で出力先から削除します。 {.flat .sqlite}

Cloud版では、公開や取り下げを公開サイトに反映するには、設定を変えたあとに[ビルド](#static-build-button)します。「下書き」や「承認待ち」に切り替えたページも、次のビルドまでは公開済みのファイルが残ります。 {.cloud}

詳しくはページ編集の[公開設定](#page-edit-status)を参照してください。

#### ID

ページのURLになる識別子（スラッグ）です。例として `news` と設定すると、URLは `/news` の形になります。

詳しくはページ編集の[ID](#page-edit-id)を参照してください。

#### タイトル

ページのタイトルです。クリックするとページ編集を開きます。HTML以外のページには、タイトルの右に出力形式のラベル（HTML + Markdownは `+MD`、Markdownは `MD`、データ形式は `JSON`・`JS` など）が付きます。

詳しくはページ編集の[タイトル](#page-edit-title)を参照してください。

#### 更新

ページの更新日時から日付を表示します。作成時の日時が初期値で、初めて公開に切り替えると、その時点の日時に変わります。

詳しくはページ編集の[更新](#page-editor-date)を参照してください。

#### 表示順

ナビゲーションなどの一覧での表示優先順位です。リスト表示する `NAV_LINKS`・`NAV_CARDS` のなかで、またこのページ一覧のなかで、数字が小さいほど前（上）に表示されます。0より小さいものは、`NAV_LINKS`・`NAV_CARDS` のリストに表示されません。

詳しくはページ編集の[表示順](#page-edit-sort-order)を参照してください。

#### カテゴリ

ページに設定されたカテゴリが表示されます。`{{NAV_LINKS:カテゴリ}}` などで絞り込みに利用されます。「_」で始まるカテゴリはサイト運用で使用するカテゴリで、薄く表示されます。

詳しくはページ編集の[カテゴリ](#page-edit-category)を参照してください。

### ページ送り

一覧が複数ページにわたるときは、一覧の下にページ番号と件数が表示されます。[前へ]{.m-btn .m-btn-gray}・[次へ]{.m-btn .m-btn-gray} で移動します。表示件数は<a href="#site-settings">サイト共通設定</a>で変更できます。

### 静的サイトをビルド {#static-build-button}

#### [auto_awesome]{.material-symbols-outlined} 静的サイトをビルド{.m-btn .m-btn-blue}

「公開(静的)」のページの編集内容をサイトに反映させるには、静的サイトをビルドする必要があります。 {.flat .sqlite}

ページの編集内容をサイトに反映させるには、静的サイトをビルドする必要があります。 {.cloud}

ボタンの下には、ビルドに関する情報が表示されています。

- 「公開側に反映していない変更があります（○ページ）。」または「公開側は最新です。」
- 最後にビルドした日時
- 公開先（書き出しモードでは書き出し先のフォルダ）

コンポーネントやサイト設定だけを変えた場合は、ページの数に入りません。

Cloud版は変更のあるファイルを公開用Workerへ送ります。ビルドが完了するまで画面を開いておきます。公開用Workerの設定を求められたら、[公開用Workerの接続](#public-worker)を確かめます。 {.cloud}

---

## ページ編集 {#page-edit}

ページの内容や設定を編集する画面です。「新規作成」ボタンや、ページのタイトル・編集ボタンをクリックすると表示されます。

SQLite版とCloud版では、ほかの編集者が開いているページの編集を制限します。 {.sqlite .cloud}

選んだ[出力形式](#page-edit-output-format)によって、表示する欄が変わります。出力形式によって表示が変わる欄には、見出しの下に「出力形式が○○のときだけ表示します。」と書いてあります。何も書いていない欄は、どの出力形式でも表示します。

| 欄 | HTML | HTML + Markdown | Markdown | データ形式・mikanBox DB |
|---|:-:|:-:|:-:|:-:|
| デザインコンポーネント | ○ | ○ | － | － |
| Markdown版の末尾コンポーネント | － | ○ | ○ | － |
| タグを展開する | － | － | － | ○（mikanBox DBを除く） |
| サムネール・OGP画像 | ○ | ○ | ○ | － |
| キーワード | ○ | ○ | ○ | － |
| ページ概要 | ○ | ○ | ○ | － |
| 本文 | ○ | ○ | ○ | ○ |
| Raw HTML | ○ | ○ | － | － |
| ページ専用CSS | ○ | ○ | － | － |
| ページ専用JavaScript | ○ | ○ | － | － |

#### 更新 {#page-editor-date}

作成した日時が初期値です。初めて公開に切り替えたときに、その日時へ自動で更新します。取り下げたページを再公開するときや、公開済みのページを編集するときは更新日時を維持します。この欄で日時を手動で変更できます。

#### 履歴 {.sqlite .cloud}

保存するたびに過去の内容が自動的に最大10件保存され、日時と編集者名（複数ユーザーで運用している場合）付きで選択できます。過去のリビジョンを選ぶと内容がフォームに読み込まれ、「過去のリビジョンをプレビュー中」という警告バナーが表示されます。そのまま [[save]{.material-symbols-outlined}保存]{.m-btn .m-btn-blue} を押すと、そのリビジョンの内容が現在のバージョンとして復元されます。ドロップダウンの先頭（「最新バージョン (現在)」）を選び直すと、最新の内容に戻ります。

SQLite版では、保存済みの履歴があるときだけ表示します。 {.sqlite}

#### [edit_square]{.material-symbols-outlined} ビジュアル編集{.m-btn .m-btn-blue}

[ビジュアル編集](#visual-edit)の画面を開きます。出力形式で、HTMLを出すページだけで使えます。それ以外の出力形式では、灰色で押せません。

ビジュアル編集では、公開サイトのアドレスで書いたリンクからも、リンク先のページを編集できます。下書きのHTMLページにも移動できます。 {.cloud}

#### タイトル {#page-edit-title}

ページのタイトルです。必須項目です。コンポーネント内では `{{TITLE}}` や `{{FULL_TITLE}}`（「ページタイトル - サイト名」の形式）で出力できます。

#### 公開設定 {#page-edit-status}

ページの公開状態を選択します。

- **下書き** — 非公開。管理者以外はアクセスできません。
- **承認待ち** — 非公開ですが、推測不可能な確認用の共有URL（`?preview=トークン`、有効期限7日）が自動生成され、この画面にも表示されます。URLを知らせるだけで、承認者に管理画面へログインさせずに内容を確認してもらえます。 {.sqlite}
- **承認待ち** — 非公開です。ログインした編集者だけがプレビューできます。作業状態を示すラベルで、閲覧権限は下書きと同じです。 {.cloud}
- **公開(動的)** — PHPで動的に表示します。 {.flat .sqlite}
- **公開(静的)** — サイト画面の静的サイト生成 (SSG)の設定に従ったディレクトリ、ファイル設定で静的HTMLファイルとして公開します。 {.flat .sqlite}
- **公開** — ビルドで出力形式に応じたファイル（HTML・Markdown・JSONなど）を作り、静的ファイルとして配信します。 {.cloud}

「公開(静的)」から別の公開設定に変えて保存すると、生成済みの静的HTMLファイルを削除します。 {.flat .sqlite}

公開済みのページを「下書き」や「承認待ち」に変更した場合は、[ビルド](#static-build-button)した時点で公開サイトから取り除かれます。 {.cloud}

#### プレビュー共有URL（関係者確認用） {#page-review-url .sqlite}

公開設定が「承認待ち」のときだけ表示します。テキストボックスにURLが入ります。

[[content_copy]{.material-symbols-outlined} コピー]{.m-btn .m-btn-gray} を押すと、URLをクリップボードにコピーします。新規作成のページでは、一度保存したあとにURLが有効になります。

このURLには7日間の有効期限があります。期限が過ぎたURLは開けなくなるので、この画面を開き直して新しいURLを確認し、渡し直してください。同じ日のうちは同じURLが表示されます。確認用URLのページには、リファラを送らない・検索エンジンに載せない・キャッシュしない指定が付きます。

#### メモ

管理者だけが見られる非公開のメモ欄です。覚えておきたいことや引き継ぎ事項などを自由に入力できます。サイトや公開APIには表示されません。認証済みの管理MCPからは、AIも作業の参考として読めます。

#### ID {#page-edit-id}

URLになるページの識別記号（スラッグ）です。半角英数字・ハイフン・アンダースコアが使用できます。ドット（`.`）は使えません（拡張子は[出力形式](#output-formats)から付きます）。

ID自体に「/」を使用することができますが、先頭・末尾・連続した「/」は使えません。`/` は階層として扱われます。例えば、`news/2024` のようなIDを設定すると、`サイトのURL/news/2024` のようなURLになります。ただし、システム用のパスと重なるIDは使えません。

flat版では、JSONのフォルダ構造を変更すると、IDも変更されます。{.flat}

使用済みのIDを指定して保存すると、確認が表示されます。「IDを変更する」を選ぶと、入力した内容を残したままID欄に戻ります。「上書きする」を選ぶと、そのIDのページが編集中の内容に置き換わります。既存ページのIDを変更した場合は、変更前のIDのページがなくなります。

「上書き前のページを下書きで残す」は、初めから選ばれています。このまま上書きすると、保存先にあったページを別IDの下書きとして残します。たとえば `news` の保存先を残す場合は `news-bk` になり、使用済みなら `news-bk-2` などになります。チェックを外すと、下書きを作らずに上書きします。

トップページのIDは `index` です。トップページを差し替えるには、今のページのIDを変更してから、新しいページのIDを `index` にします。

PHP版では設置先の管理フォルダやデータフォルダ、`media`・`api`・`setup-token` などが予約されています。 {.flat .sqlite}

Cloud版では `admin`・`mcp`・`public-mcp` なども予約されています。 {.cloud}

#### 出力形式 {#page-edit-output-format}

ページの出力形式を、HTML / HTML + Markdown / Markdown / データ（JSON・XML・CSV・テキスト・JavaScript・CSS・[mikanBox DB](#mikanbox-db)）から1つ選びます。既定はHTMLです。すぐ下に、実際に出るURL（例 `https://example.com/news-list.json`）が表示されます。

ここで選んだ出力形式によって、設定する内容が異なります。使わない設定項目は表示されなくなります（一覧は[ページ編集](#page-edit)冒頭の表）。詳しくは[出力形式](#output-formats)を参照してください。`404` ページの形式はHTMLです。

**この設定は、「出力形式」です。本文にMarkdownを入力しても、それを　HTMLに変換して表示するのであれば、「HTML」を選択します。**

たとえば、最新記事リストをJSONで公開し、各ページからJavaScriptで読み込むと、一覧を置いたページの本文を編集せずに更新できます。Cloud版でJSONの内容を公開サイトに反映するには、ビルドが必要です。

#### カテゴリ（カンマ区切り） {#page-edit-category}

ページを分類するカテゴリをカンマ区切りで自由に入力します。入力欄の下に現在使用中のカテゴリが表示されます。カテゴリの一覧ページは自動では作成されません。必要な場合は、ページを作成し、`{{NAV_LINKS:カテゴリ}}` や `{{NAV_CARDS:カテゴリ:コンポーネントID}}` を配置してください。

「_」で始まるカテゴリ（`_nav`、`_sample`、`_temp` など）は、サイトを運用するうえで利用する分類の印として扱います。内容的なカテゴリ（`coffee` など）と文字1つで区別でき、いくつでも付けられます。`{{NAV_LINKS:_nav}}` のような明示の指定には使えますが、[`@same`](#navigation) の判定、`{{CATEGORY}}`、[Markdown版](#markdown-version)の `categories` からは除外されます（`{{CATEGORY:all}}` には表示されます）。「@」で始まるカテゴリ名は、`@same` などの特別な指定と区別するため予約されていて使えません。

#### デザインコンポーネント

出力形式が「HTML」「HTML + Markdown」のときだけ表示します。

ページのレイアウトに使用するページコンポーネントを選択します。「デザイン編集」の「タイプ」で「ページ」を選んだコンポーネントが選択肢として表示されます。

##### このページでは計測タグを読み込まない {#page-edit-no-tracking}

サイト共通設定で計測タグを設定しているときだけ表示します（出力形式が「HTML」「HTML + Markdown」のときのみ）。チェックを入れると、このページだけ、計測タグを読み込みません。設定のしかたは、サイト共通設定の[共通の読み込み](#common-loading)を参照してください。

#### Markdown版の末尾コンポーネント

出力形式が「HTML + Markdown」「Markdown」のときだけ表示します。

[Markdown版](#markdown-version)の最後に付けるコンポーネントのIDを、付ける順にカンマ区切りで入力します。空欄なら何も付けません。使用できるのはパーツのみで、ページコンポーネントとAI指示は使えません。

Markdownに関連情報などを付け加えたいときに使います。

#### タグを展開する

出力形式が「JSON」「XML」「CSV」「テキスト」「JavaScript」「CSS」のときだけ表示します（mikanBox DBでは表示しません）。

本文の `{{ }}` タグを展開するかどうかを切り替えます。JSON・XML・CSV・テキストは既定でオン、JavaScript・CSSは既定でオフです。値は形式に合わせてエスケープされます（[データ形式](#data-formats)）。

#### 表示順 {#page-edit-sort-order}

ナビゲーションなどでの並び順を指定できます。数字の小さい順に並びます。0より小さいものは、ページのリストに表示されません。

#### サムネール・OGP画像

出力形式が「HTML」「HTML + Markdown」「Markdown」のときだけ表示します。

SNSでシェアされた際や、カード一覧などで表示される画像ファイル名（例: `ogp.jpg`）を指定します。`meta` タグの `og:image` として出力されます。コンポーネント内では `{{OGP_IMAGE}}` で利用できます。

#### キーワード

出力形式が「HTML」「HTML + Markdown」「Markdown」のときだけ表示します。

ページの検索用キーワード（meta keywords）をカンマ区切りで入力します。コンポーネント内では `{{KEYWORDS}}` で出力されます。

#### ページ概要 (meta description)

出力形式が「HTML」「HTML + Markdown」「Markdown」のときだけ表示します。

ページの概要となる説明文を設定します。 `{{DESCRIPTION}}` で出力され、検索結果のタイトル下に表示される紹介文として利用されます。

#### 本文

ページのメインコンテンツを Markdown 記法やHTMLで記述します。MarkdownとHTMLは混在できます。AIが生成した複雑なHTMLをそのまま貼り付けても正常に動作します。利用できるMarkdown記法と独自タグは編集画面下部のガイドを参照してください。詳しくは [Markdown](#markdown)・[コンポーネント（タグ）](#components) の項目を参照してください。

この欄はどの出力形式でも表示します。欄の見出しは出力形式に合わせて、「本文 (Markdown or HTML)」「本文 (Markdown)」「本文 (JSON)」のように変わります。データ形式では、本文がそのまま出力されます（[データ形式](#data-formats)）。

##### Raw HTML

出力形式が「HTML」「HTML + Markdown」のときだけ表示します。

本文をMarkdownに変換せず、HTMLとして表示したいときに、チェックを入れます。AIが作った完成済みのHTMLページなどを、そのまま貼り付けて使うときの設定です。

**完成したHTMLをそのまま使うには**

1. 「デザインコンポーネント」を「なし（本文のHTMLをそのまま使用）」にします。
2. 本文に、完成したHTMLを貼り付けます。
3. 「Raw HTML」にチェックを入れます。

サイト共通のヘッダーやフッターも使いたいときは、手順1で、`_layout` などのページコンポーネントを選びます。貼り付けたHTMLは、そのレイアウトの本文の位置に入ります。

サイト共通設定の「共通の読み込み」で計測タグを設定していれば、完成したHTMLをそのまま使うページにも、計測タグが自動で入ります（`head` の中と、`body` の開始直後）。外したいページだけ、「このページでは計測タグを読み込まない」にチェックを入れます。

概要とOGP画像など、サイト共通の情報を自動で補いたい場合は、次の詳細を確認してください。

###### 詳細：貼り付けたHTMLに補われるもの {.detail}

貼り付けたHTMLに足りないものがあれば、次のとおり補います。すでにHTMLに書いてある項目は、上書きしません。

- `html`・`head`・`body` がなければ、補います。
- `head` に、UTF-8の文字コード指定、スマートフォン向けの表示設定（viewport）、タイトルがなければ、追加します。タイトルは「ページ名 - サイト名」の形です。
- `head` に概要（`description`）とOGP画像（`og:image`）がなければ、補います。ページ編集の「ページ概要」「サムネール・OGP画像」に値があればそれを使い、なければサイト共通設定の「サイトの共通概要」「共通OGP画像」を使います。どちらにも値がなければ、補いません。

#### ページ専用CSS

出力形式が「HTML」「HTML + Markdown」のときだけ表示します。

このページだけに適用したいスタイルを記述します。自動的にスコープ化されるため、他のページや本文以外の部分へ影響を与えません。CSSのなかで展開できるのは `{{SITE_URL}}` や `{{PAGE_URL:ID}}` などの基本情報タグです。

レイアウトなどスコープ外の要素へ意図的にスタイルを適用する場合は、セレクタ全体を `:global(...)` で囲みます。たとえば `:global(.hero_box) { display: none; }` と記述すると、`.hero_box` だけにスコープ外からスタイルを適用できます。通常のセレクタはこれまでどおり、このページの本文内だけに適用されます。

#### ページ専用JavaScript

出力形式が「HTML」「HTML + Markdown」のときだけ表示します。

`<script>` タグを付けずにJavaScriptを記述します。コードがある場合は欄が開いた状態で表示されます。CMSがページ本文末尾（通常は `</body>` の直前。閉じタグのないHTMLでは末尾）へスクリプトを自動挿入します。コンポーネントのJavaScriptに続いて、このページのコードが一度だけ出力されます。動的表示・プレビュー・静的HTMLのいずれでも利用できます。

JavaScriptにはCSSのスコープ設定は適用されません。JavaScript欄ではMarkdown変換・独自タグの展開・URLの自動補完は行われず、コードをそのまま使用します。HTML本文に直接書く `<script>` も引き続き利用できます。外部ライブラリへの依存など、必要な読み込み順はコード側で指定してください。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-blue}

ページの内容をすべて保存します。

公開済みのページを変更しても、[ビルド](#static-build-button)するまで公開サイトには反映されません。 {.cloud}

編集中に別の画面やAI（MCP）から同じページが更新されると、保存が止まります。必要な入力内容を控えてから画面を再読み込みし、最新の内容を確認してください。 {.cloud}

#### [visibility]{.material-symbols-outlined} プレビュー{#page-preview .m-btn .m-btn-blue}

編集中の内容を、保存せずに、新しいタブで表示します（ボタンの横に「編集中の内容を表示します（保存はされません）。」と表示します）。すべての出力形式・公開設定、新規ページでも使えます。HTMLを出すページはページとして、Markdownだけのページ・データ形式・mikanBox DBは実際に出るテキストやJSONを、文字コードUTF-8で表示します。プレビューは管理画面と切り離した状態（スクリプトは動きますが、管理画面には触れません）で表示します。実サイトのアドレスで開くには、ページ一覧の[[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue}を使います（<a href="#page-mgmt">ページ一覧</a>の表）。

Cloud版では、編集中の内容をプレビュー用に30分だけ保持します。1人につき4件までで、本人だけが開けます。公開サイトのURLで書かれたリンクは公開サイトを開きます。下書きのHTMLページどうしを移動するには、[ビジュアル編集](#visual-edit)を使います。 {.cloud}

#### [arrow_back]{.material-symbols-outlined} 一覧へ戻る{.m-btn .m-btn-gray}

ページの一覧へ戻ります。保存していない変更がある場合には、以下の選択肢が表示されます。

- [[save]{.material-symbols-outlined} 保存して閉じる]{.m-btn .m-btn-blue} — 保存して、一覧へ戻ります。
- [[delete]{.material-symbols-outlined} 破棄して閉じる]{.m-btn .m-btn-red} — 変更を捨てて、一覧へ戻ります。
- [キャンセル]{.m-btn .m-btn-gray} — そのまま編集を続けます。

#### [delete]{.material-symbols-outlined} 削除{.m-btn .m-btn-red}

ページを完全に削除します。削除後は元に戻せません。

PHP版では、ページを削除すると公開サイトからすぐに取り除かれます。「公開(静的)」の場合は、現在の出力先にある静的ファイルも削除します。 {.flat .sqlite}

Cloud版では、公開済みのページを削除したあと、[ビルド](#static-build-button)した時点で公開サイトから取り除かれます。トップページ（`index`）には「削除」が表示されません。 {.cloud}

#### Markdown記法とタグガイド

画面下部から[Markdown](#markdown)の簡易ガイドと[タグガイド](#tags)を開けます。

---

## ビジュアル編集 {#visual-edit}

ページの見た目を確認しながら、本文やページ専用CSSを編集できます。ページ一覧またはページ編集で [[edit_square]{.material-symbols-outlined} ビジュアル編集]{.m-btn .m-btn-blue} を押します。HTMLまたはHTML + Markdownのページで使えます。

### 右下のツールバー {#visual-dock}

画面の右下に、ビジュアル編集の操作をまとめたツールバーが表示されます。左から次の順に並び、区切り線で3つのグループに分かれています。オンとオフのあるボタンは、オンのとき色が変わります。

#### ページとページ設定

- [[menu_open]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **ページを切り替える（⌘/Ctrl+K）** — ページの一覧を開いて、別のページを開いたり、新しいページを作ったりします。<a href="#visual-pages">別のページを開く・作る</a>を参照してください。
- [[dashboard]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **管理画面（ページ一覧）へ（g l）** — 管理画面のページ一覧を、新しいタブで開きます。
- [[tune]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **このページの設定を管理画面で開く（g e）** — タイトル・公開状態などを変えるため、このページのページ編集を、新しいタブで開きます。

キーボードでは、「g」を押してから「l」または「e」で、上の2つを開けます。「g」を押すと、短いヒントが表示されます。

#### 元に戻す・履歴

- [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **元に戻す（⌘/Ctrl+Z）** — 本文やCSSの保存を、ひとつ前の状態に戻します。戻せる回数は、ボタンにマウスを乗せると「あと○回」と出ます。戻せるものがないときは、押せません。
- [[redo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **やり直す（⌘/Ctrl+Shift+Z）** — 元に戻した保存を、やり直します。
- [[history]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **編集の履歴（作業の前の状態に戻す）** — 履歴の一覧を開きます。<a href="#visual-save">保存と履歴</a>を参照してください。

#### 表示と編集

- [[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **画面幅を切り替えて確かめる** — 画面幅をスマホ・タブレット・PCに切り替えて確かめます。<a href="#visual-viewport">画面幅を変えて確認する</a>を参照してください。
- [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **クリックで編集（オン／オフ）** — オンのとき、本文の文字をクリックして、その場で直せます。オフにすると、閲覧だけになり、リンクやボタンを試せます。押すたびに、「クリックで編集: オン／オフ」と表示します。設定は覚えられ、次に開いたときも同じです。<a href="#visual-inline">文字をその場で直す</a>を参照してください。
- [[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} **調べる** — ページの要素をクリックして、セレクタとCSSのルールを調べ、本文やCSSを直す場所を選びます。<a href="#visual-inspect">虫眼鏡で要素とCSSを選んで直す</a>を参照してください。
- [[edit_note]{.material-symbols-outlined} 本文]{.m-btn .m-btn-gray} **本文** — 本文全体のMarkdownを、画面下のパネルで編集します。<a href="#visual-md-panel">「本文」パネルで直す</a>を参照してください。
- [[palette]{.material-symbols-outlined} CSS]{.m-btn .m-btn-gray} **CSS** — このページ専用のCSSを、画面下のパネルで編集します。<a href="#visual-css-panel">ページ専用CSSを直す</a>を参照してください。

### 文字をその場で直す {#visual-inline}

本文の文字をクリックすると、その場所で入力できます。リンクやボタンの文字を直すときは **Shift+クリック**します。

文字をクリックすると、入力欄の近くに濃い青色の小さなバー（インライン編集バー）が表示されます。バーの左側には、操作のヒント「⌘/Ctrl+Enter で保存・Esc でキャンセル」が出ます。右側のボタンは、左から次の順に並びます。

- [[add_row_above]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **上に段落を追加** — 編集中の段落の前に、新しい段落を足します。リスト編集時には上に項目を追加します。
- [[add_row_below]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **下に段落を追加** — 編集中の段落の後に、新しい段落を足します。リスト編集時には下に項目を追加します。
- [[delete]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **このブロックを削除** — 編集中の段落を削除します。リスト編集時にはこの項目を削除します。
- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **この部分を「本文」で直す** — 入力した内容を引き継いで、「本文」パネルを開きます。
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **キャンセル（Esc）** — 入力を取り消します。
- [[check]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **保存（⌘/Ctrl+Enter）** — 入力した内容を保存します。

入力は文字のまま保存します。太字やリンクにしたり、段落を書き換えたりするときは、「本文」パネルを使います。

### 「本文」パネルで直す {#visual-md-panel}

**⌥クリック（Alt+クリック）**で、選んだ部分だけを画面下の「本文」パネルに開きます。右下の [[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} で要素を選び、パスをクリックしても開けます。本文をまとめて編集するには、右下の [[edit_note]{.material-symbols-outlined} 本文]{.m-btn .m-btn-gray} を押します。

パネルは、上から次の順に並びます。

#### パネルのヘッダー

左から、タイトル「このページの本文（Markdown）」、ヒント「入力するとすぐページへ反映（保存は ⌘/Ctrl+Enter）」、状態の表示、2つのボタンです。入力するとページの表示がその場で変わります。状態の表示は、保存していない変更があるときに「● 未保存」と出ます。

- [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **保存（⌘/Ctrl+Enter）** — 本文を保存します。
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **閉じる** — パネルを閉じます。

#### Markdownのクイック操作バー

入力欄の上に並ぶ、濃い青色のバーです。選んでいる文字や、カーソルのある行に、Markdownの記法を入れます。左から次の順です。

- [[title]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **見出し（## → ### → #### → なし）** — 押すたびに、見出しのレベルが切り替わります。
- [[format_bold]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **太字（⌘/Ctrl+B）**
- [[format_italic]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **斜体（⌘/Ctrl+I）**
- [[link]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **リンク（⌘/Ctrl+K）**
- [[format_list_bulleted]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **箇条書き**
- [[format_list_numbered]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **番号つきリスト**
- [[format_quote]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **引用**
- [[code]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **コード（インライン）**
- [[image]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **画像を入れる（一覧から選ぶ・貼り付け・ドラッグでも可）** — メディアから画像を選びます。新しい画像は、一覧の「追加」、貼り付け、入力欄へのドラッグで入れられます（PNG・JPEG・GIF・WebP、10 MBかつ4,000万画素まで）。

#### 編集対象バー

「本文」パネルの中で、部分を選んでいるときだけ、クイック操作バーの下に表示します。左側に、選んだ部分の位置（タグの階層と、本文の何行目か）が出て、右側に次のボタンが並びます。本文全体を開いているときは表示しません。

- [[subdirectory_arrow_right]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **中の要素を編集対象にする（複数あれば番号で選ぶ）** — 選んでいる入れ物の、中の要素へ移ります。中の要素の数が、ボタンに数字で出ます。複数あるときは、番号の付いた一覧から選びます。
- [[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **ひとつ外（親）の要素を編集対象に戻す** — ひとつ外側の要素へ戻ります。
- [[add_row_above]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **上に段落を追加** — 選んでいる部分の前に、新しい段落（HTMLの中では要素）を足します。
- [[add_row_below]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **下に段落を追加** — 選んでいる部分の後に、新しい段落を足します。
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **このブロックを複製（下に同じ内容を追加）** — 選んでいる部分を、そのすぐ下に複製します。
- [[delete]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **このブロックを削除** — 選んでいる部分を削除します。確認のため、2回押します。
- [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **追加・複製・削除を、ひとつ戻す** — 上の追加・複製・削除を、ひとつ前の状態に戻します。入力欄の取り消しでは戻せないので、この専用のボタンを使います。
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **選んだ部分だけの表示をやめて、全体を表示** — 本文全体の表示に戻ります。

ナビゲーションのカードなど、独自タグ（`{{…}}`）で表示している部分を選んだときは、バーの下に、タグであることの案内が出ます。カードに表示されているタイトルや画像は、リンク先のページ編集で変更します。

#### 入力欄

選んだ部分（何も選んでいなければ本文全体）のMarkdownを、そのまま入力します。入力欄の上のボタンでは足りない記法は、直接書きます。

### ページ専用CSSを直す {#visual-css-panel}

右下の [[palette]{.material-symbols-outlined} CSS]{.m-btn .m-btn-gray} を押すと、ページ専用CSSのパネルが開きます。[[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} で要素を選び、表示される小さな枠の [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} を押すと、その要素に関係するルールだけを選んで開けます（<a href="#visual-inspect-pop">枠のボタン</a>）。CSSの変更も、入力中にページへ反映されます。

パネルは、「本文」パネルと同じ並びです。違いは、次のとおりです。

#### パネルのヘッダー

タイトルが「このページ専用のCSS」になります。ボタンは、「本文」パネルと同じ [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **保存（⌘/Ctrl+Enter）** と [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **閉じる** です。

#### CSSのルールの選択バー

ルールを選んで開いているときだけ、入力欄の上に表示します。左側に、選んだルールのセレクタと、「n / 全体の数」が出て、右側に次のボタンが並びます。クイック操作バーはありません。

- [[arrow_upward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **前の該当ルール** — 前のルールへ移ります。先頭では押せません。
- [[arrow_downward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **次の該当ルール** — 次のルールへ移ります。最後では押せません。
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **選んだ部分だけの表示をやめて、全体を表示** — ページ専用CSS全体の表示に戻ります。

#### 入力欄

選んだルール（何も選んでいなければCSS全体）を、そのまま入力します。

#### 要素を調べたときの枠のボタン {#visual-inspect-pop}

[[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} で要素を選ぶと、その要素を表すセレクタの一覧が、小さな枠に出ます。1行が1つのセレクタで、左から、セレクタ、そのセレクタに当たる要素の数、3つのボタンの順に並びます。セレクタをクリックしても、本文を開けます。

- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **この部分の本文を編集** — その要素の本文を、「本文」パネルに開きます。本文のブロックではない部分では、押せません。
- [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **CSSのルールを選ぶ（この要素に当たる・名前を含むもの）** — 関係するページ専用CSSのルールを、番号つきの一覧で表示します。選ぶと、そのルールをCSSパネルに開きます。
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **コピー** — セレクタをコピーします。

ルールの一覧では、[[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **セレクタの一覧へ戻る** で、セレクタの一覧へ戻れます。

### 虫眼鏡で要素とCSSを選んで直す {#visual-inspect}

[[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} は、ページの見た目の上で「ここを直したい」という要素を選び、その要素の本文や、当たっているCSSのルールを、編集パネルに開くための道具です。次の流れで使います。

**手順1.** 右下の [[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} を押します。オンのあいだはボタンの色が変わり、ページ上で、マウスを重ねた要素が枠で強調されます。この間、リンクやボタンは動きません。

**手順2.** 直したい要素をクリックします。クリックした要素の `<タグ名> のセレクタ` の一覧が、小さな枠に出ます。1行が、1つのセレクタです。外側の要素へたどった形で、何行か並びます。行の右側には、そのセレクタに当たる要素の数（○件）も出ます。

**手順3.** 直したいものに応じて、行のボタンを選びます。選ぶと、[[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} は自動的にオフになります。

- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **この部分の本文を編集** — その要素の本文を、「本文」パネルで、選んだ状態で開きます。セレクタの文字をクリックしても同じです。本文の外の要素（ヘッダー・フッターなど）では、押せません。
- [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **CSSのルールを選ぶ** — その要素に当たるルールと、その要素のクラス名・idを名前に含むルールが、番号つきの一覧で出ます。ルールを選ぶと、そのルールだけが、CSSパネルに開きます。一覧には、ルールの行番号と、内容の一部も出ます。[[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、セレクタの一覧へ戻れます。
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **コピー** — セレクタをコピーします。

**手順4.** 開いたパネルで、そのまま編集します。入力するとページの表示がその場で変わります。CSSパネルでは、[[arrow_upward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} と [[arrow_downward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、ほかの該当ルールへ移れます。[[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} または ⌘/Ctrl+Enter で保存します。パネルの使い方は、<a href="#visual-md-panel">「本文」パネルで直す</a>と<a href="#visual-css-panel">ページ専用CSSを直す</a>を参照してください。

枠の下には、選んだ要素に応じて、次の案内が出ることがあります。

- ページの本文の外（ヘッダー・フッターなど）の要素です。ページ専用CSSは本文の中にだけ効くので、部品のCSSで指定してください。
- ここは本文のブロックではないので、本文は編集できません（レイアウトなど）。CSSは確認できます。
- ページのタイトルそのものです。本文ではないので、ここでは直せません。案内の下のリンクから、ページ設定の「タイトル」を開いて変更します。

やめるときは、Escを押します。枠が出ているときは枠が閉じ、もう一度押すと [[search]{.material-symbols-outlined} 調べる]{.m-btn .m-btn-gray} がオフになります。[[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray} の画面幅の確認の中でも、同じように要素を選べます（確認は閉じません）。

### 保存と履歴 {#visual-save}

「本文」や「CSS」の編集は、パネルの [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} または ⌘/Ctrl+Enter で保存します。静的に公開しているページの変更は、<a href="#static-build-button">ビルド</a>するまで公開サイトには反映されません。

- 入力中の表示に反映できなくても、パネルに「保存はできます」と表示されていれば保存できます。
- 編集中の本文やCSSが別の画面で変わった場合は、保存を止めて知らせます。入力した内容を控えてから、ページを開き直してください。
- 右下の [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} と [[redo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、本文やCSSの保存を、直近3回分まで戻したり、やり直したりできます。
- 右下の [[history]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、「編集の履歴」の枠を開きます。ページを開いてから最初に保存する直前の状態が、1回の作業につき1件、日時つきで並びます。直近10件まで残ります。一覧の日時を選んで、確認で「はい」を選ぶと、その状態に戻します。戻したあとは、ページを読み込み直します。直前の状態は、[[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で戻せます。枠は、[[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で閉じます。

Cloud版では、ほかのユーザーがページ編集中の場合も保存できません。 {.cloud}

### 画面幅を変えて確認する {#visual-viewport}

右下の [[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray} を押すと、画面の上に、画面幅を切り替えるバーが出ます。その幅のまま、編集できます。バーの左から、次のボタンが並びます。

- [[smartphone]{.material-symbols-outlined} 375]{.m-btn .m-btn-gray} **375 で表示** — スマートフォンの幅です。
- [[tablet_mac]{.material-symbols-outlined} 768]{.m-btn .m-btn-gray} **768 で表示** — タブレットの幅です。
- [[desktop_windows]{.material-symbols-outlined} 1200]{.m-btn .m-btn-gray} **1200 で表示** — PCの幅です。
- [[width_full]{.material-symbols-outlined} 全幅]{.m-btn .m-btn-gray} **画面幅の確認を終える（全幅）** — 確認を終えて、元の画面に戻ります。

バーの右側には、「文字をクリックすると、その場で直せます。⌥クリックで、その要素を下の本文で直せます（この表示のまま）」と案内が出ます。右端の [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} でも、確認を終えられます。

リンクやボタンの動作だけを試したいときは、右下の [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-gray} でクリックで編集をオフにできます。

ページ内のJavaScriptで表示が変わる部分は、[ページ編集のプレビュー](#page-preview)や公開サイトで確認してください。

### 別のページを開く・作る {#visual-pages}

Cloud版では、公開サイトのURLで書いたリンクからも、リンク先のビジュアル編集を開けます。下書きのHTMLページにも移動できます。画面幅の確認中にリンクを押すと、編集対象も切り替わります。 {.cloud}

右下の [[menu_open]{.material-symbols-outlined}]{.m-btn .m-btn-gray}（⌘/Ctrl+K）を押すと、ページの枠が開きます。枠は、上から次の順です。

- **ページを探す** — 入力欄です。ページのタイトルかIDを入力して、一覧を絞り込みます。右端の [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、枠を閉じます。
- [[add]{.material-symbols-outlined} 新規ページ]{.m-btn .m-btn-gray} **新しいページを作る** — ページIDとタイトルを入力する欄が開きます。
- [[content_copy]{.material-symbols-outlined} このページを複製]{.m-btn .m-btn-gray} **いま開いているページを複製** — ページIDとタイトルを入力する欄が開きます。
- **ページの一覧** — ページを選ぶと、そのページのビジュアル編集を開きます。保存していない入力があるときは、確認が出ます。各行の [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} で、その行のページを複製できます。いま開いているページを選ぶと、枠が閉じます。
- **すべてのページ（一覧画面）へ** — ページを選ぶ画面を開きます。

新しいページと複製の欄には、次の項目があります。

- **ページID（URLになります）** — 例：`about`、`news/2026`。空欄なら、自動で決まります。複製では、元のIDに「-copy」を付けた案が入ります。
- **タイトル** — 複製では、元のタイトルに「 のコピー」を付けた案が入ります。
- [[check]{.material-symbols-outlined} 作る]{.m-btn .m-btn-blue} **作る**（複製では「複製する」） — ページを作ります。作ったページは下書きです。
- [[close]{.material-symbols-outlined} キャンセル]{.m-btn .m-btn-gray} **キャンセル（Esc）** — 欄を閉じて、ページの一覧へ戻ります。

作ったページは下書きです。公開するときは、ページ編集で公開設定を変更します。

---

## サイト {#site}

サイト全体にかかわる設定と、運営・保全のための道具をまとめた画面です。管理メモ・バージョン・改ざんをチェックは常に表示し、ほかの項目は見出しを押して開きます。下の説明は、画面に並ぶ順です。

### どの項目をいつ使うか

| 用途 | 項目 | 使うとき |
|---|---|---|
| 日々の運営 | [管理メモ](#site-memo) | 引き継ぎや作業メモを残すとき |
| | [サイト共通設定](#site-settings) | 設置直後にサイト名などを決めるとき。計測タグを入れるとき |
| | [言語](#site-lang) | 管理画面の表示言語を変えるとき |
| | [CSVインポート](#csv-import) | 表のデータをページに入れたいとき |
| 公開 | [静的サイト生成](#site-ssg)（flat版・SQLite版） | 静的HTMLの書き出し先や形式を決めるとき |
| | [Cloudflare公開設定](#cloudflare-publish-settings)（Cloud版） | 設置直後に一度。公開サイトへ配信できるようにするとき |
| 更新と保全 | [バージョン（flat版・SQLite版）](#site-version-php)、[バージョン（Cloud版）](#site-version-cloud) | 新しい版が出たとき |
| | [データ管理](#site-data-mgmt) | 定期的なバックアップ、ほかのサイトからの移行、公開サイトのダウンロード |
| アカウントと接続 | [端末とユーザー](#site-users)（SQLite版・Cloud版） | 自分の端末を足す・人を招く・なくした端末を止めるとき |
| | [パスワードの変更](#site-password)（flat版） | パスワードを変えるとき |
| | [MCP APIキー](#site-mcp-key) | AIからサイトを操作させるとき |
| 点検 | [改ざんをチェック](#site-integrity) | 身に覚えのない変更がないか確かめたいとき |
| | [最近の操作記録](#site-audit) | いつ、だれが、何を変えたかを見たいとき |

### 管理メモ {#site-memo}

サイトの運営メモを残す欄です。サイト画面の上部に常に表示されます。公開ページや公開APIには含まれません。認証済みの管理MCPからは、AIも読めます。

#### メモ欄

引き継ぎ事項や作業メモを、自由に入力します。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-gray}

メモの内容を保存します。

### バージョン（flat版・SQLite版） {#site-version-php .flat .sqlite}

🍊mikanBoxのプログラムを新しい版に更新するための欄です。インストール済みの版と、GitHubで正式公開された最新の版を並べて表示します。更新で入れ替わるのはプログラムだけで、記事・設定・画像・データベース・静的出力には触れません。

#### 現在のバージョン・最新バージョン

最新バージョンを取得できないときは、「取得できませんでした」と表示します。

#### 更新する{.m-btn .m-btn-blue}

新しいバージョンがあるときだけ表示します。押して確認すると、プログラムファイルを入れ替え、画面を読み込み直して、開いていた位置に戻ります。

更新に失敗したときは、自動で更新前の状態に戻します。 {.detail}

自動で戻せなかったときはエラーが出て、サーバーの `update-backups/.pending-*` にバックアップが残ります。削除せず、サーバー管理者に復元を頼んでください。 {.detail}

#### 再インストール

最新版のときだけ表示します。同じ版のプログラムを入れ直します。更新が途中で止まって、プログラムが不完全になったときに使います。記事・設定・画像には触れません。

#### 再確認

最新バージョンの情報を取り直します。結果は右下に表示します。

#### GitHub

この版のリポジトリを、新しいタブで開きます。

#### 元に戻す

更新前の版が保存されているときだけ表示します。更新したあとに不具合が出たとき、直前の版へ戻します。

### バージョン（Cloud版） {#site-version-cloud .cloud}

管理画面（管理用Worker）を新しい版に更新するための欄です。現在の版と、GitHubで公開された最新のリリースを表示します。更新しても記事や画像は消えませんが、更新の前に、[データ管理](#site-data-mgmt)で書き出して保存してください。

#### 現在のバージョン・最新バージョン

現在の版と、最新のCloud版リリースを表示します。

#### 再確認

最新版の情報を取り直します。更新後は、画面を開き直して、現在の版を確かめます。

#### 更新する {#manual-update .m-btn .m-btn-blue}

v0.21.0以降は管理画面から更新できます。「Cloudflare公開設定」に保存したAPIトークンを使います。

1. 「サイト」で最新バージョンと変更内容を確認し、「更新する」を押します。
2. 進み具合が表示されます。完了まで画面を開いておきます。通信が中断したときは「更新を再開」を押します。
3. 完了したら画面を再読み込みし、現在のバージョン、記事、画像、設定を確かめます。

更新ボタンが表示されず、トークンの設定を求められたときは、「Cloudflare公開設定」に管理用Workerの編集権限（Workers Scripts Edit）があるトークンを保存してください。コードで追加した端末は、登録から3日経つと更新できます。

更新するのは管理画面と、その版で必要になるデータベースの変更です。公開ページや画像の変更を配信するときは、「静的サイトをビルド」を使います。

管理画面から更新できるのは、管理用Workerで標準の `DB` と `ASSETS` を使い、公開用Workerを分けた構成です。独自の接続設定があるサイトや、リリースに更新ファイルが付いていない場合は、下の予備の手順で更新します。ワンクリック更新だけなら、APIトークンに D1 Edit 権限を追加する必要はありません。 {.detail}

#### Cloud版リリース

GitHubの公開リリースを、新しいタブで開きます。

#### 古い版から初めて更新するとき

v0.21.0より前の版では、次のダウンロード手順か、設定済みの[GitHub Actions](#github-actions)で一度更新してください。その後は「サイト」の「更新する」が使えます。途中の版で準備が必要か、各リリースノートを確かめます。

#### ダウンロードして更新する

1. 最新版の[リリースページ](https://github.com/yoshihik0/mikanBox-cloud/releases)から **Source code (zip)** をダウンロードして展開します。
2. 展開したフォルダに、設置したときの `wrangler.production.json` を置きます。新規用のサンプル値ではなく、今のサイトの構成と同じものを使います。見つからないときは、Cloudflareで既存Workerの設定を確かめて作り直してから進めます。
3. AIに頼むときは、展開したフォルダでAIの新しい会話を開き、既存の管理画面のURLを伝えて、「このサイトを最新版に更新してください」と頼みます。Cloudflareへのログインを求められたら、既存サイトと同じアカウントで **Authorize** を押します。

#### コマンドで更新する {.cloud .detail}

展開したフォルダで次を順に実行します。`whoami` で、既存サイトと同じアカウントであることを確かめてから、残りの2つを実行します。

```sh
npm ci
npx wrangler login
npx wrangler whoami
npx wrangler d1 migrations apply DB --remote --config wrangler.production.json
npx wrangler deploy --config wrangler.production.json --keep-vars
```

更新では、`JWT_SECRET` や `SETUP_TOKEN` を作り直さないでください。登録済みの端末が使えなくなることがあります。公開側の `wrangler.public.json` も配備し直さないでください。公開サイトが最初の仮のページに置き換わります。終わったら管理画面を開き直し、版と記事・画像を確かめます。

#### GitHub Actionsで更新する {#github-actions .cloud .detail}

GitHub Actionsで更新する場合は、自分で管理するCloud版リポジトリに `.github/workflows/update-cloud.yml` を用意します。初回は、次の2つを自分のリポジトリの **Settings → Secrets and variables → Actions → New repository secret** で登録します。

| 名前 | 登録する値 |
|---|---|
| `CLOUDFLARE_API_TOKEN` | 対象アカウントに限定し、**Workers Scripts Edit** と **D1 Edit** を持つCloudflareのAPIトークン。独自ドメインやルートの構成によっては追加のゾーン権限が必要です |
| `MIKANBOX_UPDATE_CONFIG` | 現在のサイトの `wrangler.production.json` 全文。アカウント、管理用Worker、D1、ルートや独自ドメインの設定を確かめます |

APIトークンはGitHubのSecretsに直接登録します。設定用JSONにAPIトークン、`JWT_SECRET`、`SETUP_TOKEN` を入れないでください。

1. 自分のリポジトリで **Actions → Update mikanBox Cloud** を開きます。
2. **Run workflow** を押します。
3. 実行結果で **Success** を確認します。管理画面を開き直し、版と記事・画像を確かめます。

ワークフローは公式の最新公開リリースを取得します。公開プレリリースも対象です。管理用Workerの接続やルートを変更したら、GitHubに登録した設定も更新します。ワークフロー自体の修正が公開されたら、自分のリポジトリにも取り込みます。

#### 更新が中断したとき・前の版に戻すとき {#cloud-update-recovery .cloud .detail}

通信が切れた場合は、管理画面を開き直し、「更新を再開」が出たら押します。失敗した場合は、画面のメッセージかGitHub Actionsで失敗した工程を確認します。D1の更新だけが済んでいる場合があります。権限や設定を直してから再試行します。適用済みのマイグレーションは再適用されません。

管理画面が開けない場合も、Cloudflareダッシュボードにある管理用Workerのデプロイ履歴から、配信中の版を確認できます。前の版に戻す場合は、その版が現在のD1で動くことを確かめてから、デプロイ履歴で切り替えます。Workerの切り替えだけではD1の構造やデータは戻りません。前の版に合わせて[D1を復元](#cloud-d1-restore)する必要がある場合は、復元時点よりあとに保存した編集も取り消されます。

### 改ざんをチェック {#site-integrity}

操作記録にない変更が、ファイルや保存データに起きていないかを調べます。身に覚えのない表示の変化があったとき、[最近の操作記録](#site-audit)に色付きの記録が出たとき、定期的な点検に使います。

#### 改ざんをチェック{.m-btn .m-btn-gray}

押したときだけ動きます。結果はボタンの下に表示します。問題がなければ、確認した時刻を表示します。調べるものは次のとおりです（画像・音声・動画は調べません）。

- **公開ファイル** — 書き出し（Cloud版は公開）したときの記録と比べ、変わったもの、なくなったものを表示します。PHP版では、知らないファイルが増えたときも表示します。Cloud版では、公開サイトに増えた知らないファイルは見つけられません。
- **プログラム** — PHP版だけです。入っている版の公式リリース（GitHub）と比べます。GitHubに接続できないときは、更新・承認したときの記録と比べます。Cloud版のプログラムは、配備されたWorkerそのものなので、調べません。
- **保存したデータ** — ページ・コンポーネント・サイト設定を、操作記録にある最後の変更と比べます。記録を取り始める前に作ったページは、比べられないので対象外です。

最近30日間に「普段と違う操作」として色付けされた記録も、結果に並びます。

#### これは自分の変更（承認）{#site-integrity-accept .m-btn .m-btn-gray}

結果に、自分の変更が含まれることがあります（FTPでファイルを置いた、D1を直接直した、など）。押すと、いまの状態が新しい基準になり、同じ項目は次回から報告されません。色付けされた記録の色も外れます。承認は、操作記録の「重要な操作」に記録します。

承認前の状態に侵入が含まれていると、その状態を基準にしてしまいます。自分の変更だと確かめてから押してください。

### 静的サイト生成 (SSG) {#site-ssg .flat .sqlite}

公開設定が「公開(静的)」のページを、静的なHTMLファイルとして書き出すための設定です。PHPを実行しないので速く表示され、公開する場所にPHPファイルやデータを置かない構成にもできます。動的に公開するページと混在できます。ここでは書き出し先と形式を決め、普段の書き出しは、ページ一覧の[静的サイトをビルド](#static-build-button)でも行えます。

管理フォルダ・データフォルダ・`media`・`setup-token` の中には書き込みません。そこに当たるページは、書き出さずにエラーとして表示します。 {.detail}

#### 静的サイトの作成方法

- **サーバー内で静的ページを生成** — サーバー上で、そのまま公開する場所へ書き出します。基本はサイトルートです。
- **アップロード用フォルダを作成（ローカルで運用している場合）** — 手元のパソコンで作ったサイトを、ほかのサーバーへアップロードするための形です。HTMLと `media/` を、専用の出力先にまとめて書き出します。サイトルート、🍊mikanBox本体、既存のmediaフォルダと重なる出力先は指定できません。

#### 出力先ディレクトリ

書き出し先を、サイトルートからの相対パスで指定します。`blog` ならサイトルート直下の `blog/`、`../` なら1つ上です。空欄なら、サイトルート直下に書き出します。「アップロード用フォルダを作成」では、初期値が `export` になります。

#### 出力構成 / URL形式

HTMLファイルの形式を選びます。

- **ディレクトリ型 (folder/index.html)** — URLは `/ページID/` です。動的公開と同じURLなので、切り替えてもリンク切れが起こりません。
- **ファイル名型 (filename.html)** — URLは `/ページID.html` で、シンプルなファイル構成になります。

初期値は、「サーバー内で静的ページを生成」ではディレクトリ型、「アップロード用フォルダを作成」ではファイル名型です。影響するのはHTMLだけで、Markdown版とデータ形式は、どちらも `ID.拡張子` で書き出します。

#### リンク方式

アップロード用フォルダで、ページや画像のリンクを選びます。

- **相対リンク（持ち運び可能・推奨）** — 出力先からの相対リンクです。フォルダを移動しても、リンクが保たれます。
- **公開URL固定** — 指定した公開先URLに固定します。

#### 公開先ルートURL（SSG用）

公開先のルートを、`https://example.com/site` のように指定します。Cloudflare PagesやNetlifyのように、公開URLが決まっているときは、そのURLを入れます。

- **公開URL固定** — 必須です。ページ内のリンクと、`sitemap.xml`・`rss.xml` に使います。
- **相対リンク** — 任意です。`sitemap.xml` と `rss.xml` の生成にだけ使い、空欄ならこの2つは生成しません。

#### メディアも出力先の media/ に置く {#ssg-media-in-output .flat .sqlite}

公開する場所を管理画面と分けるときに使います（[同じサーバーで管理側に認証をかける例](#php-separated-publishing)）。「サーバー内で静的ページを生成」で、出力先ディレクトリと公開先ルートURLを入力し、この項目を選んでビルドすると、既存のメディアが出力先直下の `media/` に移り、以後のアップロードもそこに入ります。

たとえば、公開先を `/blog/` から `/pub/` に変えるときは、出力先を `../pub`、公開先ルートURLを `https://example.com/pub` にします。移動先の `media/` は、空か、存在しない必要があります。チェックを外して保存すると、メディアは元の場所に戻ります。「アップロード用フォルダを作成」では、この項目にかかわらず、現在のメディアを出力先に同梱します。 {.detail}

移動元と移動先は同じファイルシステムに置き、管理側のPHPに移動できる権限を与えます。管理画面のプレビューも、公開先ルートURLの `media/` から画像を読み込みます。 {.detail}

##### 保存先と配信用URLを個別に指定する {#php-media-location .flat .sqlite .detail}

メディア専用のフォルダや配信先を使う場合は、管理フォルダに `local-config.php` を作り、保存先と配信用URLを指定します。この設定が「メディアも出力先の media/ に置く」より優先されます。既存のファイルは、先に新しい保存先へ移します。

```php
<?php
define('MIKANBOX_MEDIA_DIR', '/srv/mikanbox/assets');
define('MIKANBOX_MEDIA_URL', 'https://example.com/media/');
```

サーバー内の静的生成では、指定した配信用URLを使います。アップロード用フォルダと「公開サイトの書き出し」のZIPには、保存先のファイルを同梱します。HTMLの `srcset` の画像や動画の `poster` も同梱したファイルを参照します。

#### [auto_awesome]{.material-symbols-outlined} 保存して静的サイトをビルド{.m-btn .m-btn-blue}

設定を保存して、公開設定が「公開(静的)」のすべてのページを、静的HTMLとして書き出します。画面は切り替わらず、生成したファイル数が通知で表示されます。ページを追加・変更したときは、ビルドし直すまで公開中のファイルは変わりません。

IDに `/` を含むページ（例：`news/2024`）は、出力先の中に実際のフォルダを作って書き出します。`sitemap.xml`・`rss.xml`・`podcast.xml` は、公開先ルートURLが設定されているときに生成します。404ページとデータ形式のページは含みません。詳しくは、[サイトマップ・RSS・API](#site-mapfeed)を参照してください。 {.detail}

### 言語 {#site-lang}

管理画面の表示言語を設定します。公開ページの言語には影響しません。

#### 言語の選択

「自動 (ブラウザ設定)」「日本語 (Japanese)」「英語 (English)」から選びます。「自動」は、ブラウザの言語設定に合わせます。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-blue}

言語の設定を保存します。

### CSVインポート {#csv-import}

表計算ソフトなどで作ったCSVを、ページに貼り付けられるデータの形（`DATAROW` ブロック）に変換します。商品の一覧やイベントの予定など、同じ形のデータをページで使いたいときに使います。

#### ファイルを選択

CSVファイルを選びます。1行目は、英数字のフィールド名にします。エンコーディング（UTF-8 / Shift-JIS）は、自動で判定します。

#### [content_copy]{.material-symbols-outlined} 変換してコピー{.m-btn .m-btn-gray}

各行を `{{DATAROW:連番}}` ブロックに変換して、クリップボードにコピーします。ページ編集の本文に貼り付けて、保存してください。

出力形式を「mikanBox DB」にして公開すると、このデータを `サイトURL/api/ページID.json` のAPIとして読み取れます（[mikanBox DB](#mikanbox-db)）。PHP版では、データ専用のページを下書きにしたまま、`{{POST_MD:ページID#行ID:フィールド}}` や `{{NAV_CARDS}}` で参照できます。Cloud版では、公開側から下書き・承認待ちのデータを参照できないので、公開してビルドしてください。

<a id="site-backup"></a>
### データ管理 {#site-data-mgmt}

サイトの内容を保存・移行するための欄です。次のようなときに使います。

- **定期的なバックアップ** — 「書き出し」でZIPを保存し、安全な場所に保管します。
- **ほかのサイトや版からの移行、バックアップからの復元** — 「読み込み」で、書き出したZIPやWordPressのデータを取り込みます。
- **公開サイトをそのまま持ち出す** — 「公開サイトの書き出し」で、公開中のページをZIPにします。

欄の一番上に、ページ・コンポーネント・メディアの数と、メディアの合計の大きさを表示します（SQLite版とCloud版は、ユーザーの数とデータベースの大きさも）。大きなサイトでは時間がかかるので、終わるまで画面を開いたままにしてください。

#### 保存容量 {.cloud}

ページ・履歴・設定はD1データベースに、メディアのファイルは公開サイトに保存します。無料枠の範囲で使えます。D1の使用量が450 MBを超えると、注意が出ます。メディアは、1ファイル10 MB・10,000ファイルまでです。公開サイトがメディアの唯一の置き場なので、「書き出し」で、JSONと画像を定期的に保存してください。

#### 書き出し {#data-export}

サイトの内容を、ZIPなどのファイルにして保存します。

##### 保存する内容

1つ選びます。迷ったら「JSONと画像」を選びます。

| 保存する内容 | 中身 |
|---|---|
| JSONと画像 | 設定・ページ・コンポーネントのJSONと、画像などのメディアを、1つのZIPにします。 |
| JSONだけ | 設定・ページ・コンポーネントのJSONだけのZIPです。 |
| 画像だけ | メディアファイルだけのZIPです。 |
| データベース（SQLite版・Cloud版） | SQLite版はデータベースのファイル、Cloud版はD1のSQLファイルです。ページの履歴も入ります。 |

##### ページの履歴を含める {.sqlite .cloud}

選ぶと、各ページの履歴（直近10件）も入ります。初めは選ばれていません。

##### 絞り込み状態にあわせる

ページ一覧をカテゴリ・出力形式・検索で絞り込んでいるときに選ぶと、そのページと、そこで使っているレイアウト・パーツ・メディアだけを書き出します。一部のページだけをほかのサイトへ渡すときに使います。リンク先のページや、ナビゲーションに並ぶページは含まれません。データベースの書き出しには影響しません。

部品の中に埋め込まれた部品も含みます。JavaScriptにメディアの配信用URLを文字列で直接書いている場合も、そのファイルを含みます。JavaScriptが実行時に組み立てるURLからは追加しません。

##### [download]{.material-symbols-outlined} ダウンロード{.m-btn .m-btn-gray}

選んだ内容をダウンロードします。下書きや管理メモなど、公開していない内容も入るので、安全に保管してください。ユーザー・端末・パスワード・MCP APIキー・サイトIDは、書き出しのZIPに含まれません。ZIPの形は3つの版で同じなので、ほかの版のサイトにも読み込めます。

Cloud版では、画像は公開サイトから読み出してまとめます。公開サイトのファイルが記録と違うときは止まるので、サイトをビルドしてから、もう一度保存してください。「データベース」は、設定、アカウント、メディアの情報を含むD1全体を1つのSQLファイルにします。画像のファイルは含みません。D1編集権限のトークンをCloudflare公開設定に登録すると選べます。 {.cloud}

#### 読み込み {#data-import}

書き出したファイルや、ほかのサイトのデータを、このサイトへ読み込みます。

##### ファイルを選ぶ

読み込むファイルを選びます。複数を一度に選べ、中身を見分けて、JSONと画像を読み込みます。読み込めないファイルは無視します。選べるのは、次のファイルです。

- 書き出しで保存したZIP（ほかの版や、古い版のZIPも可）
- `posts/`・`components/` を含むZIP
- 画像などのメディアファイル、またはそれだけを入れたZIP
- WordPressのXML（「ツール ＞ エクスポート」で書き出したもの）。投稿と固定ページを読み込み、公開中は公開、承認待ちは承認待ち、それ以外は下書きになります。

##### 同じIDのデータがあるとき

同じIDや名前のデータがあるときの動きを、3つから選びます。初めは「上書きしない」です。

- **上書きしない（ないものだけ足す）** — 今のサイトにないものだけを足します。設定は読み込みません。ほかのサイトから部品を取り込むときに向いています。
- **上書きする** — 同じIDや名前のものを、ファイルの内容で置き換えます。ファイルにないものは残ります。置き換える前のページは、履歴に残ります（SQLite版・Cloud版）。
- **丸ごと置き換える（ファイルにないものは消える）** — 今のサイトを、ファイルの内容にします。バックアップから元に戻すときに使います。実行前に確認します。

変わるのは、ファイルに入っている種類（ページ・コンポーネント・画像）だけです。設定は、「上書きする」「丸ごと置き換える」のときだけ読み込み、ユーザー・端末・サイトID・MCP APIキーは変わりません。使えないID（予約された名前など）は読み込まず、結果に表示します。

##### [upload]{.material-symbols-outlined} 読み込む{.m-btn .m-btn-gray}

選んだファイルを読み込みます。結果は右下に出て、画面は読み込み直しません。読み込んだページを公開ページに反映するには、サイトをビルドしてください。

Cloud版では、画像を読み込むと、そのまま公開サイトへ送ります（サイト全体をビルドします）。送れなかったときは、画像は読み込まれないので、同じファイルをもう一度読み込んでください。 {.cloud}

#### D1の指定時刻への復元 {#cloud-d1-restore .cloud}

操作を誤ったときなどに、データベース全体を、Cloudflareが保持している期間内（無料プランは直近7日）の指定時刻に戻します。使うには、Cloudflare公開設定に、D1編集権限のあるトークンを登録します。

戻るのはD1の中身（ページ・コンポーネント・設定・ユーザー・端末・メディアの情報）だけです。メディアのファイルと公開サイトは戻りません。戻したあとは、サイトをビルドして、ページと画像を確かめてください。その時刻より後に登録した端末は使えなくなるので、登録し直しが必要になることがあります。

##### 復元する時刻

戻したい日時を入力します。

##### [history]{.material-symbols-outlined} D1を指定時刻に戻す{.m-btn .m-btn-gray}

データベースを、入力した時刻に戻します。

#### 公開サイトの書き出し {#site-export}

現状の公開サイトを、ZIPでダウンロードします。ほかのサーバーへ静的サイトとして移すときや、公開中の状態を控えておくときに使います。PHP版では、動的に公開しているページもファイルにして入れます。Cloud版では、公開しているファイルを、公開サイトから読み出してまとめます。

##### 相対パスに変換する

ページ同士のリンクや画像の場所を、ファイルの位置から見た書き方にします。サイトのアドレスやフォルダが変わっても、リンクが切れません。選ばないときは、サイトのアドレスから始まる書き方になります。

##### [download]{.material-symbols-outlined} 公開サイトをダウンロード{.m-btn .m-btn-gray}

公開サイトをZIPにして、ダウンロードします。`.htaccess` などのサーバー用のファイルは入りません。

### サイト共通設定 {#site-settings}

サイト名や、ページに個別の設定がないときに使う既定の値、すべてのページに入れる計測タグを設定します。設置したら、まずサイト名を設定してください。

#### サイト名

サイトの名前です。`{{SITE_NAME}}` で出力できます。`{{FULL_TITLE}}` は「ページタイトル - サイト名」の形で、ブックマークや検索結果のタイトルにも使われます。

#### サイトID（AI識別用）

サイトごとに自動で作られる、変更できないIDです。複数のサイトをAIに接続したとき、AIが操作対象を取り違えないために使います。

#### サイトURL {.flat .sqlite}

AIが操作対象を確認するためのURLです。通常は空欄で、サイトのアドレスを自動で取得します。SSGの出力先・公開先とは別のものです。別のURLをAIに伝えたいときだけ、入力します。

#### サイトURL {.cloud}

公開先のURLを、読み取り専用で表示します。

#### サイト環境

本番、ステージング、開発、ローカル、未指定から選びます。AIは、サイトID・サイト名・URLと一緒に、この環境を確認できます。用途を伝える目印で、操作やアクセスを制限する機能はありません。

#### サイトの共通概要

ページに概要がないときの、ページ概要に使います。`{{SITE_DESCRIPTION}}` で出力できます。

#### 共通キーワード

ページにキーワードがないときの、キーワードに使います。

#### 共通OGP画像

ページにOGP画像がないときの、SNSでシェアされる画像に使います。`{{SITE_OGP_IMAGE}}` で出力できます。推奨サイズは 1200 × 630 px です。

#### ページ一覧の表示件数 (1ページあたり)

「ページ」画面の一覧を、何件ごとに分けるかを指定します（初期値は30件）。

#### メディア一覧の表示件数 (1ページあたり)

「メディア」画面の一覧を、何件ごとに分けるかを指定します（初期値は100件）。

#### カテゴリ登録（カンマ区切り） {.flat .sqlite}

絞り込みやページ編集で使う、カテゴリの候補を登録します。

#### 外部Markdownの読み込みを許可 {.flat .sqlite}

他のサイトのMarkdown文書を、`{{EXT_MD:url}}` で取得できるようにします。既定値と対象URLは、[他のサイトのMarkdown文書の埋め込み](#external-markdown)を参照してください。

#### 共通の読み込み {#common-loading}

アクセス解析などの計測タグを、すべてのページに読み込みます。ページごとに貼る必要はありません。Googleタグ・タグマネージャーを使うときは、「計測ID」を入力して保存します。静的に公開しているページには、ビルドし直すと反映されます。特定のページだけ止めるときは、ページ編集で、[「このページでは計測タグを読み込まない」](#page-edit-no-tracking)を選びます。

##### 計測ID

GoogleタグのID（`G-…`）か、タグマネージャーのコンテナID（`GTM-…`）を入力します。

##### コードを直接入力する（上級者向け）

独自の計測コードを使うときに開きます。「head用コード」と「body開始直後用コード（任意）」の入力欄が出て、入力したコードは、そのまま挿入されます。入力すると、計測IDは使われません。本文やコンポーネントに直接書いた計測タグは、ページ単位では外せません。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-blue}

サイト共通設定の内容を保存します。

### Cloudflare公開設定 {#cloudflare-publish-settings .cloud}

管理画面から公開サイトへページや画像を配信するための、Cloudflareとの接続を設定します。設置したあと、最初のビルドの前に一度設定します。これが済むまで、「静的サイトをビルド」で公開できません。

#### Cloudflare APIトークンの取得方法

開くと、Cloudflare側でトークンを発行する手順が表示されます。公開には、Workers Scripts Edit 権限が必要です。データ管理のD1の書き出しや指定時刻への復元も使うときは、D1編集権限も加えます。

#### CloudflareアカウントID

設置先のCloudflareアカウントIDが初めから入っています。そのまま使います。

#### Worker名

設置時に指定した管理用Workerの名前が初めから入っています。そのまま使います。公開用Workerを変更するときは、[公開用Workerの接続](#public-worker)を参照してください。

#### Cloudflare APIトークン

発行したAPIトークンを入力します。保存した値は暗号化され、再表示されません。Cloudflareでトークンを失効させたときや、サイトの `JWT_SECRET` を変更したときは、使えるトークンを再登録します。

#### 保存{.m-btn .m-btn-gray}

接続の設定を保存します。横に、「接続設定済み」か「未設定」が表示されます。

#### 公開用Workerの接続 {#public-worker .cloud .detail}

公開用Workerの名前と公開サイトのアドレスは、管理用の `wrangler.production.json` の `vars.CLOUDFLARE_PUBLIC_WORKER_NAME` と `vars.PUBLIC_SITE_URL` で設定します。管理用と公開用には別のWorkerを使います。設定を変えた場合は管理用Workerを配備し直します。[設定ファイルの値](#cloud-install-config)を参照してください。

公開用Workerが段階的デプロイ中なら、Cloudflareで1つのバージョンに配信をまとめてからビルドします。

### 端末とユーザー {#site-users .sqlite .cloud}

管理画面に入れる端末（ブラウザ）と、編集者のアカウントを管理します。自分の別のパソコンを使えるようにする、ほかの人を招く、なくした端末を止める、といったときに使います。編集履歴には編集者名が記録されます。パスワードは使いません。端末の管理（画面ロックなど）は、利用者の責任です。

#### ユーザーと端末

ユーザーごとに、その人の端末を表示します。端末の名前、登録日時、最終利用、期限が分かります。自分の端末には「（この端末）」と表示されます。ほかの人が新しい端末を登録したときは、次に管理画面を開いたときにお知らせが出ます。心当たりがなければ、止めてください。

##### 表示名

自分のユーザーだけにある入力欄です。編集履歴などに表示される名前を入力します。ユーザー名は変更できません。

##### 保存{.m-btn .m-btn-gray}

表示名を保存します。

##### 止める{.m-btn .m-btn-red}

端末ごとに表示します。なくした端末や、もう使わない端末に使います。その端末と、その端末が出した未使用のコードを止めます。止めた端末はすぐログアウトされ、再び使うには追加用のコードが必要です。自分の端末を止めると、ログアウトと同じです。

コードで追加した端末は、登録から3日間、ほかの端末を止めたり、ユーザーを削除したりできません。盗まれたコードで追加された端末に、持ち主が締め出されるのを防ぐためです。 {.detail}

Cloud版では、この3日間は、ほかのユーザーの変更とD1の指定時刻への復元もできません。 {.cloud .detail}

##### 削除{.m-btn .m-btn-red}

ほかのユーザーごとに表示します。そのユーザーを、端末と未使用のコードごと削除します。削除後は元に戻せません。ログイン中のユーザーと、最後の1人は削除できません。

#### [add]{.material-symbols-outlined} 端末を追加する

自分の別のブラウザやパソコンを使えるようにします。開くと、コードを出すボタンが表示されます。新しい端末で管理画面を開き、コードを入力してください。

##### [add]{.material-symbols-outlined} コードを出す{.m-btn .m-btn-blue}

追加用のコードを出します。コードは3時間有効で、1回だけ使えます。出した直後だけ表示され、あとから見ることはできません。

##### [add]{.material-symbols-outlined} 招待のコードを出す{.m-btn .m-btn-blue}

「ほかの人を招く」の欄にあります。一緒に編集する人を招くときに使います。招待のコードを出して、招待したい人に渡します。招待された人は、ログイン画面でコードと端末の名前を入力し、ユーザーIDと表示名を決めて登録します（IDは半角英数字・アンダースコア・ハイフン）。コードは6時間有効で、1回だけ使えます。

#### 未使用のコード

発行済みで、まだ使われていないコードの一覧です。

##### 取り消す{.m-btn .m-btn-gray}

コードごとに表示します。渡し間違えたときなどに、そのコードを無効にします。

#### 使わない端末が切れるまで

しばらく使っていない端末を、自動で使えなくするまでの期間を、30日・90日・180日から選びます。期間は、端末を使うたびに延びます。切れた端末は、コードで追加し直します。

##### 保存{.m-btn .m-btn-gray}

有効期間を保存します。

すべての端末を失ったときは、初期設定トークンで復旧します（[SQLite版の復旧](#device-recovery)）。 {.sqlite}

すべての端末を失ったときは、`SETUP_TOKEN` で復旧します（[Cloud版の復旧](#cloud-device-recovery)）。 {.cloud}

#### プロキシ配下の端末認証 {#device-auth-proxy .sqlite .detail}

端末の登録や復旧の入力に同じIPアドレスから10回失敗すると、そのIPからの試行が15分間止まります。プロキシ配下では、利用者全員が同じIPとして扱われることがあります。複数の利用者にまとめて制限がかかる場合は、Webサーバーの管理者に、信頼するプロキシを指定して利用者のIPを `REMOTE_ADDR` に渡す設定を依頼します。管理画面での設定は不要です。

### パスワードの変更 {#site-password .flat}

管理画面のログインパスワードを変更します。

#### 保存用サイトID

ブラウザがこのサイトのパスワードを見分けるための、読み取り専用の欄です。同じドメインに複数のサイトがあるとき、ブラウザに保存したパスワードを取り違えないようにします。

#### 現在のパスワード

現在のパスワードを入力します。

#### 新しいパスワード

新しいパスワードを入力します（7文字以上）。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-blue}

パスワードを変更して保存します。ログインしたままここで変更しても、MCP APIキーは変わりません。

パスワードを忘れたときは、FTPなどでサーバー上の `mikanData/settings.json` を開き、`password_hash` の値を空にします。次にアクセスすると、初回のパスワード設定画面が出ます（サーバーでは初期設定トークンも必要です）。ここで設定し直すと、MCP APIキーは無効になるので、「[MCP APIキー](#site-mcp-key)」で作り直してください。

### MCP APIキー {#site-mcp-key}

Claude CodeなどのAIエージェントに、MCPでこのサイトを操作させるための、APIキーを発行・管理します。AIにページの作成やデザインの修正を任せたいときに使います。

#### MCP を有効にする

チェックボックスです。外して保存すると、キーを残したまま、管理用MCPを停止します。再び有効にすると、同じキーで接続できます。使わない間は、停止しておくと安全です。公開ヘルプMCPは、停止中も使えます。

#### 保存{.m-btn .m-btn-gray}

「MCP を有効にする」の設定を保存します。

#### [add]{.material-symbols-outlined} APIキーを追加する {#mcp-key-issue}

開くと、キーを発行する欄が表示されます。キーには名前を付けて、最大20本まで発行できます。ツールやマシンごとに別のキーにすると、操作記録で「どのキーの操作か」が分かり、問題のあるキーだけを無効にできます。

##### キーの名前（AIツール・マシン名など）

そのキーを設定するAIツールやマシンの名前（例：「Claude Code・MacBook」）を入力します。

##### [auto_awesome]{.material-symbols-outlined} キーを発行{.m-btn .m-btn-blue}

新しいキーを発行して、下の「APIキー」の欄に表示します。ほかのキーには影響しません。

##### 接続URL

このサイトの管理用MCPのURLです。

###### [content_copy]{.material-symbols-outlined} コピー{.m-btn .m-btn-gray}

接続URLをクリップボードにコピーします。そのまま、クライアントの設定に貼り付けられます。

##### APIキー

発行した直後だけ表示します。あとから表示し直すことはできません。

###### [content_copy]{.material-symbols-outlined} コピー{.m-btn .m-btn-gray}

APIキーをクリップボードにコピーします。接続するツールの設定（`.mcp.json` など）に、すぐ貼り付けてください。なくしたときは、そのキーを無効にして、新しく発行します。

#### 発行済みAPIキー

発行済みのキーを、名前・発行日・最後に使った日時の順に表示します。最初に使うまでは、「まだ使われていません」と表示します。

##### 無効にする{.m-btn .m-btn-gray}

キーごとに表示します。使わなくなったキーや、漏れたおそれのあるキーを無効にします。そのキーを設定しているAIツールは、接続できなくなります。ほかのキーは、使えたままです。

#### 接続のしかた

AIアプリのRemote MCP（Streamable HTTP）の機能に、接続URLを登録し、APIキーをヘッダーに指定します。`Authorization: Bearer 発行したAPIキー`、または `X-API-Key: 発行したAPIキー` です。APIキーをURLに入れてはいけません。ClaudeのカスタムコネクタのOAuth欄にも、貼り付けないでください。認証が不要な公開ヘルプMCPは、URLだけで登録できます。

APIキーは、パスワードと同じように扱い、公開リポジトリや共有ファイルに書かないでください。キーを持つAIは、ページやコンポーネントのJavaScriptも書けるので、管理者と同じくらい信頼できるAIにだけ渡します。

AIは、接続すると、登録されているすべての [AI指示]{.type-badge .badge-ai} を読み、そのサイト固有の指示として使います。AI指示には、MCPキー、ログイン、サイト設定、サーバーやプログラム本体に関わる指示は含まれません。 {.detail}

#### APIキーが無効になるとき

- 一覧で、そのキーを無効にしたとき
- SQLite版で、初期設定トークンによる復旧をしたとき、または `reset-accounts.txt` で引き継いだとき {.sqlite}
- flat版で、`password_hash` を空にしてパスワードを設定し直したとき {.flat}
- Cloud版で、[端末の復旧](#cloud-device-recovery)をしたとき {.cloud}

通常のパスワード変更、端末の停止、ユーザーの削除では、キーは変わりません。 {.flat .sqlite}

#### 管理用MCPでできること {.detail}

ページ・コンポーネントの編集や削除、AI指示書の編集、プレビュー、メディアのアップロードに使えます。SQLite・Cloud版ではページの保存履歴も確認できます。

#### Cloud版での運用 {.cloud .detail}

ページは、ページIDで指定します。メディアのアップロードは、1ファイル10 MBまでです。同名のファイルがあるときは、名前の後ろに連番（`_1`、`_2`…）が付きます。静的HTMLを生成したあと、公開側へ配信するには[管理画面でビルド](#static-build-button)します。

#### 公開ヘルプをAIに読ませる {#cloud-public-help}

使い方や設定をAIに尋ねたいときは、AIアプリのRemote MCP（Streamable HTTP）に公開ヘルプのURLを登録します。URLだけで接続できます。AIは公開ヘルプを検索して読みます。

- flat・SQLite版の接続先は、mikanBoxを設置したサイトのURLの末尾に `/mcp` を付けたものです。 {.flat .sqlite}
- Cloud版の接続先は、管理用WorkerのURLの末尾に `/public-mcp` を付けたものです。ブラウザから読むには、同じWorkerの `/public-help.html?language=ja` を開きます。 {.cloud}

WebMCPに対応するブラウザでは、管理画面のブラウザ内エージェントからも公開ヘルプを検索できます。Cloud版の静的な公開ページでは、WebMCPは使えません。 {.cloud}

### 最近の操作記録 {#site-audit}

サイトに加えられた操作の履歴です。「いつ、だれが、何をしたか」を確かめ、身に覚えのない変更に気づくために使います。記録にない変更は、[改ざんをチェック](#site-integrity)で調べます。公開ページの表示には影響しません。

#### タブ

記録は、「重要な操作」「サイト編集」「ログイン失敗」の3つのタブに、新しい順に表示します。

- **重要な操作** — MCPの有効化・停止、MCPキーの発行・無効化、データの復元（「丸ごと置き換える」での読み込み）、[改ざんチェックの承認](#site-integrity-accept)、プログラムの更新（Cloud版は、更新後の最初のアクセスで記録）。PHP版では、以前の版への復元も。SQLite版・Cloud版では、端末の登録・停止、コードの発行、ユーザーの削除、復旧。
- **サイト編集** — ページ・コンポーネント・メディア・サイト設定の追加・変更・削除、データの読み込みです。管理画面・ビジュアル編集・MCPのどこから行っても記録します。サイト設定は、変わった項目の名前だけを記録し、値は記録しません。
- **ログイン失敗** — パスワードなどの認証に失敗したときです。

#### 色付けと「普段と違う操作」

次のどちらかの記録を、薄い色で目立たせます。設定はありません。

- その実行者とIPの範囲の組み合わせが、それより前の記録に一度も出てこないもの
- 実行者が空欄・不明のもの

たとえば、自宅と職場からしか操作していなかったのに、別の場所からの操作が現れると、その1件が色付きになります。自分の操作だと確かめたら、「改ざんをチェック」の結果で「これは自分の変更（承認）」を押すと、そこまでの色が外れます。

#### 1件の記録に入るもの {.detail}

日時、操作の種類、実行者（ログイン中のユーザー名。MCPは「MCP」）、対象（ページ・コンポーネント・メディアのID、設定の項目名）、伏せたIPです。パスワード、キー、トークン、本文、設定の値、完全なIPアドレスは、保存しません。

#### 残る件数とまとめ方 {.detail}

重要な操作は直近50件、ログイン失敗は直近50件、サイト編集は直近500件を残します。同じ人が同じものを10分以内に続けて保存したときは、1行にまとめます。内容が変わらず、更新日時だけが変わる保存は、記録しません。

#### 記録されないもの

- 公開ページの閲覧。書き込みの操作だけを記録します。
- 管理画面やMCPを通さずに、サーバー上のファイルを直接書き換えた変更（FTPでのアップロードなど）。見つけるのは、「改ざんをチェック」です。 {.flat .sqlite}
- CloudflareのダッシュボードやwranglerでD1を直接書き換えた変更。記録は残りますが、実行者が分からないので、空欄になります。 {.cloud}

---

## デザイン {#design-mgmt}

作成済みのコンポーネントを一覧で確認・管理する画面です。ページのレイアウト、共通パーツ、AIへの指示を、「コンポーネント」として管理します。

#### [add]{.material-symbols-outlined} 新規作成{.m-btn .m-btn-blue}

新しいコンポーネントを作成します。クリックするとデザイン編集エリアが開きます。

#### コンポーネントの絞り込み

コンポーネントの種類で一覧を絞り込みます。検索と組み合わせられます。

- すべて
- ページ
- パーツ
- AI指示
- 標準コンポーネント — IDが `_` で始まるものだけを表示します。

#### キーワード検索

コンポーネントのIDとメモを検索します。入力すると、その場で一覧が絞り込まれます。種類の絞り込みと組み合わせられます。

### コンポーネント一覧

一覧は [AI指示]{.type-badge .badge-ai}、[ページ]{.type-badge .badge-page}、[パーツ]{.type-badge .badge-part} の順に並びます。同じ種類のなかでは、`_global_head`・`_header`・`_footer` を先頭にして、ほかはID順です。

#### 操作（編集）

- [[edit]{.material-symbols-outlined} 編集]{.m-btn .m-btn-blue} — デザイン編集を開きます。IDをクリックしても開きます。

詳しくは[デザイン編集](#design-edit)を参照してください。

#### ID

コンポーネントの識別記号です。ほかのページやコンポーネントから呼び出すときの名前になります。`_` で始まるIDは標準コンポーネントの目印です。自分で作るコンポーネントには、通常は付けません。

詳しくはデザイン編集の[ID](#design-edit-id)を参照してください。

#### タイプ

[パーツ]{.type-badge .badge-part}・[ページ]{.type-badge .badge-page}・[AI指示]{.type-badge .badge-ai} のどれかを表示します。

詳しくはデザイン編集の[タイプ](#design-edit-type)を参照してください。

#### タグ名

`{{COMPONENT:header}}` のような埋め込みタグです。クリックするとコピーできます。ページ本文やほかのコンポーネントのHTMLに貼り付けると、そのコンポーネントを呼び出せます。ページコンポーネントは、ページ編集の「デザインコンポーネント」で選んで使います。

---

## デザイン編集 {#design-edit}

コンポーネントの内容や設定を編集する画面です。「新規作成」ボタンや、コンポーネントのID・編集ボタンをクリックすると表示されます。

SQLite版では、ほかの編集者が編集中のコンポーネントは編集が制限されます。 {.sqlite}

選んだ[タイプ](#design-edit-type)によって、表示する欄が変わります。タイプによって表示が変わる欄には、見出しの下に「タイプが○○のときだけ表示します。」と書いてあります。何も書いていない欄は、どのタイプでも表示します。

| 欄 | パーツ | ページ | AI指示 |
|---|:-:|:-:|:-:|
| ID・タイプ・メモ | ○ | ○ | ○ |
| HTML / Markdown | HTML | HTML | Markdown |
| CSS | ○ | ○ | ○（任意） |
| CSSをスコープ化する | ○ | ○ | － |
| JavaScript | ○ | ○ | － |
| Markdown記法とタグガイド | ○ | ○ | － |

#### ID {#design-edit-id}

コンポーネントを呼び出すときの名前です。ほかのページや部品からは、`{{COMPONENT:ID}}` で呼び出します。IDの先頭に `_` を付けると標準コンポーネントとして扱いますが、自分で作るものには通常付けません。

- PHP版では半角英数字・ハイフン・アンダースコアを使えます。区切りに `/`、各区切りの内側にドットを使えます。AI指示のIDは必要に応じて末尾に `.md` が付きます。 {.flat .sqlite}
- Cloud版では半角英数字・ハイフン・アンダースコアと末尾の `.md` を使えます。AI指示のIDに `.md` を付けるときは入力してください。 {.cloud}

#### タイプ {#design-edit-type}

コンポーネントの役割を3つから1つ選びます。選んだタイプで、下の欄の表示が変わります。

| タイプ | 用途 | 使い方 |
|---|---|---|
| [パーツ]{.type-badge .badge-part} | ヘッダーやナビゲーションなど | 本文や別の部品へ `{{COMPONENT:ID}}` を貼る |
| [ページ]{.type-badge .badge-page} | ページ全体のレイアウト | HTMLに `{{CONTENT}}` で本文の位置を書き、ページ編集の「デザインコンポーネント」で選ぶ |
| [AI指示]{.type-badge .badge-ai} | デザインや運用のルール | `DESIGN.md` などの指示書に内容を記入する |

#### メモ

管理画面で使うメモ欄です。認証済みの管理MCPからも参照できます。公開ページには表示されません。

#### HTML / Markdown

欄の見出しは、タイプに合わせて「HTML」または「Markdown」に変わります。

- パーツ・ページ — HTMLで、構造を書きます。別のコンポーネントを埋め込めます。ページでは、`{{CONTENT}}` で本文の位置を指定します。
- AI指示 — AIへの指示をMarkdownまたはHTMLで書きます。公開ページには表示されません。

使える独自タグは、画面下のガイドや[利用可能なタグ一覧](#tags)で確認できます。

#### CSS

部品のスタイルを書きます。AI指示では、ボタンなどのサンプルスタイルを書くための任意の欄になり、空欄でもかまいません。CSS欄では `{{SITE_URL}}` や `{{PAGE_URL:ID}}` などの基本情報タグを使えます。

ページコンポーネントでは、`{{HEAD_CSS}}` を使って集約したCSSの挿入位置を指定できます。

#### CSSをスコープ化する

タイプが「パーツ」「ページ」のときだけ表示します。

オンにすると、CSSはその部品の範囲だけに適用されます（初期値）。オフにするとサイト全体に適用できます。スコープをオンにしたまま、特定のルールを外にも適用するときは、セレクタを `:global(.hero_box)` のように囲みます。

#### JavaScript

タイプが「パーツ」「ページ」のときだけ表示します。コードがある場合は、欄が開いた状態で表示されます。

`<script>` タグを省いてコードを書きます。同じコンポーネントのコードは、1ページに1回だけ出力されます。ページ内に同じコンポーネントを複数置いても、初期実行は一度です。この欄の独自タグは展開されません。

#### [save]{.material-symbols-outlined} 保存{.m-btn .m-btn-blue}

コンポーネントの内容をすべて保存します。

静的に公開しているページへ変更を反映するには、[ビルド](#static-build-button)します。

#### [arrow_back]{.material-symbols-outlined} 一覧へ戻る{.m-btn .m-btn-gray}

コンポーネントの一覧へ戻ります。保存していない変更がある場合には、以下の選択肢が表示されます。

- [[save]{.material-symbols-outlined} 保存して閉じる]{.m-btn .m-btn-blue} — 保存して、一覧へ戻ります。
- [[delete]{.material-symbols-outlined} 破棄して閉じる]{.m-btn .m-btn-red} — 変更を捨てて、一覧へ戻ります。
- [キャンセル]{.m-btn .m-btn-gray} — そのまま編集を続けます。

#### [delete]{.material-symbols-outlined} 削除{.m-btn .m-btn-red}

既存のコンポーネントを編集しているときだけ表示します。

コンポーネントを完全に削除します。削除後は元に戻せません。

削除した部品を使っていたページも確認してください。

#### Markdown記法とタグガイド

タイプが「パーツ」「ページ」のときだけ表示します。

画面下部から[Markdown](#markdown)の簡易ガイドと[タグガイド](#tags)を開けます。

---

## メディア {#media-mgmt}

アップロード済みのファイルを一覧で確認・管理する画面です。画像やJS・CSSをアップロードして、ページで使うファイルを管理します。PHP版では、音声・動画もアップロードできます。

#### ファイルの種類の絞り込み

ファイルの種類で一覧を絞り込みます。検索やカテゴリの絞り込みと組み合わせられます。

- すべての種類
- 画像
- 音声 {.flat .sqlite}
- 動画 {.flat .sqlite}
- JS
- CSS

#### キーワード検索

ファイル名の一部を検索します。入力すると、その場で一覧が絞り込まれます。種類やカテゴリの絞り込みと組み合わせられます。

### アップロード

#### ファイルを選択

アップロードするファイルを選びます。メディアの欄にドラッグ＆ドロップしても追加できます。

#### [upload]{.material-symbols-outlined} アップロード{.m-btn .m-btn-blue}

選んだファイルを追加します。同じ名前のファイルがあるときは、名前の後ろに連番（`_1`、`_2`…）が付きます。

カテゴリで絞り込んでいる状態で追加すると、画像などのファイル名の先頭に、そのカテゴリ名が自動で付きます（例：`blog_photo.jpg`）。JS・CSSと、すでに「英数字_」で始まる名前のファイルには付きません。

##### Cloud版のアップロードと公開 {#cloud-media-delivery .cloud}

アップロードしたファイルは、公開サイトへ送られます。**下書きのページで使うファイルも、アップロードすると公開されます。** 公開先へ送れたファイルが、一覧に表示されます。

サイトをまだ公開したことがない場合は、初回のアップロード時にサイト全体をビルドします。完了まで画面を開いておいてください。

他のファイルの送り直しを求められたら、サイトをビルドしてからアップロードをやり直します。

#### アップロード設定

ボタンの下の「アップロード設定」を開くと、対応形式と加工の設定が表示されます。設定はブラウザに保存され、ドラッグ＆ドロップにも適用されます。

##### 対応形式

対応しているファイルの形式を表示します。画像はJPEG・PNG・GIF・WebP・SVG、コードはJS・CSSです。PHP版では、MP3・M4A・MP4も追加できます。JS・CSSは公開ページで動きます。提供元と内容を確認できるファイルだけを追加してください。

##### 長辺を（　）pxに制限する

画像の長辺を制限します。初めはオンで、2,000pxを超える画像を縮小します。数値は変えられます。GIF・SVGには適用されません。

##### WebPに変換する

JPEG・PNGをWebPにして保存します。

元のサイズや形式で配布したい画像は、加工の設定を外してから追加してください。

##### 詳細：アップロードと加工の上限 {.detail}

- JS・CSSは、UTF-8で1ファイル10 MBまでです。
- PHP版では、アップロード上限と使える画像加工は、サーバーの設定にも従います。 {.flat .sqlite}
- Cloud版は1ファイル10 MB、10,000ファイルまでです。画像の加工は、長辺16,000px以下かつ4,000万画素以下が対象です。 {.cloud}

#### カテゴリの絞り込み状態 {#media-category-state}

管理画面上部でカテゴリを選んでいるときだけ、一覧の上に表示します。カテゴリ名が付いたファイル（`blog_photo.jpg` など）に絞り込んでいる状態を示します。`g_` で始まるファイルはglobalで、JS・CSSとともに、どのカテゴリでも表示されます。

##### [visibility_off]{.material-symbols-outlined} カテゴリで絞り込みしない{.m-btn .m-btn-orange}

絞り込みを一時的に解除して、すべてのメディアを表示します。

##### [filter_list]{.material-symbols-outlined} カテゴリ絞り込みを適用する{.m-btn .m-btn-blue}

解除中だけ表示します。もう一度、カテゴリで絞り込みます。

### ファイル一覧

ファイルは、更新の新しい順にカードで表示されます。

#### サムネイル・ファイル名・形式

カードには、画像のサムネイル（画像以外はファイルの種類を示すアイコン）、ファイル名、形式と寸法が表示されます。ファイル名をクリックするとファイル名をコピーできます。

画像を使うときは、Markdownなら `![説明](ファイル名.png)`、HTMLなら `media/ファイル名` を指定します。

#### [delete]{.material-symbols-outlined} 削除{.m-btn .m-btn-red}

ファイルを取り除きます。削除後は元に戻せません。

Cloud版では、公開済みのファイルを削除すると、サイトをビルドして取り下げます。このとき、ほかの公開ページに保存した変更も反映されます。 {.cloud}

#### URLをコピー{.m-btn .m-btn-gray}

JS・CSSのファイルだけに表示します。ファイルのURLを、クリップボードにコピーします。

Cloud版でコピーするのは管理側のURLです。公開用のURLを人に伝えるときは、公開サイトのアドレスに `/media/ファイル名` を付けます。 {.cloud}

#### 読み込みタグをコピー{.m-btn .m-btn-gray}

JS・CSSのファイルだけに表示します。`<script>` または `<link>` のタグを、クリップボードにコピーします。ページ本文やコンポーネントのHTMLに貼り付けます。

#### リサイズ

JPEG・PNG・WebPのファイルだけに表示します。

「リサイズ」を開くと、幅(W)と高さ(H)の入力欄と、[[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} が表示されます。幅または高さを入力して、保存します。片方だけ入力すると、縦横比を保ちます。Cloud版では、縮小だけできます。リサイズした画像は公開先へ送信されます。 {.cloud}

#### 名前変更

「名前変更」を開くと、ファイル名の入力欄と、[[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} が表示されます。拡張子を変えずに、新しい名前を指定します。「リンクを更新」を選ぶと、ページや部品の参照も直します。

静的ページの参照を切り替えるには、変更後に[ビルド](#static-build-button)します。Cloud版では、名前変更で使わなくなった旧URLは、次のビルドで取り除かれます。 {.cloud}

##### 詳細：名前を変えるときの参照の更新 {.detail}

「リンクを更新」は、本文、CSS、JavaScript、コンポーネント、OGP画像で使うファイル名が対象です。外部からのリンクとJavaScriptで組み立てたURLは、個別に確認してください。

SQLite版とCloud版では、参照を更新するページが編集中の場合、操作を中止します。編集を終えてからやり直してください。 {.sqlite .cloud}

#### WebPに変換{.m-btn .m-btn-blue}

JPEG・PNGのファイルだけに表示します。WebPに変換し、拡張子が変わるため、ページや部品の参照も更新します。変換先に同名のWebPがあるときは、変換を中止します。変換後に静的ページの参照を切り替えるには、ビルドします。

Cloud版では、変換後のWebPは公開先へ送信され、使わなくなった旧URLは次のビルドで取り除かれます。 {.cloud}

### 公開サイトへの反映のタイミング {#cloud-media-timing .cloud}

Cloud版では、メディアの操作によって、公開サイトに反映されるタイミングが違います。

- **公開済みのファイルの削除** — その場で、サイト全体をビルドして、取り下げます。まだビルドしていない、ほかのページの保存済みの変更も、一緒に反映されます。
- **アップロード** — 公開済みのサイトでは、そのファイルだけを、すぐ公開サイトへ送ります。ほかのページは変わりません。
- **リサイズ** — 縮小した画像を、すぐ公開サイトへ送り、同じ名前の画像を置き換えます。ほかのページは変わりません。
- **名前変更・WebPに変換** — ページや部品の参照は、その場で更新します。ただし、公開ページへの反映と、使わなくなった旧ファイルの取り下げは、次のビルドのときです。WebPに変換した画像は、すぐ公開サイトへ送ります。

### ページ送り

一覧が複数ページにわたるときは、一覧の下にページ番号と件数が表示されます。[前へ]{.m-btn .m-btn-gray}・[次へ]{.m-btn .m-btn-gray} で移動します。表示件数は<a href="#site-settings">サイト共通設定</a>で変更できます。

---

## Markdown {#markdown}

ページの本文では、見出しやリストなどのMarkdown記法を使うことができます。

Markdown記法については、ページ編集画面の下部に使用できる記法についてのガイドがあります。

### MarkdownとHTML

MarkdownとHTMLは混在できます。行頭（先頭の空白を除く）に以下のタグがある行はHTMLとして扱われ、その行のMarkdown記法は変換されません。

`a` `address` `article` `aside` `blockquote` `body` `button` `canvas` `caption` `col` `colgroup` `dd` `details` `dialog` `div` `dl` `dt` `fieldset` `figcaption` `figure` `footer` `form` `h1`〜`h6` `head` `header` `hr` `html` `iframe` `legend` `li` `link` `main` `menu` `meta` `nav` `noscript` `ol` `optgroup` `option` `p` `pre` `script` `section` `select` `source` `span` `style` `summary` `table` `tbody` `td` `tfoot` `th` `thead` `title` `tr` `ul` `video` `audio`

開始タグと終了タグの両方が対象です。行頭の `<!DOCTYPE html>` や、`<!-- コメント -->` のように `<!--` の直後に空白を入れたコメントも対象です。HTMLブロックが複数行にわたる場合は、空行または終了タグだけの行まで、太字などの記法は変換されません。コードブロックとテーブルは、HTMLブロックより先に処理されます。

複雑なHTMLレイアウトを貼り付けるときは、ページ編集画面の「Raw HTML」をオンにすると、本文をMarkdown変換せずHTMLとして扱います。🍊mikanBoxの独自タグは引き続き展開されます。

### 段落

**空行で区切った文章**は段落になり、`<p>内容</p>` の形で出力されます。段落のなかでは、太字などのMarkdown記法や文中のHTMLタグを使えます。段落内の改行は `<br>` になります。HTMLタグの直前・直後では `<br>` は挿入されません。

### 見出し

行頭で `## 見出し` のように使うと見出しとして扱われ、 `<h2>見出し</h2>` の形で出力されます。**`#`記号のあとには半角または全角のスペースを入れる必要があります**。

`#` は1〜6個です。通常、`#` で示される `<h1>` タグはページタイトルに使用されるので、本文内では、 `## 見出し` `### 小見出し` を利用するのが良いでしょう。

Markdown:`## 見出し`
HTML:`<h2>見出し</h2>`

### 箇条書き

箇条書きには `-`、`*`、`+` を使用します。箇条書き・番号付きリストとも、**記号のあとに半角または全角のスペースを入れる必要があります**。

Markdown:`- リスト項目`
HTML:`<ul><li>リスト項目</li></ul>`

Markdown:`* リスト項目`
HTML:`<ul><li>リスト項目</li></ul>`

Markdown:`1. 番号付きリスト項目`
HTML:`<ol><li>番号付きリスト項目</li></ol>`

### 引用

引用は `>`で指定します。 引用の `>` のあとのスペースは省略できます。連続する引用行はひとつの引用にまとまります。

Markdown:`> 引用`
HTML:`<blockquote>引用</blockquote>`

### 区切り線

区切り線は、1行に `-`、`*`、`_` のいずれかを空白なしで3つ以上並べて指定します。

Markdown: `---`、`***` または `___`
HTML: `<hr>`


### コードブロック

コードは、バッククォート（&#96;）またはチルダ（`~`）を3つ以上並べた行で囲みます。行頭には半角スペースを3つまで入れられます。閉じる行には、開く行と同じ文字を、同じ数以上並べます。囲まれた中は、Markdown、HTML、`{{タグ}}` のどれも変換されず、書いたとおりに表示されます。HTMLでは `<pre><code>…</code></pre>` になります。

- 開く行の後ろに、半角英数字・ハイフン・アンダースコアで言語名を1語書くと（例：`php`、`json`、`html`）、`<code class="language-php">` のように、`language-` で始まるクラスが付きます。CSSやハイライトのライブラリで、言語ごとに見た目を変えるときに使えます。
- コードの中に、バッククォート3つのコードブロックを書きたいとき（Markdownの書き方を説明するページなど）は、外側のフェンスを4つ以上のバッククォートにするか、チルダにします。中のフェンスより長ければ、中のフェンスでは閉じません。
- 閉じる行を書き忘れると、そこから最後までが、コードとして扱われます。

### 文中で使用できるインライン要素

Markdown:`**太字**`
HTML:`<strong>太字</strong>`

Markdown:`*斜体*`
HTML:`<em>斜体</em>`

Markdown:`~~取り消し~~`
HTML:`<del>取り消し</del>`

Markdown:&#96;コード&#96;
HTML:`<code>コード</code>`

インラインコードは文中でバッククォート1つずつで囲みます。たとえば、変数名を `name` のように表示できます。

バッククォート（&#96;）そのものを文字として見せたいときは、次の入力例をそのまま本文に書きます。

| 用途 | 本文に入力する内容 | 表示 |
|---|---|---|
| 普通の文に1文字だけ表示する | `&#96;` | &#96; |
| コードの中に表示する | `<code>&#96;name&#96;</code>` | <code>&#96;name&#96;</code> |

バックスラッシュでエスケープする書き方や、バッククォートを2つ並べて囲む書き方には対応していません。

### リンク

リンクは `[リンクしたい文字](URL)` の形で指定できます。 `<a href="URL">リンクしたい文字</a>` の形で出力されます。

Markdown:`[リンクしたい文字](URL)`
HTML:`<a href="URL">リンクしたい文字</a>`

相対URL、ページ内リンク（`#見出しID`）、`https:`・`http:`・`mailto:`・`tel:` を使えます。その他の明示的なスキームには `#` を出力します。

### メディアの利用

画像を表示する際には、 `![画像の説明](filename)` でページに埋め込むことができます。`[ ]` のなかには、画像の説明を入れ、 `<img src="画像のURL" alt="画像の説明">` の形で出力されます。メディア管理でアップロードした画像は、ファイル名を指定するだけで利用できます。また、独自タグの `{{IMAGE:ファイル名}}` の形でも同様に利用できます。

Markdown:`![画像の説明](filename)`
HTML:`<img src="画像のURL" alt="画像の説明">`

#### 自動的に付与される属性

メディアにある画像をMarkdownの画像記法や `{{IMAGE:ファイル名}}` で挿入すると、画像の寸法を取得できた場合に、出力される `<img>` タグに属性が自動で付きます。

Cloud版では静的ビルド時に付きます。 {.cloud}

- `width` / `height` — 画像本来の縦横のピクセル数。ブラウザが読み込み前に表示場所を確保し、レイアウトのずれを抑えます。
- `fetchpriority="high"` — 大きな画像のうち、処理順で最初の1枚を優先して読み込みます。
- `loading="lazy" decoding="async"` — 残りの大きな画像は、画面に近づくまで読み込みを遅らせます。

縦横どちらも 200px 未満の小さな画像（ロゴ、アイコン、区切り線など）には `width` / `height` だけが付き、読み込みの指示は付きません。数KBの画像を遅延させても効果がなく、かえって表示が遅れるためです。

SVG、外部URLの画像、メディアにない画像には、これらの属性は付きません。HTMLで直接書いた `<img>` にも自動では付きません。

#### 音声・動画 {#markdown-audio-video}

PHP版は、アップロード済みのファイルを `{{AUDIO:ファイル名}}` または `{{VIDEO:ファイル名}}` で埋め込めます。 {.flat .sqlite}

Cloud版で外部配信の音声・動画を埋め込むには、HTMLの `<audio src="https://example.com/audio.mp3" controls></audio>` または `<video src="https://example.com/video.mp4" controls></video>` を使います。 {.cloud}

### テーブルの表示

テーブルは行頭を `|` で始め、各セルを `|` で区切ります。見出しの次に `|---|` のような区切り行、その下にデータを1行以上書きます。1行目が見出し行 `<th>` 、3行目以降がデータ行 `<td>` となります。`|:---|:---:|---:|` のようにコロンを入れることで、表示位置を指定できます。

`| 指定なし | 左寄せ | 中央 | 右寄せ |`
`|---|:---|:---:|---:|`
`| A | B | C | D |`

| 指定なし | 左寄せ | 中央 | 右寄せ |
|---|:---|:---:|---:|
| A | B | C | D |

### クラス・IDの指定（独自拡張）

Markdownの行末に `{.className}` または `{#idName}` を追加すると、見出しや段落などに `class` や `id` を指定できます。両方を付ける場合は、`{.className #idName}` のように1組の波括弧にまとめます。複数行の段落では、段落の最後に書きます。

Markdown:`## 見出し{.highlight}`
HTML:`<h2 class="highlight">見出し</h2>`

Markdown:`段落テキスト{.note #intro}`
HTML:`<p id="intro" class="note">段落テキスト</p>`

文中の一部に指定するときは `[text]{.className}` と書くと、該当する文字列を `<span>` で囲みます。複数のクラスと1つのIDを組み合わせられます。

Markdown:ここは`[赤いテキスト]{.red}`です
HTML:ここは`<span class="red">赤いテキスト</span>`です

Markdown:ここを`[**太字**で強調]{.highlight .bold}`します
HTML:ここを`<span class="highlight bold"><strong>太字</strong>で強調</span>`します

Markdown:ここが`[アンカー]{#my-id .note}`です
HTML:ここが`<span id="my-id" class="note">アンカー</span>`です

#### 入れ子のスパン（アイコン付きボタンなど）（独自拡張）

`[text]{.className}` は入れ子にできます。内側の `[...]{...}` から順に処理され、外側のスパンがそれを包みます。三重以上の入れ子も可能です。

Markdown:`[[save]{.material-symbols-outlined}保存]{.m-btn .m-btn-blue}`
HTML:`<span class="m-btn m-btn-blue"><span class="material-symbols-outlined">save</span>保存</span>`

Material Symbolsなどのアイコンフォントのアイコンを、ボタン風スパンやバッジの中に埋め込む場合に便利です。なお、`[...]{...}` の内容に、対応する `{...}` を持たない裸の `[` を含めることはできません（入れ子のブラケットはスパンとして解釈されるため）。

これらの記法は🍊mikanBox独自のMarkdown拡張です。他のMarkdownレンダラー（GitHub等）では動作しません。

---

## コンポーネント（独自タグ） {#components}

### タグの利用

ページ本文やコンポーネントのHTMLで独自タグを使えます。CSS欄では `{{TITLE}}`・`{{SITE_URL}}`・`{{PAGE_URL:ID}}` などの基本情報タグを展開します。

ページやコンポーネントの**JavaScript専用欄では、独自タグは展開されません**。JavaScriptで値を使いたいときは、HTMLに `data-site-url="{{SITE_URL}}"` のように書き、JavaScriptからその要素の `dataset.siteUrl` を読みます。

出力形式が「JavaScript」のページでは、編集画面で「タグを展開する」をオンにすると、本文のタグを展開できます。使えるタグは、画面下部のガイドと[利用可能なタグ一覧](#tags)を参照してください。

### コンポーネントの利用

デザイン管理で作成したコンポーネントは、 `{{COMPONENT:ID}}` の形で、ページや他のコンポーネントに埋め込むことができます。

### 公開ページのAI質問ボックス {#cloud-ai-question}

訪問者が、FAQや利用案内などの公開資料について、外部AIに質問できる欄です。ページの本文に `{{COMPONENT:_ai_question}}` と書いて配置します。初期設定では、配置した公開ページを参照します。

#### 参照資料と聞き方を設定する

1. FAQなどの資料を公開します。公開ページの[出力形式](#page-edit-output-format)で「HTML + Markdown」または「Markdown」を選ぶと、公開した `.md` のURLも使えます。
2. デザイン管理で `_ai_question` を開き、HTMLの `data-sources=""` に公開URLを指定します。複数のURLは `|` で区切ります。
3. AIに渡す文章を変えるには、HTMLの末尾の `template` の中を編集します。`%QUESTION%` は訪問者の質問に、`%SOURCE_URLS%` は参照URLの一覧に置き換わります。`%SOURCE_URL%` は先頭のURL、`%PAGE_URL%` は配置先の公開ページのURLを示します。
4. 質問欄の文言や配置はHTMLで、色や余白などはCSSで調整します。静的ページの場合は、ビルドして公開ページを確認します。

Cloud版の旧標準部品を編集せずに使っていた場合は、更新時に新しい標準部品に切り替わります。サイトで編集した部品は引き続き使えます。HTMLが `{{AI_QUESTION}}` の1行の場合は、手軽に資料を指定できる次のタグが使えます。HTML・CSS・プロンプトを編集する場合は、配布ファイルの `mikanBox/import/ja/components/_ai_question.json` またはCloud版の初期コンテンツにある部品を使います。

```text
{{AI_QUESTION:sources=https://example.com/faq.md | https://example.com/guide/}}
```

訪問者は質問を入力し、表示されたプロンプトをコピーしてChatGPTやClaudeに貼り付けます。資料を読めるかどうかは外部AIによります。mikanBoxの公開ヘルプの節を指定するには、`{{AI_QUESTION:セクションID}}` を使います。

### 画像・音声・映像

画像は `{{IMAGE:ファイル名}}` で埋め込めます。

PHP版では、アップロードした音声や動画を `{{AUDIO:ファイル名}}` や `{{VIDEO:ファイル名}}` で埋め込めます。 {.flat .sqlite}

Cloud版のメディアには音声・動画をアップロードできません。外部配信のファイルを埋め込む場合は、HTMLの `<audio>` や `<video>` に外部URLを指定します。 {.cloud}

### ナビゲーション {#navigation}

リンクの一覧は `{{NAV_LINKS:カテゴリ}}` を使用すると、リスト形式 `<li>` で出力されます。

画像付きのカードなどで出力したい場合は、 `{{NAV_CARDS:カテゴリ:コンポーネントID}}` で出力できます。カードのデザインは、コンポーネントで定義できます。コンポーネントIDを省略すると、標準的なデザイン（`_nav_card`コンポーネント）で出力されます。

リストやカードの順番はページの「表示順」で指定できます。表示順が0より小さいページと、[データ形式](#data-formats)のページは表示されません。

カテゴリを `all` にすると全カテゴリ、カテゴリ引数を空欄にするとカテゴリのないページを対象にします。ページのカテゴリはひとつのページに複数設定できるので、カテゴリを適切に設定することで、多様な形でのナビゲーションを設定することができます。

#### リンク先一覧のオプション

位置の引数（カテゴリ、カードのコンポーネントID）のあとに、`名前=値` を `:` でつないで指定できます。

```
{{NAV_LINKS:news:limit=5}}
{{NAV_CARDS:news:_nav_card:limit=5:sort=new}}
{{NAV_CARDS:news:sort=old}}
```

|オプション|意味|
|---|---|
|`sort=new`|更新日時の新しい順（同じ日時なら表示順）|
|`sort=old`|更新日時の古い順（同じ日時なら表示順）|
|`limit=N`|先頭からN件まで（`@same` ではカテゴリごと）|
|`sep=文字列`|項目と項目のあいだに入れる区切り（主にデータ形式でJSONの配列を作るため）。値は次の `:` か `}}` までをそのまま使い、`\n`（改行）と `\t`（タブ）だけを特別に解釈します|

一覧の `sort=new`・`sort=old` は、ページに保存されている[更新日時](#page-editor-date)を使います。オフセットや自動のページャーはありません。全件の一覧ページを作ってそこへリンクするか、[目録データとJavaScript](#catalog-pager)で作ります。

#### 関連ページ `@same`

表示中のページと同じカテゴリのページを自動で並べます。リンク一覧は `{{NAV_LINKS:@same}}`、カード一覧は `{{NAV_CARDS:@same}}` を使います。表示中のページ自身は一覧に入りません。

たとえば、表示中のページに `coffee` と `tokyo` が付いていると、`coffee` の一覧と `tokyo` の一覧ができます。両方のカテゴリが付いたページは、両方の一覧に出ます。カテゴリが1つならカテゴリ名を付けずに一覧を出し、カテゴリがなければ何も表示しません。`_nav` のように `_` で始まるカテゴリは、関連ページを探すときには使いません。

既定では「表示順」で並びます。たとえば `{{NAV_LINKS:@same:sort=new:limit=3}}` と書くと、カテゴリごとに更新日時の新しい順で3件まで表示します。

カテゴリ別の見た目をCSSで変えるときは、グループに `.nav-group`、カテゴリ名に `.nav-group-label` を指定します。Markdown版ではカテゴリ別の一覧が入れ子のリストになります。

#### カテゴリの表示 `{{CATEGORY}}`

`{{CATEGORY}}` は、ページのカテゴリを「_」で始まるものを除いて書いた順に出します。HTMLでは1つずつ `<span class="category">coffee</span>` で囲み、Markdown版とデータ形式ではカンマ区切り（`coffee, tokyo`）です。`{{CATEGORY:all}}` は「_」で始まるものも含めます。カード用コンポーネントの中では、そのカードのページのカテゴリを出します。

### 他のページの埋め込み

他のページの内容は、`{{POST_MD:ID}}` の形で埋め込めます。本文の一部を別ページにすると、お知らせなどを個別に編集できます。

PHP版では、埋め込み元を下書きにして単独のページとしては公開せずに使えます。 {.flat .sqlite}

Cloud版の公開ページでは、下書き・承認待ちを `POST_MD` で読み込めません。`NAV_LINKS`・`NAV_CARDS`・`@same`、フィード、APIにも出ません。 {.cloud}

### 外部Markdownの埋め込み {#external-markdown}

GitHubなど、他のサイトに設置したMarkdown文書を `{{EXT_MD:url}}` の形で埋め込むことができます。GitHub上で直接編集できるのはもちろん、AIエージェントにGitHub上のファイルを書き換えてもらうだけでサイトを更新できる「AIフレンドリー」な運用が可能です。

**PHP版の設定：** サイト設定の「外部Markdownの読み込みを許可」で切り替えます。新規サイトはOFFです。設定のない既存サイトは、保存済みのページ・部品に `EXT_MD` があれば利用を引き継ぎます。OFFでは表示・プレビュー・ビルドのいずれも外部通信しません。ONではPHPのcURL拡張が必要です。HTTP/HTTPSの公開アドレスだけを取得し、内部アドレス・リダイレクトは拒否します。文書の直接URLを使ってください。読み込める文書は約1 MBまでで、通信時間は最大5秒です。読み込めない場合、その部分は表示されません。設定をOFFにしても、生成済みHTMLは再ビルドするまで変わりません。 {.flat .sqlite}

Cloud版では `EXT_MD` の位置に内容を表示しません。HTMLには `<!-- EXT_MD is not available in the Cloud edition -->` というコメントだけが残り、Markdownやデータ形式の出力形式では何も表示しません。 {.cloud}

### ページコンポーネント

ページ全体をレイアウトするコンポーネントは、本文を出力する `{{CONTENT}}` を挿入してください。CSSの挿入位置を指定する場合は `{{HEAD_CSS}}` を配置します。省略時は `</head>` 直前（閉じタグがなければHTML先頭）へ自動挿入され、複数ある場合は最初の1つにだけ出力され、残りは取り除かれます。基本的な構成は、初期コンポーネントの `_layout` を参照してください。

---

## 利用可能なタグ一覧 {#tags}

### 全版で使えるタグ

|タグ|説明|
|---|---|
|`{{ TITLE }}`|ページタイトル|
|`{{ FULL_TITLE }}`|ページタイトル - サイト名|
|`{{ UPDATE_DATE }}`|更新日時の日付（YYYY-MM-DD）|
|`{{ UPDATE_DATE:JP }}`|更新日時の日付（YYYY年MM月DD日）|
|`{{ UPDATE_DATE:SLASH }}`|更新日時の日付（YYYY/MM/DD）|
|`{{ IS_NEW:N }}`|N日以内の更新なら「new」、それ以外は空欄|
|`{{ DESCRIPTION }}`|ページ概要（空欄ならサイト共通説明）|
|`{{ KEYWORDS }}`|ページのキーワード|
|`{{ OGP_IMAGE }}`|ページサムネール・OGP画像URL|
|`{{ PAGE_URL }}`|ページURL|
|`{{ PAGE_URL:ID }}`|指定したページのURL（出力形式の拡張子付き。データ形式のページは `?v=` 付き）。[詳しく](#page-url-id)|
|`{{ CATEGORY }}`|ページのカテゴリ（「_」で始まるものを除く）|
|`{{ CATEGORY:all }}`|ページのすべてのカテゴリ|
|`{{ SITE_URL }}`|サイトURL。Markdownのリンク先にも使えます（例：`[詳細]({{SITE_URL}}/about)`）|
|`{{ SITE_NAME }}`|サイトのタイトル|
|`{{ SITE_DESCRIPTION }}`|サイトの共通説明|
|`{{SITE_KEYWORDS}}`|サイトの共通キーワード|
|`{{BASE_URL}}`|`{{SITE_URL}}` と同じサイトURL（互換用）|
|`{{PAGE_TITLE}}`|そのページのタイトル欄の値|
|`{{PAGE_DESCRIPTION}}`|そのページの概要欄の値|
|`{{PAGE_KEYWORDS}}`|そのページのキーワード欄の値|
|`{{PAGE_OGP_IMAGE}}`|そのページのOGP画像欄に指定した画像のURL|
|`{{ SITE_OGP_IMAGE }}`|サイトの共通OGP画像URL|
|`{{ COMPONENT:ID }}`|コンポーネントを埋め込む|
|`{{IS_ACTIVE}}`|カード部品で、表示中のページのカードなら `active`、それ以外は空欄|
|`{{LINK}}`|カード部品で、対象ページのURL（`{{PAGE_URL}}` の互換用）|
|`{{ IMAGE:ファイル名 }}`|静止画を表示|
|`{{ AUDIO:ファイル名 }}`|PHP版で、アップロードした音声を埋め込む|
|`{{ VIDEO:ファイル名 }}`|PHP版で、アップロードした動画を埋め込む|
|`{{ AI_QUESTION }}`|現在の公開ページを参照する[AI質問ボックス](#cloud-ai-question)を表示|
|`{{ AI_QUESTION:sources=公開URL }}`|指定した公開資料を参照するAI質問ボックスを表示|
|`{{ AI_QUESTION:セクションID }}`|mikanBoxの公開ヘルプの節を参照先に指定してAI質問ボックスを表示|
|`{{ NAV_LINKS:カテゴリ }}`|指定したカテゴリのページをリンクとして表示|
|`{{ NAV_LINKS:all }}`|すべてのページをリンクとして表示|
|`{{NAV_LINKS}}`|カテゴリのないページをリンクとして表示|
|`{{ NAV_CARDS:カテゴリ:コンポーネントID（省略可）}}`|指定したカテゴリのページをカード形式で表示|
|`{{ NAV_CARDS:all:コンポーネントID（省略可）}}`|すべてのページをカード形式で表示|
|`{{NAV_CARDS}}` または `{{NAV_CARDS::コンポーネントID}}`|カテゴリのないページをカード形式で表示|
|`{{ NAV_LINKS:カテゴリ:sort=new:limit=N }}`|オプション付きの一覧。`sort=new`・`sort=old`・`limit=N`・`sep=文字列`（NAV_CARDSも同じ）。[詳しく](#navigation)|
|`{{ NAV_LINKS:@same }}`|表示中のページと「_」以外のカテゴリが重なるページ（NAV_CARDSも同じ）|
|`{{ POST_MD:ID }}`|指定したIDのページをMarkdownとして埋め込む|
|`{{DATA:key}}値{{/DATA}}`|データを定義（表示）。keyは半角英字で始め、そのあとに半角英数字・アンダースコアを使えます。キーは大文字と小文字を区別し、値は日本語でも書けます。|
|`{{DATA:key:GHOST}}値{{/DATA}}`|データ定義（非表示）。keyは表示するDATAと同じ規則|
|`{{DATAROW:rowID}} {{DATA:key}}値{{/DATA}} {{/DATAROW}}`|テーブル形式のデータ定義。rowIDには半角英数字・アンダースコア・ハイフンを使えます。keyはDATAと同じ規則です。|
|`{{ POST_MD::key }}`|ページ内のデータを表示|
|`{{POST_MD:pageID:key}}`|IDページのデータを表示|
|`{{ POST_MD::#rowID:key }}`|ページ内のテーブル形式のデータを表示|
|`{{ POST_MD:pageID#rowID:key }}`|IDページのテーブル形式のデータを表示|
|`{{CONTENT}}`|**ページWrapper専用**: 本文を挿入|
|`{{HEAD_CSS}}`|集約したCSSの挿入位置。最初の1つを使用します。省略時は `</head>` の直前、閉じタグがなければHTMLの先頭へ挿入します。初期設定では `_global_head` にあります。|

### 外部Markdownの取得 {.flat .sqlite}

|タグ|説明|
|---|---|
|`{{ EXT_MD:url }}`|指定したURLのMarkdownを埋め込む|
|`{{ EXT_MD:url:key }}`|外部ページのデータを表示|
|`{{ EXT_MD:url#rowID:key }}`|外部ページのテーブル形式のデータを表示|

サイト共通設定で有効にして使います。詳しくは[他のサイトのMarkdown文書の埋め込み](#external-markdown)を参照してください。

### サイト内検索 {.sqlite}

|タグ|説明|
|---|---|
|`{{SEARCH_RESULTS}}`|サイト内検索の検索結果を表示します。`{{SEARCH_RESULTS:_search_result_item}}` のように結果1件の部品を指定できます。|
|`{{RESULT_TITLE}}`|検索結果部品で、見つかったページのタイトル|
|`{{RESULT_SNIPPET}}`|検索結果部品で、検索語に関連した抜粋|
|`{{RESULT_URL}}`|検索結果部品で、見つかったページのURL|
|`{{RESULT_DATE}}`|検索結果部品で、見つかったページの更新日時の日付|

### 検索とJavaScriptの位置 {.cloud}

|タグ|説明|
|---|---|
|`{{SEARCH_RESULTS}}`|Googleのサイト内検索フォームを表示します。検索結果部品のIDを付けてもフォームは同じです。|
|`{{HEAD_JS}}`|HTMLで集約したJavaScriptを挿入する位置です。各指定位置に出力し、省略すると `</body>` の直前、閉じタグがなければ末尾へ挿入します。|

検索語はGoogleに送られます。Googleに登録されたページが対象なので、公開した直後のページは検索結果に出るまで時間がかかることがあります。

### 年号の表示

今年の年号などはJavaScriptで表示できます（例: `<script>document.write(new Date().getFullYear())</script>`）。これにより、サーバー側の処理を介さず常に最新の年号を表示できます。

---

## 初期コンポーネント一覧 {#init-comps}

インストール時に同梱される部品は、デザイン画面で確認・編集できます。flat版は11件、SQLite版・Cloud版は各13件です。

Cloud版の見本ページが参照する画像は同梱されません。見本に画像を使う場合は、メディアから追加します。 {.cloud}

### 全版の初期コンポーネント

|ID|タイプ|役割・内容|
|---|---|---|
|`_layout`|[ページ]{.type-badge .badge-page}|標準的な共通レイアウト。`{{COMPONENT:_global_head}}` `{{COMPONENT:_header}}` `{{CONTENT}}` `{{COMPONENT:_footer}}` を組み合わせた基本構成です。`{{HEAD_CSS}}` は `_global_head` にあります。通常のMarkdownページはこれを選択します。|
|`_global_head`|[パーツ]{.type-badge .badge-part}|HTMLの`<head>`セクション内に挿入する共通タグ集。Google AnalyticsやWebフォントの読み込みなど、全ページ共通の`head`タグをここに記述します。初期設定では、`{{HEAD_CSS}}`が配置されており、CSSの挿入位置を明示できます。CSSスコープ化は無効(グローバル)。|
|`_header`|[パーツ]{.type-badge .badge-part}|サイト共通のヘッダー。サイト名・ナビゲーションリンクを含む標準的なヘッダーコンポーネントです。ナビゲーションには `{{NAV_LINKS:_nav}}`（構造用カテゴリ `_nav` のページ）を使います。|
|`_footer`|[パーツ]{.type-badge .badge-part}|サイト共通のフッター。コピーライト・フッターナビゲーション等を含む標準的なフッターコンポーネントです。|
|`_nav_card`|[パーツ]{.type-badge .badge-part}|カード型ナビゲーションでコンポーネントIDを省略した場合に使用される標準カードデザインです。サムネイル画像・タイトル・概要・日付を表示するカードを出力します。|
|`_md_footer`|[パーツ]{.type-badge .badge-part}|[Markdown版](#markdown-version)の末尾コンポーネントのサンプル。`{{NAV_LINKS:@same:limit=5}}` で関連ページのリストを付けます。ページ編集の「Markdown版の末尾コンポーネント」で選んだときだけ使われます（既定にはしません）。|
|`sample_card`|[パーツ]{.type-badge .badge-part}|カード表示の見本。`{{NAV_CARDS:カテゴリ名:sample_card}}` で使えます。|
|`json_item`|[パーツ]{.type-badge .badge-part}|JSONの目録の1件分のサンプル。`{{NAV_CARDS:カテゴリ:json_item:sep=,}}` で使います（[目録とページ一覧](#catalog-pager)）。|
|`_ai_question`|[パーツ]{.type-badge .badge-part}|[公開ページのAI質問ボックス](#cloud-ai-question)です。`{{COMPONENT:_ai_question}}` で配置します。|
|`DESIGN_sample.md`|[AI指示]{.type-badge .badge-ai}|AIにデザインを生成・編集させる際の指示書のサンプルです。見本を複製または編集し、`DESIGN.md` などの名前で自分の設計ルールを書けます。内容はAI自身に書かせることもできます。同様の使い方ができるファイルとして、`BRAND.md`（ブランド情報）や `CONTENTS.md`（コンテンツ方針）なども考えられます。|
|`AGENTS_sample.md`|[AI指示]{.type-badge .badge-ai}|🍊mikanBoxでのページ制作ルールをAIに渡すための指示書のサンプルです。見本を複製または編集し、`AGENTS.md` などの名前で自分の制作ルールを書けます。欄の使い分け、本文をMarkdownで書くこと、共通部分のコンポーネント化、メディアの扱い、公開設定とビルドをまとめています。|

### 初期の検索コンポーネント {.sqlite .cloud}

|ID|タイプ|役割・内容|
|---|---|
|`_search_box`|[パーツ]{.type-badge .badge-part}|フォームから検索ページ（`search`）へ検索語を送ります。|
|`_search_result_item`|[パーツ]{.type-badge .badge-part}|SQLite版の検索結果1件のデザインです。Cloud版にも同梱され、公開サイトの検索にはGoogleを使います。|

---

## データの埋め込み、読み込み、API {#data-embedding}

### データの形式

以下の3種類の形式の情報を扱うことができます。

- Markdown: サイト内部、あるいは外部（GitHub等）のMarkdownデータ
- Data: WordPressの拡張フィールドのような、key-value形式の個別データ
- DataBase: `{{DATAROW}}` を用いたテーブル形式のデータ

### Markdownの読み込み

#### 他のページをMarkdownとして読み込める

コンポーネントだけでなく、他のページ自体もMarkdownとして読み込むことができます。複雑なページを直接編集するのではなく、更新用のシンプルなページを更新するだけで、反映させられます。

#### 他のサイトのページをMarkdownとして読み込める

GitHubなど、他のサイトのページをMarkdownとして読み込むことで、サイトに直接触らなくても更新できます。外部文書のHTMLは文字として表示され、🍊mikanBoxのテンプレートタグも実行されません。もちろん、セキュリティ上、JavaScriptは動かないようにしています。

### GitHubのページをMarkdownとして出力

GitHubのmdファイルのページは以下のようにURLを変えて、Rawデータにすることで読み込むことができます。

変換前: [https://github.com/yoshihik0/testData/blob/main/md-test.md](https://github.com/yoshihik0/testData/blob/main/md-test.md)

変換後: [https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md](https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md)

#### 変換ルール
github.com → raw.githubusercontent.com
/blob/ → /（消す）

読み込み：
`{{EXT_MD:https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md}}`

#### GitHub以外では：

Dropbox: `?dl=1` に変える
Google Docs: 公開設定して `/export?format=txt`

### ページへのデータの埋め込みと利用

`{{DATA:key}}データ{{/DATA}}`と書くだけで、WordPressの拡張フィールドのように、ページに情報を埋め込んで、ページ内や他のページから利用できます。キー（key）は、半角英数字と「_」を使うことができます。しかも`{{DATA:key:GHOST}}データ{{/DATA}}`のように「:GHOST」を付け加えると、ページ内では表示されなくなります。

ページ内からの利用：`{{POST_MD::key}}`
他のページからの利用：`{{POST_MD:pageID:key}}`

### 他のサイトでのデータの埋め込みと利用

`{{DATA:key}}データ{{/DATA}}`の形式は、GitHubなど、他のサイトのページからも読み込めます。サイトに直接触らなくても更新できるので、AIと相性のよいGitHubを利用すれば、自動運用もしやすいです。

他のサイトからのデータの利用：`{{EXT_MD:url:key}}`

### DB Less DB

`{{DATAROW:rowID}}{{DATA:key}}データ{{/DATA}}{{/DATAROW}}`と書くだけで、表形式のデータを埋め込んで、ページ内や他のページから利用できます。データベースを使っていないのに、ページ自体がデータベースになれます。

ページ内のテーブル系形式のデータを表示：`{{POST_MD::#rowID:key}}`
IDページのテーブル系形式のデータを表示：`{{POST_MD:pageID#rowID:key}}`

### 他のサイトのデータベースも読み込める

`{{DATAROW:rowID}}{{DATA:key}}データ{{/DATA}}{{/DATAROW}}`の形式は、GitHubなど、他のサイトのページからも読み込める。

外部ページのテーブル系：`{{EXT_MD:url#rowID:key}}`

### CSVデータを変換

[サイト画面の「CSVインポート」](#csv-import)で、エクセルなどのCSVデータを `{{DATAROW}}` の形式に変換できます。

エクセルから書き出したCSV形式のファイルを選択し、
#### [content_copy]{.material-symbols-outlined} 変換してコピー{.m-btn .m-btn-gray}
をクリックして、データをコピーします。ページ内の本文にペーストして利用してください。

1行目をフィールド名として読み込み、各行を `{{DATAROW:連番}}` ブロックに変換してクリップボードにコピーします。コピー後、ページ編集の本文に貼り付けて保存してください。


### ヘッドレスCMSにもなる

`{{DATAROW}}`を埋め込んだページを、出力形式「[mikanBox DB](#mikanbox-db)」にして公開すると、外部からAPIで読み取ることができるようになります。

`https://yoursite.com/api/pageID.json`（PHP版の動的表示とCloud版では `/api/pageID` でも読めます）

例：[https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)


### さまざまな情報のカードが作れる

これらの情報は、カード形式の表示（レコードの一覧表示）のなかでも利用できるので、商品情報一覧なども作成できます。

|タグ|内容|
|:---|:---|
|`{{TITLE}}`|ページタイトル|
|`{{UPDATE_DATE}}`|更新日時の日付（YYYY-MM-DD）|
|`{{UPDATE_DATE:JP}}`|更新日時の日付（YYYY年MM月DD日）|
|`{{UPDATE_DATE:SLASH}}`|更新日時の日付（YYYY/MM/DD）|
|`{{IS_NEW:30}}`|N日以内 new、それ以外は空|
|`{{DESCRIPTION}}`|ページ概要）|
|`{{OGP_IMAGE}}`|ページのOGP画像のURL|
|`{{PAGE_URL}}`|ページのフルURL|
|`{{POST_MD::name}}`|データの表示|
|`{{POST_MD::date}}`|データの表示|
|`{{POST_MD::#test:book}}`|データの表示ROW|
|`{{IS_ACTIVE}}`|現在のページなら active、それ以外は空|

## 出力形式（Markdown版・データ） {#output-formats}

ページ編集で「出力形式」を選び、本文を書きます。保存前に「プレビュー」で出力を確認できます。公開時のURLは、出力形式の下に表示されます。静的に公開しているページの変更は、[ビルド](#static-build-button)すると反映されます。

| 目的 | 選ぶ形式 | 出力 |
|---|---|---|
| 通常のページを作る | HTML（既定） | HTMLページ |
| HTMLページにMarkdown版を付ける | HTML + Markdown | HTMLページと `/ID.md` |
| Markdownだけで文書を公開する | Markdown | `/ID.md` |
| 目録やファイルを出す | JSON・XML・CSV・テキスト・JavaScript・CSS | `/ID.拡張子` |
| 本文のデータをAPIで公開する | mikanBox DB | `/api/ID.json` |

ビジュアル編集は、HTMLまたはHTML + Markdownのページで使えます。

### URLの規則 {#output-urls}

ページIDが `news` なら、Markdown版は `/news.md`、JSONは `/news.json` です。拡張子は出力形式から付きます。トップページのMarkdown版は `/index.md` です。

#### PHP版のURL {.flat .sqlite}

HTMLを静的に書き出すと、[出力構成](#site-ssg)に応じて `/ID/` または `/ID.html` になります。Markdown版やデータ形式は、出力構成にかかわらず拡張子付きのURLで開きます。

#### Cloud版のURL {#cloud-output-urls .cloud}

HTMLは `/ID.html` に書き出します。`/ID` と `/ID/` からも開けます。Markdownだけのページでは、これらのURLから `/ID.md` を開けます。データ形式には拡張子付きのURLを使います。

ページIDが `news/index` のHTMLは `/news/index.html` に書き出します。`/news/index` と `/news/index/` からも開けます。`news` のページが存在しない場合は、`/news` と `/news/` からも開けます。

HTML・MarkdownページのURL別名は、サイト全体で2,000件までです。上限を超えるとビルドで知らせます。

### Markdown版を出す {#markdown-version}

HTMLも公開するなら「HTML + Markdown」、Markdownだけを公開するなら「Markdown」を選びます。HTML版のレイアウトを通さず、本文をMarkdownとして出します。サイト内リンクや画像のURLは、公開先の絶対URLになります。

PHP版で、相対リンクのアップロード用フォルダを使い、公開先ルートURLが空欄のときは、Markdown版もサイト内リンクと画像を相対リンクで書き、先頭の `url` を付けません。公開先ルートURLを設定すると、絶対URLで書きます。 {.flat .sqlite}

ページ編集の「Markdown版の末尾コンポーネント」で、文末に付ける部品を選べます。たとえば同梱の `_md_footer` を使うと、関連ページのリンクを付けられます。

#### 詳細：Markdown版に含まれるもの {.detail}

文書の先頭には、ページの情報が付きます。`date` は「更新」の年月日です。

```yaml
---
title: "ページタイトル"
date: 2026-11-08
description: "ページの概要"
url: "https://example.com/page/"
categories: "coffee, tokyo"
---
```

`url` はHTML版があるときだけ付きます。サイト内のページへリンクするときは、相手にMarkdown版があればそのURLを使います。コードブロック・インラインコード・HTMLコメント内の記述はそのまま残ります。

### データ形式のファイルを出す {#data-formats}

JSON・XML・CSV・テキスト・JavaScript・CSSの形式を選び、内容は「本文」に書きます。たとえばID `site-style` をCSS形式にすると `/site-style.css` で公開できます。データ形式のページは、ナビゲーションの一覧、サイトマップ、RSS、検索には出ません。

「タグを展開する」は、JSON・XML・CSV・テキストでは既定でオン、JavaScript・CSSでは既定でオフです。JavaScriptやCSSで🍊mikanBoxの独自タグを使いたいときは、この設定をオンにします。

### 本文のデータをAPIで公開する {#mikanbox-db}

出力形式を「mikanBox DB」にして公開すると、本文の `{{DATA}}` と `{{DATAROW}}` を `/api/ID.json` で読めます。本文中の見出しや説明文はAPIに含まれません。ファイル全体を自由な形で書く場合は「JSON」を選びます。

#### 詳細：mikanBox DBの出力例 {.detail}

`fields` はページ全体のデータ、`rows` は `{{DATAROW}}` に書いた各行です。公開する前にプレビューでJSONを確認できます。

```json
{
  "id": "sample_data",
  "title": "商品一覧",
  "fields": { "SHOP": "果物店" },
  "rows": [{ "id": 1, "GOODS": "Apple", "PRICE": "300" }]
}
```

### ページのURLを埋め込む {#page-url-id}

ページIDを指定すると、出力形式に応じたURLを入れられます。JavaScriptやCSSをデータ形式のページに書いた場合は、次のようにHTMLから読み込みます。

```html
<link rel="stylesheet" href="{{PAGE_URL:site-style}}">
<script src="{{PAGE_URL:site}}"></script>
```

データ形式のURLには、内容が変わったときにブラウザが新しいファイルを読み込むための `?v=` が付きます。IDを省いた `{{PAGE_URL}}` は、表示中のページのURLです。

---

## 目録データでページ一覧を作る {#catalog-pager}

ページ送りのある一覧は、JSONの目録をJavaScriptで読み込んで作ります。まず同梱の `sample_list_page` を開くと、動く例を確認できます。自分の一覧を作るときは、目録を作り、一覧を見せるページから読み込みます。

### JSONで目録を作る

ページを作って出力形式を「JSON」にします。IDは例として `news-list` とし、「タグを展開する」をオンにします。次の例ではカテゴリ `news` のページを更新日時の新しい順に出します。

```text
[{{NAV_CARDS:news:json_item:sort=new:sep=,}}]
```

同梱の `json_item` が一覧の1件分を作ります。`news` を自分のカテゴリ名に変えます。全ページを対象にするときは `all` を使います。公開前にプレビューでJSONを確認してください。

### 一覧を見せる

一覧を見せるHTMLページの本文に、次を貼り付けます。同梱のJavaScriptページ `sample_pager` が目録を読み込み、20件ずつ表示します。

```html
<div class="mikan-pager" data-src="{{PAGE_URL:news-list}}" data-per-page="20"></div>
<script src="{{PAGE_URL:sample_pager}}"></script>
```

`news-list` は目録のID、`20` は1ページに表示する件数です。目録・JavaScript・一覧ページを公開します。静的に公開する場合は[ビルド](#static-build-button)します。

---

## ポッドキャスト配信 {#podcast}

音声を張り込んだページを作成し、カテゴリを「podcast」に設定すると、「podcast」カテゴリのページのみのRSSが、以下のURLで出力され、ポッドキャストを配信できます。

`https://yoursite.com/podcast.xml`

おもに、以下の情報を埋め込みます。AUDIO_FILEは必須です。

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}`
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`

### ポッドキャスト用の項目

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}` ← 必須
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`
`{{DATA:EPISODE_NUM:GHOST}}1{{/DATA}}`
`{{DATA:SEASON:GHOST}}1{{/DATA}}`
`{{DATA:SUBTITLE:GHOST}}今回のテーマは...{{/DATA}}`
`{{DATA:EPISODE_IMAGE:GHOST}}ep01-cover.jpg{{/DATA}}`
`{{DATA:EPISODE_TYPE:GHOST}}full{{/DATA}}`
`{{DATA:EXPLICIT:GHOST}}false{{/DATA}}`

タイトル`{{DATA:TITLE}}`と概要`{{DATA:DESCRIPTION}}`はページの設定から自動で取得します。

利用可能なデータはサービスによって異なります。

### ポッドキャスト配信の手順

1. 🍊mikanBoxでポッドキャストエピソードページを作る（カテゴリ「podcast」）
2. `https://yoursite.com/podcast.xml` を開いて確認する（Cloud版は[ビルド](#static-build-button)して公開したあと）
3. そのURLを各プラットフォームに登録する

登録先：
| プラットフォーム | 登録場所 |
|:---|:---|
|Apple Podcasts|podcastsconnect.apple.com|
|Spotify|podcasters.spotify.com|
|YouTube・YouTube Music|[RSSフィードで配信する手順](https://support.google.com/youtubemusic/answer/13525207?hl=ja)|
|Amazon Music|music.amazon.com/podcasts|

一度登録すれば、あとは新しいエピソードページを追加するたびに自動で各プラットフォームが `podcast.xml` を取りに来て更新されます。

PHP版では音声ファイルをメディアにアップロードし、`AUDIO_FILE` にファイル名を指定します。 {.flat .sqlite}

Cloud版では外部で配信している音声のURLを `AUDIO_FILE` に指定します（例 `https://example.com/episode01.mp3`）。エピソードを追加・変更するたびにビルドします。 {.cloud}

---

## サイトマップ・RSS・API {#site-mapfeed}

これらのURLは、基本的にアクセスのたびにPHPがその場で最新の内容を生成します（動的）。ただし、[静的サイト生成 (SSG)](#site-ssg)の出力先ディレクトリをサイトのルートと同じ場所にしている場合は、静的ビルドのたびにこれらも実ファイルとして書き出され、以降はビルド時点の内容のまま（次のビルドまで更新されない）静的ファイルとして配信されます。 {.flat .sqlite}

Cloud版ではサイトマップ、RSS、Podcast RSS、mikanBox DBのJSONをファイルとして[ビルド](#static-build-button)し、公開用のアドレスで配信します。404はサイトマップやフィードから除きます。 {.cloud}

### サイトマップ

以下のURLでサイトマップが出力されます。

`https://yoursite.com/sitemap.xml`

例：[https://yoshihiko.com/mikanbox/demo/sitemap.xml](https://yoshihiko.com/mikanbox/demo/sitemap.xml)

[データ形式](#data-formats)のページはサイトマップ・RSS・Podcast RSSに含めません。Markdownだけのページは `/ID.md` のURLで載せます。

### RSS

以下のURLでRSSフィードが出力されます。

`https://yoursite.com/rss.xml`

例：[https://yoshihiko.com/mikanbox/demo/rss.xml](https://yoshihiko.com/mikanbox/demo/rss.xml)

### Podcast RSS

音声を張り込んで、カテゴリを「podcast」に設定すると、「podcast」カテゴリのページのみのRSSが、以下のURLで出力され、ポッドキャストを配信できます。

`https://yoursite.com/podcast.xml`

例：[https://yoshihiko.com/mikanbox/demo/podcast.xml](https://yoshihiko.com/mikanbox/demo/podcast.xml)

### API

`{{DATAROW}}`を埋め込んだページを、出力形式「[mikanBox DB](#mikanbox-db)」にして公開すると、外部からAPIで読み取ることができるようになります。

`https://yoursite.com/api/pageID.json`（PHP版の動的表示とCloud版では `/api/pageID` でも読めます）

例：[https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

---

## AIで作ったデザインを公開する {#ai-guide}

### ページ全体を作るプロンプト

`CSS・JSも含めて1つのHTMLファイルとして出力して。画像は media/ファイル名 で`

デザインを作るプロンプトに以上の情報を加えてください。

### 作ったページの使い方

ページを生成したら、以下の手順で配置します。

1. 「ページ編集」画面で、デザインコンポーネントに「なし（本文のHTMLをそのまま使用）」を選択します。
2. 生成したHTMLのすべての内容を「本文」にペーストして保存してください。

### 複数ページの共通部分

共通部分（ヘッダー、フッター、&lt;head&gt;内など）を切り分けて管理できます。

- `_header`、`_footer`、`_global_head` などに切り分けて登録してください。
- 「デザインコンポーネント」で `_layout` を選ぶと、これらが自動的に機能します。
- 各ページのSEO情報（タイトル・概要・OGP画像など）を反映させるには、コンポーネント内に `{{COMPONENT:_global_head}}` を入れてください。
- サイト共通や各ページで管理しているCSSは自動挿入されます。挿入位置を指定したい場合は `{{HEAD_CSS}}` を配置してください。

---

## お知らせ {#announcement}

本プログラムの制作者は、本プログラムを基本としたCMSの設計・UIデザイン・構築・運用等をお受けすることができます。
また、それ以外でも、UIデザイン・情報設計・DX推進・AI活用・社内広報・デザイン教育・コンテンツ制作などの分野でお困りのことがあれば、お気軽にご相談ください。

著書：『デザインの教室』『デザインの授業』『フラットデザインの基本ルール』

[https://yoshihiko.com](https://yoshihiko.com)

---

## ライセンス {#license}

**MIT License**

Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)

<div class="license-block">
以下に定める条件に従い、本ソフトウェアおよび関連文書のファイルを無償で利用する権利を許諾します。商用利用、修正、配布、再許諾を含みますが、これらに限定されません。<br><br>
<strong>条件:</strong> 上記の著作権表示および本許諾表示を、本ソフトウェアのすべての複製または重要な部分に記載するものとします。<br><br>
<strong>免責事項:</strong> 本ソフトウェアは「現状のまま」提供され、明示または黙示を問わず、いかなる保証もありません。作者または著作権者は、契約、不法行為、またはその他に起因するいかなる請求、損害、またはその他の責任についても一切の責任を負わないものとします。
</div>

<script>
(function () {
  var main = document.querySelector('main'), bar = document.querySelector('.manual-editions');
  if (!main || !bar) return;
  var editions = ['flat', 'sqlite', 'cloud'], nodes = Array.from(main.children);
  function level(el) { var m = /^H([1-6])$/.exec(el.tagName); return m ? +m[1] : 0; }
  function applicable(el, edition) { var own = editions.filter(function(e) { return el.classList.contains(e); }); return edition === 'all' || !own.length || own.indexOf(edition) !== -1; }
  nodes.forEach(function(el, i) {
    if (level(el) && el.classList.contains('detail')) {
      for (var j = i + 1; j < nodes.length && (!level(nodes[j]) || level(nodes[j]) > level(el)); j++) nodes[j].classList.add('detail');
    }
    var own = editions.filter(function(e) { return el.classList.contains(e); });
    if (own.length && level(el)) { var badge = document.createElement('span'); badge.className = 'manual-edition-badge'; badge.textContent = own.join(' / '); el.appendChild(badge); }
  });
  var groups = [], run = [];
  function flush() {
    if (!run.length) return;
    var group = run.slice(), button = document.createElement('button');
    button.type = 'button'; button.className = 'manual-more'; button.textContent = "詳細を開く"; button.setAttribute('aria-expanded', 'false');
    group[0].before(button); groups.push({button:button, nodes:group});
    button.addEventListener('click', function() { var open = button.getAttribute('aria-expanded') !== 'true'; button.setAttribute('aria-expanded', String(open)); button.textContent = open ? "詳細を閉じる" : "詳細を開く"; group.forEach(function(el) { el.classList.toggle('manual-open', open); }); });
    run = [];
  }
  nodes.forEach(function(el) { if (el.classList.contains('detail')) run.push(el); else flush(); }); flush();
  function apply(edition) {
    if (editions.concat('all').indexOf(edition) < 0) edition = 'all';
    document.body.dataset.edition = edition;
    main.querySelectorAll('.manual-hidden').forEach(function(el) { el.classList.remove('manual-hidden'); });
    nodes.forEach(function(el, i) {
      if (!applicable(el, edition)) {
        el.classList.add('manual-hidden'); var n = level(el);
        if (n) for (var j = i + 1; j < nodes.length && (!level(nodes[j]) || level(nodes[j]) > n); j++) nodes[j].classList.add('manual-hidden');
      }
    });
    main.querySelectorAll('li, span, td, tr').forEach(function(el) { if (!applicable(el, edition)) el.classList.add('manual-hidden'); });
    groups.forEach(function(g) { g.button.classList.toggle('manual-hidden', !g.nodes.some(function(el) { return !el.classList.contains('manual-hidden'); })); });
    bar.querySelectorAll('[data-edition-choice]').forEach(function(b) { b.setAttribute('aria-pressed', String(b.dataset.editionChoice === edition)); });
    document.querySelectorAll('nav.sidebar a[href^="#"]').forEach(function(a) { var target = document.getElementById(a.getAttribute('href').slice(1)); a.parentElement.classList.toggle('manual-hidden', !!target && target.classList.contains('manual-hidden')); });
    try { localStorage.setItem('mikanbox-manual-edition', edition); } catch (_) {}
  }
  bar.querySelectorAll('[data-edition-choice]').forEach(function(b) { b.addEventListener('click', function() { apply(b.dataset.editionChoice); }); });
  var details = document.getElementById('manual-details');
  details.addEventListener('change', function() { main.classList.toggle('manual-show-details', details.checked); });
  main.classList.add('manual-details-ready');
  var selected = new URLSearchParams(location.search).get('edition');
  try { selected = selected || localStorage.getItem('mikanbox-manual-edition'); } catch (_) {}
  apply(selected || 'all');
})();
</script>
</main>
