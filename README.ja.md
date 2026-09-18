# laravel-openapi-probe

OpenAPI 定義からリクエストを生成して Laravel アプリケーションにプロセス内で送り、
応答が定義と食い違う箇所を報告します。定義書が書いた Feature テスト、と考えると
近いです。発想は [schemathesis](https://github.com/schemathesis/schemathesis) と同じで、
Laravel 自身のリクエストパイプラインの上に組んであります。

[English](README.md)

定義の読み込みと応答の検証は
[kojirock5260/laravel-json-schema-validate](https://github.com/kojirock5260/laravel-json-schema-validate)
に依存します。PHP 8.3 以上、Laravel 12 または 13 が必要です。

OpenAPI 3.1 の定義はそのまま読みます。3.0 の定義は `nullable: true` を型の union に直した
写しを一時ファイルに作って読みます。元のファイルには触りません。

## 何をするか

登録済みのルートに対応する定義上の各操作について:

1. `example`、`enum`、`format`、数値と長さの制約から、定義に適合するリクエストを 1 つ作る
2. 制約を 1 つずつ破ったリクエストを作る。必須パラメータの欠落、`minimum` / `maximum` の外、
   `maxLength` 超過、`enum` 外の値、型違い、未知のプロパティ
3. HTTP カーネルを通して送る。各リクエストは DB トランザクションで包み、終わったら巻き戻す
4. すべての応答を定義と照合する

| 報告 | 意味 |
|---|---|
| `server-error` | 5xx が返った |
| `undocumented-status` | そのステータスコードが操作に定義されていない |
| `response-mismatch` | ヘッダーか本文が定義のスキーマに合わない |
| `validation-bypass` | 定義に反するリクエストが 2xx で受理された |
| `spec-rejected` | 定義に適合するリクエストが 400 / 422 で拒否された |
| `missing-route` | 定義にある操作に対応するルートが無い |

どちらが正しいかは道具には分かりません。`spec-rejected` は定義の書き忘れかもしれず、
`validation-bypass` は意図した寛容さかもしれません。判断は人がします。

## インストール

```bash
composer require --dev kojirock5260/laravel-openapi-probe
```

定義の場所は依存先パッケージの設定で指定します。詳細はそちらの README を参照してください。

```dotenv
OPENAPI_PATH=/path/to/openapi.yaml
OPENAPI_BASE_PATH=api       # ルートが /api 配下で、定義にその接頭辞が無い場合
```

## 実行

```bash
php artisan openapi:probe --token="$API_TOKEN" --strict
```

| オプション | 効果 |
|---|---|
| `--token=` | 全リクエストに付ける Bearer トークン。`OPENAPI_PROBE_TOKEN` でも可 |
| `--header=` | 追加ヘッダー。複数指定可: `--header="X-Api-Key: secret"` |
| `--id=` | パスパラメータに使う値。既定は `1` |
| `--only=` | `METHOD /path` にこの文字列を含む操作だけ |
| `--limit=` | 操作あたりのリクエスト数の上限。既定は `16` |
| `--keep-throttle` | throttle ミドルウェアを残す。1 プロセスから大量に送るため既定では外す |
| `--strict` | 報告があれば失敗の終了コードを返す。CI 用 |
| `--allow-production` | `APP_ENV=production` でも実行する |

## 実行前に読んでください

巻き戻せるのは DB だけです。メール、Webhook、キューに入るジョブ、他サービスへの呼び出し、
ファイル書き込み。リクエストが起こしたそれ以外のことは、実際に起きます。それらを止めた
開発環境かテスト環境で動かし、本番には決して向けないでください。`APP_ENV=production`
のときコマンドは起動を拒否します。

パスパラメータはすべて同じ値（`--id`）になるため、既存レコードが必要な操作の多くは 404 を
返します。404 が定義されていれば問題ありません。深く掘るなら先に DB にデータを入れてください。

## 現状

Firefly III 6.7.2 と公式の定義で試した結果: 242 操作、1,208 リクエスト、4 秒、
239 件の報告。うち不正入力での 500 が 14 操作で 28 件、未定義の 422 を返す操作が 22 件。

## 開発について

このプロジェクトは [Claude](https://claude.com)（Anthropic）を活用して開発しています。
