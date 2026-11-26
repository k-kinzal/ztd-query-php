<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(ForeignKeyScalarKind::class)]
final class ForeignKeyScalarKindTest extends TestCase
{
    public function testPackedSelectsThePhysicalByteComparisonFamily(): void
    {
        $packed = array_filter(ForeignKeyScalarKind::cases(), static fn (ForeignKeyScalarKind $kind): bool => $kind->packed());

        self::assertSame(['DECIMAL', 'BIT', 'TIME', 'DATETIME', 'TIMESTAMP'], array_column($packed, 'value'));
    }

    public function testAcceptsTheNativeIntegralWidthAndSign(): void
    {
        self::assertTrue(ForeignKeyScalarKind::Year->accepts(new ForeignKeyIntegerDomain(IntegerStorage::Tiny, true)));
        self::assertFalse(ForeignKeyScalarKind::Year->accepts(new ForeignKeyIntegerDomain(IntegerStorage::Tiny, false)));
        self::assertTrue(ForeignKeyScalarKind::Date->accepts(new ForeignKeyIntegerDomain(IntegerStorage::Medium, false)));
        self::assertFalse(ForeignKeyScalarKind::Date->accepts(new ForeignKeyIntegerDomain(IntegerStorage::Medium, true)));
        self::assertTrue(ForeignKeyScalarKind::Bit->accepts(new ForeignKeyScalarStorage(ForeignKeyScalarKind::Time, 6)));
    }

    public function testDimensionsDistinguishesOptionalAndRequiredPrecision(): void
    {
        self::assertSame([1, 65, true, false], ForeignKeyScalarKind::Decimal->dimensions());
        self::assertSame([1, 255, true, true], ForeignKeyScalarKind::Double->dimensions());
        self::assertSame([0, 6, false, false], ForeignKeyScalarKind::Timestamp->dimensions());
        self::assertSame([null, null, false, false], ForeignKeyScalarKind::Date->dimensions());
    }
}
