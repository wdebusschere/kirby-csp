# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `cacheSafe` option for sites using Kirby's pages cache: cached HTML stores a
  stable nonce placeholder (via `page.render:after`) and the real per-request
  nonce is injected into every response — cache hits included — through an
  output buffer. Requires Kirby 4+.
- `cacheSafePlaceholder` option: a per-site secret placeholder for `cacheSafe`,
  so markup injected into cached content that carries the (public) default
  placeholder cannot obtain a valid nonce.

### Fixed

- A custom API slug (`api.slug` option) is now excluded from the CSP like the
  Panel slug already was.
- Newlines in configured directive values are folded into spaces instead of
  making PHP's `header()` silently drop the entire CSP header.

## [1.0.0] - 2026-07-24

### Added

- Initial release.
- `route:before` hook that sends a strict `Content-Security-Policy` header with a
  per-request nonce, following Google's strict CSP guidance
  (`'strict-dynamic'` + nonce, with `https:` / `'unsafe-inline'` as legacy fallback).
- `cspNonce()` helper — one memoized base64 nonce per request for inline scripts,
  Vite tags and third-party snippets.
- Opt-in `enabled` flag, per-host `hosts` allowlist, and `reportOnly` mode for a
  safe rollout.
- Vendor-neutral minimal default directives; per-directive config merge with a
  `{nonce}` placeholder.
- Panel, API and media routes are never sent a CSP header.

[1.0.0]: https://github.com/akibeo/kirby-csp/releases/tag/1.0.0
