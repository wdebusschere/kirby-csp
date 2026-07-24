<?php

/**
 * One CSP nonce per request, memoized so the header and templates match.
 *
 * Use it on inline scripts that must survive the strict CSP:
 * <script nonce="{{ cspNonce() }}">...</script>
 *
 * @return string Base64-encoded 128-bit random nonce
 */
if (!function_exists('cspNonce')) {
    function cspNonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }
}
