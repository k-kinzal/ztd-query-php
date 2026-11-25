<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind as Kind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;

#[CoversClass(ForeignKeyScalarStorage::class)]
final class ForeignKeyScalarStorageTest extends TestCase
{
    public function testKeyBytesUsesTheFixedPointDimensionsAndRetainsTheAttributes(): void
    {
        $decimal = new ForeignKeyScalarStorage(Kind::Decimal, 22, 6, true, true);

        self::assertSame(Kind::Decimal, $decimal->kind);
        self::assertSame(22, $decimal->precision);
        self::assertSame(6, $decimal->scale);
        self::assertTrue($decimal->unsigned);
        self::assertTrue($decimal->zerofill);
        self::assertSame(11, $decimal->keyBytes());
    }

    public function testKeyBytesCountsBothDecimalGroupsAndFractionalSeconds(): void
    {
        self::assertSame(30, (new ForeignKeyScalarStorage(Kind::Decimal, 65, 30))->keyBytes());
        self::assertSame(2, (new ForeignKeyScalarStorage(Kind::Bit, 9))->keyBytes());
        self::assertSame(6, (new ForeignKeyScalarStorage(Kind::Time, 5))->keyBytes());
        self::assertSame(7, (new ForeignKeyScalarStorage(Kind::DateTime, 3))->keyBytes());
        self::assertSame(4, (new ForeignKeyScalarStorage(Kind::Timestamp, 0))->keyBytes());
        self::assertSame(8, (new ForeignKeyScalarStorage(Kind::Double))->keyBytes());
    }
}
