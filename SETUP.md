# Setup guide — kirby-csp on a new Kirby site

Step-by-step guide to roll this plugin out on a Kirby website. Written to be followed by a developer **or handed to an AI agent as-is** ("follow SETUP.md from the kirby-csp plugin").

The goal: a strict, nonce-based `Content-Security-Policy` header, rolled out safely via report-only mode first.

## 1. Install the plugin

Composer:

```bash
composer require akibeo/kirby-csp
```

Or copy the whole folder into the target project as `site/plugins/kirby-csp/`:

```
site/plugins/kirby-csp/
├── index.php       # plugin registration: options + route:before hook
├── src/
│   ├── Csp.php     # pure header-building helpers
│   └── helpers.php # cspNonce() helper
├── composer.json
├── README.md
├── SETUP.md
├── LICENSE
└── SECURITY.md
```

No build step is needed — Kirby autoloads plugins from `site/plugins/`.

**Check the project's `.gitignore`:** many projects ignore `site/plugins/*` with explicit whitelists. If so, add:

```gitignore
!/site/plugins/kirby-csp
```

Verify with `git status site/plugins/kirby-csp/` — all plugin files must show up as untracked/added, not silently ignored.

## 2. Inventory the site's scripts and third-party origins

Before enabling anything, find what the policy must allow.

**a) Script tags that will need a nonce** (inline scripts and static `<script src>` tags are blocked by `'strict-dynamic'` unless nonced):

```bash
grep -rn "<script" site/templates site/snippets | grep -v "cspNonce()"
```

**b) Inline event handlers**, which a nonce cannot rescue — these must be rewritten before enabling (see §3):

```bash
grep -rnoEi '(^|[[:space:]])on[a-z]+="[^"]*"|href="javascript:[^"]*"' site/templates site/snippets
```

This assumes double-quoted attributes; if the codebase also uses `onclick='…'`, run it again with the quotes swapped.

**c) Third-party origins** used by the frontend (candidates for the directive whitelist):

```bash
grep -rhoE 'https://[a-z0-9.-]+' site/templates site/snippets assets | sort -u
```

**d) Or use an AI agent** — example prompt:

> Search all templates and snippets (site/templates, site/snippets) for `<script>` tags. For every inline script and every static `<script src>` tag without a `nonce` attribute, add `nonce="{{ cspNonce() }}"` (Blade) or `nonce="<?= cspNonce() ?>"` (plain PHP templates). Separately, find every inline event handler attribute (`onclick`, `onchange`, `onsubmit`, … any `on*=`) and every `href="javascript:…"`, and report them — these cannot take a nonce and must be rewritten as `addEventListener` calls in a bundled JS file. Then list every third-party domain the frontend loads resources from — scripts, styles, fonts, images, iframes, and fetch/XHR endpoints (check assets/js too) — grouped by CSP directive (script-src, style-src, font-src, img-src, frame-src, connect-src), so I can whitelist them in the `akibeo.csp` directives config.

Typical origins to look for: Google Fonts, Google Analytics / Tag Manager, Google Maps or Mapbox, YouTube/Vimeo embeds, cookie-consent CDNs, chat widgets, form/recaptcha endpoints.

**Known vendor requirements** (beyond the defaults in `index.php`):

| Vendor | Directives to add |
| --- | --- |
| Mapbox GL JS | `style-src https://api.mapbox.com` (lazy-loaded css), `connect-src https://api.mapbox.com https://events.mapbox.com`, `worker-src 'self' blob:` (layout engine runs in a blob worker), `blob:` in `img-src` |
| Fontshare (Satoshi) | `style-src https://api.fontshare.com`, `font-src https://cdn.fontshare.com https://api.fontshare.com` |
| GTM noscript pixel | `frame-src https://www.googletagmanager.com` |
| reCAPTCHA v3 | `frame-src https://www.google.com`, `connect-src https://www.google.com https://www.gstatic.com` (it XHRs to `/recaptcha/api2/*` on every submission); the loader tag itself needs a nonce |

## 3. Add the nonce to script tags

Every inline `<script>` and static `<script src>` in templates/snippets gets the nonce:

```blade
<script nonce="{{ cspNonce() }}">…</script>
<script nonce="{{ cspNonce() }}" src="/some/static.js"></script>
```

Notes:

- The nonce is generated once per request and memoized — header and templates always match.
- Scripts **injected by a nonced script** (e.g. GTM loading further scripts, Mapbox GL lazy-loading) are allowed automatically by `'strict-dynamic'` — no nonce needed there.
- **Consent-gated scripts stay un-nonced**: `<script type="text/plain" data-category="...">` tags (gtag, Meta pixel, LinkedIn Insight) are inert until the cookie-consent script re-injects them via `createElement` — `'strict-dynamic'` trusts that injection because the consent script itself is nonced.
- `<script type="application/ld+json">` data blocks are never executed and need no nonce.
- **Inline event handlers cannot be nonced — rewrite them.** A nonce is an attribute on a `<script>` tag; there is nowhere to put one on `onclick="…"`. The `'unsafe-inline'` in the default `script-src` does not help either: the browser ignores it as soon as a nonce is present in the same directive (that's the "Note that 'unsafe-inline' is ignored…" sentence in the console error). So every `on*=` attribute and every `href="javascript:…"` fails with *"Executing inline event handler violates the following Content Security Policy directive"*. Replace them with a data attribute plus a listener in a bundled script:

  ```blade
  {{-- before --}}
  <button onclick="toggleTheme()">…</button>

  {{-- after --}}
  <button type="button" data-theme-toggle>…</button>
  ```

  ```js
  document.addEventListener('click', (event) => {
      if (event.target.closest('[data-theme-toggle]')) toggleTheme();
  });
  ```

  Use `closest()` rather than comparing `event.target` directly, so clicks landing on a child element (an icon inside the button) still match. Allowing these via `'unsafe-hashes'` plus a hash per handler is possible but not recommended — it defeats most of the benefit of a strict policy and every handler edit becomes a config change.
- The Panel is untouched: the header is only sent on frontend routes, and only when enabled.

**a) Vite tags** — the tags printed by `vite()` are parser-inserted and need the nonce too. `lukaskleinschmidt/kirby-laravel-vite` supports a nonce option that accepts a callable, resolved once per request:

