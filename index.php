<?php

use Akibeo\Csp\Csp;
use Kirby\Cms\App as Kirby;

// Composer autoload when installed as a package; plain requires when the
// folder is dropped straight into site/plugins/. Both are idempotent.
@include_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Csp.php';

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
        'route:before' => function () {
            $kirby = kirby();

            if ($kirby->option('akibeo.csp.enabled') !== true) {
                return;
            }

            // Frontend only — skip the Panel, API and media routes.
            $reserved = Csp::isReservedPath(
                $kirby->path(),
                $kirby->option('panel.slug', 'panel'),
                $kirby->option('api.slug', 'api')
            );

            if ($reserved) {
                return;
            }

            $hosts = $kirby->option('akibeo.csp.hosts', []);
            if ($hosts !== []) {
                $host = Csp::normalizeHost($kirby->environment()->host());

                if (in_array($host, array_map('strtolower', $hosts), true) === false) {
                    return;
                }
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
        },
    ],
]);
