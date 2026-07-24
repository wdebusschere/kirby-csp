# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/wdebusschere/kirby-csp/releases/tag/1.0.0
