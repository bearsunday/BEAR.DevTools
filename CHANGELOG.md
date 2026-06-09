# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-06-06

### Added
- `HttpResource::href()` follows a HAL `_links` relation with a `GET` request, throwing `HalLinkNotFoundException` when the relation or its `href` is missing
- `WorkflowTestTrait` for hypermedia workflow tests (`follow()`, `followLocation()`, `bodyValue()`, `header()`); the same scenario can run in-process or over HTTP by overriding `newResource()`
- Per-test HTTP access logs: pass a log directory to `HttpResource` to write one `<test-name>.log` per test method
- PHP 8.5 support

### Changed
- Raised the minimum PHP version to 8.2
- Bumped the `bear/resource` requirement to `^1.31`
- Moved `phpunit/phpunit` to `require`, since the package ships `WorkflowTestTrait` for use in consumer test suites
- Modernized the codebase for PHP 8.2+ (constructor property promotion, readonly properties)
- Moved `src-deprecated/` from autoload to autoload-dev

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