```php
'lukaskleinschmidt.laravel-vite' => [
    'buildDirectory' => 'build',
    'nonce' => fn () => cspNonce(),
],
```

**b) Script tags printed by Composer-managed plugins** (e.g. `kirby-cookieconsent`'s `cookieconsentJs` snippet) can't be patched in place — the plugin folder is gitignored. Override the snippet instead: a file in `site/snippets/` with the registered snippet name (e.g. `site/snippets/cookieconsentJs.php`) takes precedence over the plugin's version. Copy the plugin snippet and add `'nonce' => cspNonce()` to its `js()` attribute arrays.

## 4. Configure — start in report-only mode

In `site/config/config.php` (or a host config `config.<host>.php` for per-host rollout):

```php
'akibeo.csp' => [
    'enabled' => true,
    'reportOnly' => true, // test first — report, don't block

    // Optional: limit to specific hosts (lowercase, no port); [] = all hosts
    'hosts' => [],

    // Override only the directives that differ from the defaults in index.php.
    // Config merges per directive; {nonce} is replaced per request.
    'directives' => [
        // Example: site uses Mapbox GL + Fontshare + YouTube embeds
        'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com https://api.fontshare.com https://api.mapbox.com",
        'font-src' => "'self' data: https://fonts.gstatic.com https://cdn.fontshare.com https://api.fontshare.com",
        'img-src' => "'self' data: blob: https:",
        'connect-src' => "'self' https://api.mapbox.com https://events.mapbox.com https://www.google-analytics.com",
        'frame-src' => "'self' https://www.youtube-nocookie.com",
        'worker-src' => "'self' blob:",
    ],
],
```

The defaults (see `index.php`) are a minimal, vendor-neutral strict baseline — everything is `'self'` plus the `'strict-dynamic'` + nonce script policy. Add exactly what step 2 found on top; nothing third-party is whitelisted out of the box.

## 5. Test

1. With `reportOnly => true`, browse the site with DevTools open — every page type: home, listing/detail pages, forms, pages with maps/embeds/video, cookie banner flows.
2. Console shows `[Report Only]` CSP violations without breaking anything. Fix each one:
   - inline script blocked → add the nonce (step 3)
   - external resource blocked → add its origin to the right directive (step 4)
3. Verify the header is actually sent, and that the header nonce matches the HTML:

   ```bash
   curl -sD headers.txt https://www.example.com -o home.html
   grep -io "nonce-[A-Za-z0-9+/=]*" headers.txt          # header nonce
   grep -o 'nonce="[^"]*"' home.html | sort | uniq -c    # must all match it
   grep -o '<script[^>]*>' home.html | grep -v nonce      # only ld+json should remain
   ```

4. Confirm the Panel (`/panel`) and any API endpoints still work.
5. **Debug mode suppresses tracking snippets** (gtag, GTM, pixels, chat widgets) on many sites — those code paths only render on a non-debug host, so test them on staging/production, not just localhost.

## 6. Enforce

When the console stays clean across the site, flip `reportOnly` to `false` (or remove it). Re-test the critical flows once more (forms, maps, favorites, cookie consent).

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Inline script blocked, console shows "Refused to execute inline script" | Add `nonce="{{ cspNonce() }}"` to that tag |
| Third-party script/frame/font blocked | Add its origin to the matching directive in config |
| GTM/GA tags not firing | Ensure the GTM loader snippet itself is nonced; `'strict-dynamic'` then allows what it loads |
| Nonce in HTML differs from header | Something echoes output before the hook, or Kirby's pages cache serves stale HTML — set `'cacheSafe' => true` |
| Header missing | `enabled` not `true`, or current host not in `hosts` |
| Vite bundle blocked | Add the `nonce` callable to the `lukaskleinschmidt.laravel-vite` config (step 3a) |
| Cookie banner script blocked | Override the plugin's `cookieconsentJs` snippet in `site/snippets/` with nonced `js()` calls (step 3b) |
| Map renders blank, worker error in console | Add `worker-src 'self' blob:` (Mapbox GL runs in a blob worker) |
| Consent-gated tags blocked after accepting cookies | Ensure the cookie-consent script itself is nonced — `'strict-dynamic'` then trusts what it re-injects; the `text/plain` tags themselves need no nonce |

> ⚠️ **Full-page caching caveat:** a cached page contains a stale nonce that won't match the fresh header, and with `'strict-dynamic'` that blocks every script on cache hits. If the project uses Kirby's pages cache (any driver — file, Redis, Memcached), set `'cacheSafe' => true`: the HTML is then cached with a stable placeholder and the real nonce is injected per request, cache hits included. Requires Kirby 4+.
>
> **Security note:** the default placeholder is a public constant, so if untrusted user-supplied HTML can reach cached pages, injected markup carrying the placeholder would receive a valid nonce after the swap. On sites that render untrusted HTML, set `'cacheSafePlaceholder'` to a random per-site secret (e.g. `bin2hex(random_bytes(16))`, generated once) and keep it stable — changing it requires flushing the pages cache. Sanitize untrusted HTML regardless; CSP is defense in depth, not a substitute.

## License

MIT — © E-xperience LAB
