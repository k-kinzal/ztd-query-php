<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\MemoryQuantity;

#[CoversClass(MemoryQuantity::class)]
final class MemoryQuantityTest extends TestCase
{
    #[TestWith(['256', 256])]
    #[TestWith([' 2MB ', 2048])]
    #[TestWith(['1.5MB', 1536])]
    #[TestWith(['1GB', 1048576])]
    #[TestWith(['1TB', 1073741824])]
    #[TestWith(['2048B', 2])]
    #[TestWith(['0x100', 256])]
    #[TestWith(['0200', 128])]
    #[TestWith(['-1', -1])]
    #[TestWith(['1e3', 1000])]
    #[TestWith(['0x10 kB', 16])]
    #[TestWith(['0X10', 16])]
    #[TestWith(['107', 107])]
    #[TestWith(['09.5', 10])]
    #[TestWith(['5kB', 5])]
    #[TestWith(['1.5', 2])]
    #[TestWith(['2.5', 2])]
    #[TestWith(['1536B', 2])]
    #[TestWith(['2560B', 2])]
    #[TestWith(['2147483647', 2147483647])]
    #[TestWith(['-2147483648', -2147483648])]
    public function testKilobytesReadsIntegerParameterSyntax(string $text, int $expected): void
    {
        self::assertSame($expected, MemoryQuantity::kilobytes($text));
    }

    #[TestWith(['2mb'])]
    #[TestWith(['x5'])]
    #[TestWith(['0128'])]
    #[TestWith(['2147483648'])]
    #[TestWith(['08'])]
    #[TestWith(['abc'])]
    #[TestWith(['4TB'])]
    #[TestWith([''])]
    public function testKilobytesRejectsUnreadableQuantities(string $text): void
    {
        self::assertNull(MemoryQuantity::kilobytes($text));
    }
}
