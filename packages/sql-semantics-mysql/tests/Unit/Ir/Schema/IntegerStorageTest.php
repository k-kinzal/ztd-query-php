<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(IntegerStorage::class)]
#[Small]
final class IntegerStorageTest extends TestCase
{
    public function testIdentifiesAllFiveSignedStorageRoles(): void
    {
        self::assertSame(['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT'], array_map(static fn (IntegerStorage $kind): string => $kind->value, IntegerStorage::cases()));
    }

    public function testBytesMatchesTheNativeStorageWidth(): void
    {
        self::assertSame([1, 2, 3, 4, 8], array_map(static fn (IntegerStorage $kind): int => $kind->bytes(), IntegerStorage::cases()));
    }

    public function testAcceptsTheCorrectSignedStorageRanges(): void
    {
        $value = new RangeIntegerLiteral('-9223372036854775808');
        self::assertTrue(IntegerStorage::Wide->accepts($value));
        self::assertFalse(IntegerStorage::Standard->accepts($value));
        self::assertTrue(IntegerStorage::Medium->accepts(new RangeIntegerLiteral('-8388608')));
        self::assertFalse(IntegerStorage::Medium->accepts(new RangeIntegerLiteral('8388608')));
    }
}
