<?php

use Akibeo\Csp\Csp;
use Kirby\Cms\App as Kirby;

// Composer autoload when installed as a package; plain requires when the
// folder is dropped straight into site/plugins/. Both are idempotent.
@include_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Csp.php';

// Whether the CSP applies to the current request: enabled, not a reserved
// path (Panel/API/media), and the host passes the allowlist. Shared by the
// header hook and the cacheSafe render hook so they always agree.
$cspApplies = function (Kirby $kirby): bool {
    if ($kirby->option('akibeo.csp.enabled') !== true) {
        return false;
    }

    $reserved = Csp::isReservedPath(
        $kirby->path(),
        $kirby->option('panel.slug', 'panel'),
        $kirby->option('api.slug', 'api')
    );

    if ($reserved) {
        return false;
    }

    $hosts = $kirby->option('akibeo.csp.hosts', []);
    if ($hosts !== []) {
        $host = Csp::normalizeHost($kirby->environment()->host());

        if (in_array($host, array_map('strtolower', $hosts), true) === false) {
            return false;
        }
    }

    return true;
};

Kirby::plugin('akibeo/csp', [
    'options' => [
        // Opt-in: enable per host config (config.<host>.php)
        'enabled' => false,

        // Only send the header on these hosts; empty array = all hosts.
        // Hosts are compared lowercase and without port.
        'hosts' => [],

        // Send Content-Security-Policy-Report-Only instead of enforcing,
        // useful to test a policy against production traffic first.
        'reportOnly' => false,

        // Opt-in for sites using Kirby's pages cache: without this, cached
        // HTML keeps the nonce of the request that warmed the cache and no
        // longer matches the (always fresh) header nonce, blocking every
        // script on cache hits. With cacheSafe the HTML is cached with a
        // stable placeholder instead, swapped for the real nonce on every
        // response — cache hits included — via an output buffer. Templates
        // keep using cspNonce() as usual.
        'cacheSafe' => false,

        // A deliberately minimal, vendor-neutral strict baseline: everything
        // is 'self' plus the nonce mechanism. Add third-party origins
        // (Google Fonts, Analytics, Maps, Mapbox, embeds, …) per project by
        // overriding single directives from config.php — they merge over
        // these defaults. See README.md / SETUP.md for ready-made examples.
        //
        // The {nonce} placeholder is replaced with the per-request nonce
        // from cspNonce():
        // 'akibeo.csp' => ['directives' => ['frame-src' => "'self' https://www.youtube.com"]]
        'directives' => [
            'default-src' => "'self'",
            // 'strict-dynamic' + nonce is the effective policy in modern
            // browsers; https: and 'unsafe-inline' are ignored by any
            // browser that understands nonces and only serve as fallback
            // for legacy browsers (per Google's strict CSP guidance).
            'script-src' => "'self' 'nonce-{nonce}' 'strict-dynamic' https: 'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'manifest-src' => "'self'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
            'object-src' => "'none'",
        ],
    ],

    'hooks' => [
        'route:before' => function () use ($cspApplies) {
            $kirby = kirby();

            if ($cspApplies($kirby) === false) {
                return;
            }

            $header = Csp::compile(
                $kirby->option('akibeo.csp.directives', []),
                cspNonce()
            );

            if ($header === null) {
                return;
            }

            $name = $kirby->option('akibeo.csp.reportOnly') === true
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            // route:before can fire more than once per request (route
            // fallthrough); replace: true keeps the header single even then.
            header($name . ': ' . $header, true);

            if ($kirby->option('akibeo.csp.cacheSafe') === true) {
                // Swap the nonce placeholder in the response body for this
                // request's real nonce at flush time. This is the only point
                // that runs on pages-cache hits too — page.render:after does
                // not (its output is what gets cached).
                static $buffering = false;

                if ($buffering === false) {
                    $buffering = true;
                    ob_start(
                        fn (string $buffer) => Csp::replacePlaceholder($buffer, cspNonce())
                    );
                }
            }
        },

        // Runs on cache misses only; the returned HTML is what the pages
        // cache stores. Strip the request's real nonce down to the stable
        // placeholder so the cached HTML is nonce-free — the output buffer
        // above puts the fresh nonce back on every response.
        'page.render:after' => function (string $contentType, array $data, string $html) use ($cspApplies) {
            $kirby = kirby();

            if ($contentType !== 'html') {
                return $html;
            }

            if ($kirby->option('akibeo.csp.cacheSafe') !== true || $cspApplies($kirby) === false) {
                return $html;
            }

            // No directives → no header and no output buffer in route:before,
            // so leave the HTML alone too.
            if ($kirby->option('akibeo.csp.directives', []) === []) {
                return $html;
            }

            return Csp::insertPlaceholder($html, cspNonce());
        },
    ],
]);
