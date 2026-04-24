<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\UserAgentParser;

#[CoversClass(UserAgentParser::class)]
class UserAgentParserTest extends TestCase
{
    public function testReturnsNullForNullInput(): void
    {
        $parser = new UserAgentParser();

        self::assertNull($parser->describe(null));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $parser = new UserAgentParser();

        self::assertNull($parser->describe(''));
        self::assertNull($parser->describe('   '));
    }

    public function testDescribesChromeOnMacOs(): void
    {
        $parser = new UserAgentParser();

        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

        self::assertSame('Chrome on Mac', $parser->describe($ua));
    }

    public function testDescribesFirefoxOnWindows(): void
    {
        $parser = new UserAgentParser();

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0';

        self::assertSame('Firefox on Windows', $parser->describe($ua));
    }

    public function testDescribesSafariOnIphone(): void
    {
        $parser = new UserAgentParser();

        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

        self::assertSame('Mobile Safari on iOS', $parser->describe($ua));
    }

    public function testReturnsNullForUnparsableGarbage(): void
    {
        $parser = new UserAgentParser();

        self::assertNull($parser->describe('---garbage---'));
    }
}
