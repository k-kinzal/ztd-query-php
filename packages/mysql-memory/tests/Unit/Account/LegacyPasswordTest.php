<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\LegacyPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyPassword::class)]
#[Small]
final class LegacyPasswordTest extends TestCase
{
    #[TestWith(['', ''])]
    #[TestWith(['a', '60671c896665c3fa'])]
    #[TestWith(['a b', '077ba8cb491e16c5'])]
    #[TestWith(["a\tb", '077ba8cb491e16c5'])]
    public function testHashKeepsTheObservedLegacyValue(string $text, string $expected): void
    {
        self::assertSame($expected, (new LegacyPassword())->hash($text));
    }
}
