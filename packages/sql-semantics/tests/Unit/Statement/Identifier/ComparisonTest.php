<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Comparison;

#[CoversClass(Comparison::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    public function testEqualIsExactForSensitiveComparison(): void
    {
        self::assertTrue(Comparison::Sensitive->equal('Users', 'Users'));
        self::assertFalse(Comparison::Sensitive->equal('Users', 'users'));
    }

    public function testEqualFoldsAsciiLettersOnlyForInsensitiveComparison(): void
    {
        self::assertTrue(Comparison::AsciiInsensitive->equal('Users', 'USERS'));
        self::assertFalse(Comparison::AsciiInsensitive->equal('Straße', 'STRASSE'));
        self::assertFalse(Comparison::AsciiInsensitive->equal('É', 'é'));
    }

    public function testFoldLowersAsciiLettersAndKeepsEveryOtherByte(): void
    {
        self::assertSame('abc_1 é', Comparison::AsciiInsensitive->fold('ABC_1 é'));
        self::assertSame('abc', Comparison::Sensitive->fold('ABC'));
    }
}
