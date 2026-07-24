<?php

namespace Akibeo\Csp;

/**
 * Pure helpers behind the CSP plugin's route:before hook.
 *
 * These are deliberately free of Kirby dependencies so the header-building
 * logic can be unit tested without bootstrapping the CMS.
 */
class Csp
{
    /**
     * Stable stand-in for the per-request nonce inside cached HTML. In
     * cacheSafe mode the rendered HTML is cached with this placeholder and
     * the real nonce is swapped in on every response, cache hits included.
     *
     * The placeholder never appears in a response — only inside the cache —
     * but this default value is public knowledge (it's in this open-source
     * file). Anyone who can inject HTML into cached content could include it
     * and receive a valid nonce after the swap. Sites that render
     * user-supplied HTML should set a per-site secret via the
     * `cacheSafePlaceholder` option instead.
     */
    public const NONCE_PLACEHOLDER = '__akibeo_csp_nonce__';

    /**
     * Replace every occurrence of the request's real nonce with the stable
     * placeholder, so the HTML can be cached without a baked-in nonce.
     */
    public static function insertPlaceholder(string $html, string $nonce, ?string $placeholder = null): string
    {
        return str_replace($nonce, $placeholder ?? self::NONCE_PLACEHOLDER, $html);
    }

    /**
     * Replace every placeholder with the current request's real nonce.
     */
    public static function replacePlaceholder(string $html, string $nonce, ?string $placeholder = null): string
    {
        return str_replace($placeholder ?? self::NONCE_PLACEHOLDER, $nonce, $html);
    }

    /**
     * Reserved path prefixes that never receive a CSP header. The Panel ships
     * its own inline scripts that a strict nonce policy would block, and
     * API/media responses don't need a CSP.
     *
     * @param string $path      The current Kirby request path, e.g. "aanbod/te-koop".
     * @param string $panelSlug The configured panel slug (panel.slug option).
     * @param string $apiSlug   The configured API slug (api.slug option).
     */
    public static function isReservedPath(string $path, string $panelSlug = 'panel', string $apiSlug = 'api'): bool
    {
        foreach ([$panelSlug, $apiSlug, 'media'] as $reserved) {
            if ($path === $reserved || str_starts_with($path, $reserved . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise a host for comparison against the `hosts` allowlist:
     * lowercased and without any :port suffix.
     */
    public static function normalizeHost(string $host): string
    {
        return preg_replace('/:\d+$/', '', strtolower($host));
    }

    /**
     * Compile an associative array of directives into a single header value.
     * Every `{nonce}` placeholder is replaced with the per-request nonce.
     *
     * @param array<string, string> $directives directive => value
     * @param string                $nonce      the per-request nonce
     *
     * @return string|null the header value, or null when there are no directives
     */
    public static function compile(array $directives, string $nonce): ?string
    {
        $policy = [];

        foreach ($directives as $directive => $value) {
            $value = str_replace('{nonce}', $nonce, (string) $value);
            // A CR/LF in a header value makes PHP's header() drop the header
            // silently — fold stray newlines into spaces instead.
            $policy[] = trim(preg_replace('/\s+/', ' ', $directive . ' ' . $value)) . ';';
        }

        if ($policy === []) {
            return null;
        }

        return implode(' ', $policy);
    }
}
