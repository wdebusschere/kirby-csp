<?php

namespace Akibeo\Csp\Tests;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function testCspNonceIsRegistered(): void
    {
        $this->assertTrue(function_exists('cspNonce'));
    }

    public function testCspNonceIsMemoizedWithinARequest(): void
    {
        $this->assertSame(cspNonce(), cspNonce());
    }

    public function testCspNonceIsValidBase64(): void
    {
        $nonce = cspNonce();

        $this->assertNotEmpty($nonce);
        // Decodable base64 that round-trips exactly.
        $this->assertSame($nonce, base64_encode(base64_decode($nonce, true)));
    }
}
