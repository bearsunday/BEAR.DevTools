# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0.html),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- `HtmlLinkAuditor` reported every link as `target-missing` when the HTML contained more than one `<a>`/`<area>`/`<link>`/`<form>` tag
- `HtmlLinkAuditor` could not read single-quoted or unquoted attribute values (e.g. `class='goNext'`)
- `CreateResponse` split header lines at the last `": "` instead of the first, corrupting values such as `Link: </next>; title="Step: 2"`. Headers without a space after the colon or with an empty value are now kept instead of dropped
- `HtmlLinkAuditor` and `HrefExtractor` read `data-href` (or any `*-href`/`*-class`/`*-rel`) as the real attribute, and treated links inside HTML comments as rendered affordances. Comments and tags are now scanned in one pass (new internal `HtmlTagScanner`) following the HTML tokenizer (`<!-->`, `<!--->`, `--!>` and unclosed comments), so a `<!--` or `>` inside a quoted attribute value no longer hides the rest of the page or truncates the tag
- `HtmlLinkAuditor` and `HrefExtractor` read `href=`/`class=`/`rel=` text inside another attribute's value (e.g. `title="see href=/x"`), and `:href`/`xlink:href`, as the real attribute. Attributes are now tokenized into a name/value map following the HTML tokenizer (first duplicate wins, a quote inside an unquoted value is part of the value)
- `CreateResponse` read an interim `HTTP/1.1 100 Continue` as the final status; interim 1xx responses are now skipped, and `HttpResource` no longer sends `Expect: 100-continue`
- `DevInvoker` restarted the XHProf profile on every embedded request, cutting the outer profile short, and left XHProf enabled when a resource threw. Only the outermost request is profiled now, and timing headers are set in a `finally` block
- `HttpResource` crashed with `ValueError` on Windows when a POST/PUT/PATCH/DELETE body was 8 KB or larger, because `escapeshellarg()` rejects such arguments there; the logged curl command now shows the body size instead
- `HaloRenderer` returned the view without the dev tools CSS/JS (only `$ro->view` had them), and printed the resource URI, State tab, file paths and profile id without HTML escaping. Interceptors are now listed for every method, not only `onGet`

### Changed
- `HttpResource` throws the new `BEAR\Dev\Http\Exception\HttpRequestException` when no HTTP response arrives (connection refused, timeout) instead of returning a fake `200` response (also when the output has no HTTP status line), and sets a 10 s connect timeout. There is no total timeout, so a slow response or a server paused in a debugger is still waited for

## [1.4.0] - 2026-06-15

### Added
- `AbstractWorkflowTest::linkHref()` resolves a HAL/Resource link relation to a URI without issuing a request, for unsafe transitions (POST/PUT/DELETE) that must choose the HTTP method explicitly
- `AbstractWorkflowTest::bodyString()` returns a body value asserted to be a string

### Changed
- Replaced the `WorkflowTestTrait` trait with the `AbstractWorkflowTest` base class. A concrete workflow test now extends `AbstractWorkflowTest` and writes the scenario once against `ResourceInterface`; an HTTP workflow test extends that concrete test and overrides only `newResource()`, proving the same rel-driven scenario across in-process and HTTP transports
- Moved `phpunit/phpunit` from `require-dev` to `require`, since the package ships `AbstractWorkflowTest` as a public test base class

### Fixed
- `HrefExtractor` now `preg_quote()`s relation/attribute names before building patterns, corrects attribute and `Link` header value extraction, and relaxes the `preg_match_all` result check so multiple link elements are scanned

### BC Break
- `WorkflowTestTrait` (added in 1.3.0) has been removed. Replace `use BEAR\Dev\Http\WorkflowTestTrait;` with `extends BEAR\Dev\Http\AbstractWorkflowTest`. The helper method set is unchanged, plus the new `linkHref()` and `bodyString()`

## [1.3.0] - 2026-06-06

### Added
- `HttpResource::href()` follows a HAL `_links` relation with a `GET` request, throwing `HalLinkNotFoundException` when the relation or its `href` is missing
- `AbstractWorkflowTest` for transport-agnostic hypermedia workflow tests (`follow()`, `linkHref()`, `followLocation()`, `bodyValue()`, `bodyString()`, `header()`); the same scenario can run in-process and over HTTP by overriding `newResource()`
- Per-test HTTP access logs: pass a log directory to `HttpResource` to write one `<test-name>.log` per test method
- PHP 8.5 support

### Changed
- Raised the minimum PHP version to 8.2
- Bumped the `bear/resource` requirement to `^1.31`
- Keep `phpunit/phpunit` in `require`, since the package ships `AbstractWorkflowTest` as a public test base class
- Modernized the codebase for PHP 8.2+ (constructor property promotion, readonly properties)
- Moved `src-deprecated/` from autoload to autoload-dev

### Fixed
- Harden `HrefExtractor` attribute parsing and semantic HTML link discovery

### Removed
- Unused dependencies: `ext-filter`, `psr/log`, `symfony/process`

## [1.2.2] - 2025-12-30

### Changed
- `HttpResource` now uses the native cURL extension instead of shell `exec()`, fixing payloads that contain shell metacharacters

### Added
- `ext-curl` requirement

## [1.2.1] - 2025-08-09

### Added
- Support for aura/sql v6 for PHP 8.4 compatibility

### Changed
- Moved `xhprof/xhprof` from `require` to `require-dev` to make profiling optional for consuming projects
- Keep `ext-xhprof` as suggest for performance profiling extension

### Fixed
- HttpResource class loading issues with aura/sql dependency resolution
