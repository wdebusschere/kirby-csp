<?php

namespace Akibeo\Csp\Tests;

use Akibeo\Csp\Csp;
use PHPUnit\Framework\TestCase;

class CspTest extends TestCase
{
    public function testCompileReplacesNoncePlaceholder(): void
    {
        $header = Csp::compile(['script-src' => "'self' 'nonce-{nonce}'"], 'ABC123==');

        $this->assertSame("script-src 'self' 'nonce-ABC123==';", $header);
    }

    public function testCompileReplacesEveryNonceOccurrence(): void
    {
        $header = Csp::compile([
            'script-src' => "'nonce-{nonce}'",
            'style-src' => "'nonce-{nonce}'",
        ], 'X');

        $this->assertSame("script-src 'nonce-X'; style-src 'nonce-X';", $header);
    }

    public function testCompileJoinsDirectivesWithTrailingSemicolons(): void
    {
        $header = Csp::compile([
            'default-src' => "'self'",
            'object-src' => "'none'",
        ], 'n');

        $this->assertSame("default-src 'self'; object-src 'none';", $header);
    }

    public function testCompileTrimsDirectiveWithEmptyValue(): void
    {
        $header = Csp::compile(['upgrade-insecure-requests' => ''], 'n');

        $this->assertSame('upgrade-insecure-requests;', $header);
    }

    public function testCompileReturnsNullWhenNoDirectives(): void
    {
        $this->assertNull(Csp::compile([], 'n'));
    }

    public function testCompileFoldsNewlinesIntoSpaces(): void
    {
        // A CR/LF would make PHP's header() drop the header silently.
        $header = Csp::compile(['script-src' => "'self'\r\nhttps://example.com"], 'n');

        $this->assertSame("script-src 'self' https://example.com;", $header);
    }

    public function testIsReservedPathMatchesPanelApiAndMedia(): void
    {
        $this->assertTrue(Csp::isReservedPath('panel'));
        $this->assertTrue(Csp::isReservedPath('panel/pages/home'));
        $this->assertTrue(Csp::isReservedPath('api/properties'));
        $this->assertTrue(Csp::isReservedPath('media/plugins/x.js'));
    }

    public function testIsReservedPathHonoursCustomPanelSlug(): void
    {
        $this->assertTrue(Csp::isReservedPath('cp/pages', 'cp'));
        // The default panel slug no longer matches when a custom one is set.
        $this->assertFalse(Csp::isReservedPath('panel/pages', 'cp'));
    }

    public function testIsReservedPathHonoursCustomApiSlug(): void
    {
        $this->assertTrue(Csp::isReservedPath('v1/properties', 'panel', 'v1'));
        // The default API slug no longer matches when a custom one is set.
        $this->assertFalse(Csp::isReservedPath('api/properties', 'panel', 'v1'));
    }

    public function testIsReservedPathDoesNotMatchOnPrefixAlone(): void
    {
        $this->assertFalse(Csp::isReservedPath('panels'));
        $this->assertFalse(Csp::isReservedPath('aanbod/te-koop'));
        $this->assertFalse(Csp::isReservedPath(''));
    }

    public function testNormalizeHostLowercasesAndStripsPort(): void
    {
        $this->assertSame('example.com', Csp::normalizeHost('Example.com:8080'));
        $this->assertSame('www.example.com', Csp::normalizeHost('WWW.Example.com'));
    }
}
