# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Document `AbstractWorkflowTest` as the base contract for transport-agnostic workflow tests.
- Add `linkHref()` and `bodyString()` helpers to `AbstractWorkflowTest`.

### Changed
- Keep PHPUnit as a direct dependency while `AbstractWorkflowTest` is shipped as a public test base class.

### Fixed
- Harden `HrefExtractor` attribute parsing and semantic HTML link discovery.

## [1.2.1] - 2025-08-09

### Added
- Support for aura/sql v6 for PHP 8.4 compatibility

### Changed
- Moved `xhprof/xhprof` from `require` to `require-dev` to make profiling optional for consuming projects
- Keep `ext-xhprof` as suggest for performance profiling extension

### Fixed
- HttpResource class loading issues with aura/sql dependency resolution
