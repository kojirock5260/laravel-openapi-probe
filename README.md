# laravel-openapi-probe

Generates requests from your OpenAPI document, sends them to your Laravel
application in-process, and reports where the responses disagree with the
document. Think of it as Feature tests written by the document instead of by
you: the same idea as [schemathesis](https://github.com/schemathesis/schemathesis),
built on Laravel's own request pipeline.

[日本語](README.ja.md)

Requires PHP 8.3 and Laravel 12 or 13. It stands alone: the document is parsed with
[cebe/php-openapi](https://github.com/DEVizzent/cebe-php-openapi) and responses are checked with
[opis/json-schema](https://opis.io/json-schema/).

OpenAPI 3.1 documents are read as they are. OpenAPI 3.0 documents are normalized in memory
(`nullable: true` becomes a type union); your file is not touched.

## What it does

For every operation in the document that matches a registered route:

1. Builds one request that conforms to the document, from `example`, `enum`,
   `format` and the numeric and length constraints.
2. Builds requests that each break one constraint: a required parameter
   missing, a value outside `minimum` / `maximum`, a string over `maxLength`,
   a value not in `enum`, the wrong type, an unknown property.
3. Sends them through the HTTP kernel, each inside a database transaction
   that is rolled back afterwards.
4. Compares every response with the document.

| Finding | Meaning |
|---|---|
| `server-error` | The application answered 5xx |
| `undocumented-status` | The status code is not defined for the operation |
| `response-mismatch` | Headers or body do not match the documented schema |
| `validation-bypass` | A request that breaks the document was accepted with 2xx |
| `spec-rejected` | A request that conforms to the document was rejected with 400 / 422 |
| `missing-route` | The document defines an operation no route serves |

Which side is wrong is yours to decide: a `spec-rejected` finding may mean
the code demands something the document forgot, and a `validation-bypass`
may be deliberate leniency.

## Install

```bash
composer require --dev kojirock5260/laravel-openapi-probe
```

Tell it where the document is:

```dotenv
OPENAPI_PROBE_PATH=/path/to/openapi.yaml
OPENAPI_PROBE_BASE_PATH=api   # when routes live under /api but the document does not say so
```

`php artisan vendor:publish --tag=config` copies `config/openapi-probe.php` if you prefer a file.

## Run

```bash
php artisan openapi:probe --token="$API_TOKEN" --strict
```

| Option | Effect |
|---|---|
| `--token=` | Bearer token sent with every request (or `OPENAPI_PROBE_TOKEN`) |
| `--header=` | Extra header, repeatable: `--header="X-Api-Key: secret"` |
| `--id=` | Value used for path parameters (default `1`) |
| `--only=` | Only operations whose `METHOD /path` contains this text |
| `--limit=` | Maximum requests per operation (default `16`) |
| `--keep-throttle` | Keep throttle middleware; it is removed by default because everything is sent from one process |
| `--strict` | Exit with a failure code when there are findings, for CI |
| `--allow-production` | Run even when `APP_ENV=production` |

Output:

```
  server-error POST /v1/bills ................ body.amount_min: wrong type → 500
    {"message":"bccomp(): Argument #1 ($num1) must be of type string, int given", ...}
  undocumented-status GET /v1/accounts .............. type: not in enum → 422
  response-mismatch POST /v1/bills ................. conforming request → 200
    data.attributes.pc_amount_min: The data (null) must match the type: string

  Operations probed ........................................................ 242
  Requests sent ........................................................... 1208
```

## Read this before running it

Only the database is rolled back. Anything else a request causes really
happens: mail, webhooks, queued jobs, calls to other services, files. Run it
against a development or test environment with those switched off, never
against production. The command refuses to start when `APP_ENV=production`.

Path parameters get the same value everywhere (`--id`), so operations that
need existing records mostly answer 404, which is fine as long as 404 is
documented. Seed the database first for deeper coverage.

## Where it stands

Tried on Firefly III 6.7.2 with its official document: 242 operations,
1,208 requests, 4 seconds, 239 findings, among them 28 server errors on
malformed input across 14 operations, and 22 operations returning an
undocumented 422.

## About development

This project is built with the help of [Claude](https://claude.com) (Anthropic).
