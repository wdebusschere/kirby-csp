# Kirby CSP

[![Tests](https://github.com/wdebusschere/kirby-csp/actions/workflows/php.yml/badge.svg)](https://github.com/wdebusschere/kirby-csp/actions/workflows/php.yml) ![Kirby 4/5](https://img.shields.io/badge/Kirby-4%20%7C%205-green.svg) ![License MIT](https://img.shields.io/badge/license-MIT-blue.svg)

Sends a strict `Content-Security-Policy` header with a per-request nonce for [Kirby](https://getkirby.com), following [Google's strict CSP guidance](https://web.dev/articles/strict-csp) — `'strict-dynamic'` + nonce, with `https:` / `'unsafe-inline'` kept only as a legacy-browser fallback.

- **Opt-in and per-host** — disabled by default, enable per environment via `config.<host>.php`.
- **Report-only rollout** — test a policy against real traffic before enforcing.
- **Per-request nonce** — `cspNonce()` helper for inline scripts, Vite tags, and third-party snippets.
- **Panel-safe** — the header is only sent on frontend routes; Panel, API and media are left untouched.

## Installation

### Composer

```bash
composer require wdebusschere/kirby-csp
```

### Download / Git submodule

Copy this repository into `site/plugins/kirby-csp/`:

```bash
git submodule add https://github.com/wdebusschere/kirby-csp.git site/plugins/kirby-csp
```

No build step is required — Kirby autoloads plugins from `site/plugins/`. The plugin registers itself as `akibeo/csp` and reads its options from the `akibeo.csp` namespace.

> **Porting to an existing site?** Follow [SETUP.md](SETUP.md) — a self-contained rollout guide (copy files, find scripts that need nonces, whitelist domains, test in report-only, enforce). It's written so you can also hand it to an AI agent as-is.

## Configuration

The plugin is **disabled by default**. Enable it in `site/config/config.php` or a host config (`config.<host>.php`):

```php
return [
    'akibeo.csp' => [
        'enabled' => true,

        // Optional: only send the header on these hosts (compared
        // lowercase, without port). Empty array = all hosts.
        'hosts' => ['www.example.com'],

        // Optional: test the policy without enforcing it — sends
        // Content-Security-Policy-Report-Only instead.
        'reportOnly' => true,
    ],
];
```

### Overriding directives

Directives are an associative array of `directive => value`. Config values merge over the defaults per directive, so you only specify what you change. `{nonce}` is replaced with the per-request nonce:

```php
'akibeo.csp' => [
    'enabled' => true,
    'directives' => [
        'frame-src' => "'self' https://www.youtube.com",
    ],
],
```

The defaults (see [`index.php`](index.php)) are a **deliberately minimal, vendor-neutral strict baseline** — everything is `'self'` plus the `'strict-dynamic'` + nonce script policy. Add the origins your project actually uses on top.

#### Example: Google Fonts + Analytics / Tag Manager + Maps

```php
'akibeo.csp' => [
    'enabled' => true,
    'directives' => [
        'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
        'font-src' => "'self' data: https://fonts.gstatic.com",
        'img-src' => "'self' data: https:",
        'connect-src' => "'self' https://www.google-analytics.com https://www.googletagmanager.com https://analytics.google.com https://region1.google-analytics.com",
        'frame-src' => "'self' https://www.google.com",
    ],
],
```

See [SETUP.md](SETUP.md) for a per-vendor directive table (Mapbox GL, Fontshare, GTM, reCAPTCHA, …).

## Usage

### Nonce for inline scripts

With `'strict-dynamic'`, inline scripts are blocked unless they carry the request nonce. Use the `cspNonce()` helper in templates and snippets:

```blade
<script nonce="{{ cspNonce() }}">
    // inline script allowed by the CSP
</script>
```

The nonce is generated once per request and memoized, so every call returns the same value that was sent in the header.

External scripts loaded by a nonced script are allowed automatically via `'strict-dynamic'`; static `<script src>` tags need the nonce attribute too. Consent-gated `<script type="text/plain">` tags and `application/ld+json` data blocks need no nonce — the former are re-injected by the (nonced) cookie-consent script, the latter are never executed.

### Nonce for Vite tags

The tags printed by `vite()` are parser-inserted and need the nonce as well. The [`lukaskleinschmidt/kirby-laravel-vite`](https://github.com/lukaskleinschmidt/kirby-laravel-vite) plugin accepts a callable, resolved once per request:

```php
'lukaskleinschmidt.laravel-vite' => [
    'nonce' => fn () => cspNonce(),
],
```

### Script tags from Composer-managed plugins

Plugins whose folders are gitignored (e.g. a cookie-consent plugin) can't be patched in place. Override their snippet instead: a file with the registered snippet name in `site/snippets/` (e.g. `site/snippets/cookieconsentJs.php`) takes precedence over the plugin's version — copy it and add `'nonce' => cspNonce()` to the `js()` attribute arrays.

### Finding scripts that need a nonce

Before enforcing, every inline `<script>` and static `<script src>` in the templates needs the nonce attribute. Quick greps:

```bash
# Inline and static script tags (excluding already-nonced ones)
grep -rn "<script" site/templates site/snippets | grep -v "cspNonce()"

# Third-party origins referenced anywhere in the frontend
grep -rhoE 'https://[a-z0-9.-]+' site/templates site/snippets assets | sort -u
```

### Testing a policy

1. Set `'reportOnly' => true` and `'enabled' => true`.
2. Browse the site with DevTools open — violations show up in the console but nothing is blocked.
3. Once the console stays clean, switch `reportOnly` off to enforce.

See [SETUP.md](SETUP.md) for the full rollout checklist and a vendor-specific directive table (Mapbox GL, Fontshare, GTM, reCAPTCHA, …).

## Development

```bash
composer install
composer test      # vendor/bin/phpunit
```

The header-building logic lives in `Akibeo\Csp\Csp` (`src/Csp.php`) — pure, Kirby-free helpers covered by the test suite in `tests/`.

## License

[MIT](LICENSE) — © [E-xperience LAB](https://e-xperience.pt)

## Credits

- Wannes Debusschere
- Yassine El Bouazzaoui
