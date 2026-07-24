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
     * Reserved path prefixes that never receive a CSP header. The Panel ships
     * its own inline scripts that a strict nonce policy would block, and
     * API/media responses don't need a CSP.
     *
     * @param string $path      The current Kirby request path, e.g. "aanbod/te-koop".
     * @param string $panelSlug The configured panel slug (panel.slug option).
     */
    public static function isReservedPath(string $path, string $panelSlug = 'panel'): bool
    {
        foreach ([$panelSlug, 'api', 'media'] as $reserved) {
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
            $policy[] = trim($directive . ' ' . $value) . ';';
        }

        if ($policy === []) {
            return null;
        }

        return implode(' ', $policy);
    }
}
