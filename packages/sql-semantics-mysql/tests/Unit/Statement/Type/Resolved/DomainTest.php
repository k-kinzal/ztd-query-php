<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Domain::class)]
#[Small]
final class DomainTest extends TestCase
{
    public function testIntegerCarriesTheBinaryCollationAndNumericCoercibility(): void
    {
        $domain = Domain::integer(Field::Long, 11);

        self::assertSame([Kind::Integer, Field::Long, 11, 0, false], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->unsigned]);
        self::assertSame(Collation::binary(), $domain->collation);
        self::assertSame(Coercibility::Numeric, $domain->coercibility);
        self::assertSame(Coercibility::Numeric, Domain::double()->coercibility);
    }

    public function testDecimalLengthCountsTheSignAndThePoint(): void
    {
        self::assertSame(7, Domain::decimal(5, 2)->length);
        self::assertSame(6, Domain::decimal(5, 2, true)->length);
        self::assertSame(6, Domain::decimal(5, 0)->length);
    }

    public function testPrecisionCountsDigitsOnly(): void
    {
        self::assertSame(5, Domain::decimal(5, 2)->precision());
        self::assertSame(5, Domain::decimal(5, 2, true)->precision());
    }

    public function testDoubleHasNoFixedDecimalsByDefault(): void
    {
        self::assertSame([22, Domain::NOT_FIXED], [Domain::double()->length, Domain::double()->decimals]);
    }

    public function testStringReportsItsByteLengthInItsCharacterSet(): void
    {
        $domain = Domain::string(10, Collation::known('utf8mb4_bin'));

        self::assertSame(40, $domain->byteLength());
        self::assertSame(10, Domain::string(10, Collation::binary())->byteLength());
    }

    public function testByteLengthOfNumbersIsTheirLength(): void
    {
        self::assertSame(21, Domain::integer()->byteLength());
    }

    public function testColumnReportsADisplayWidthNarrowerThanItsType(): void
    {
        self::assertSame([11, 5], [Domain::column(Field::Long, 5)->length, Domain::column(Field::Long, 5)->display]);
        self::assertSame([10, null], [Domain::column(Field::Long, 10, true)->length, Domain::column(Field::Long, 10, true)->display]);
        self::assertSame([20, 3], [Domain::column(Field::LongLong, 3, true)->length, Domain::column(Field::LongLong, 3, true)->display]);
        self::assertSame([4, 2], [Domain::column(Field::Tiny, 2)->length, Domain::column(Field::Tiny, 2)->display]);
        self::assertSame([21, null], [Domain::column(Field::LongLong, 21)->length, Domain::column(Field::LongLong, 21)->display]);
    }

    public function testValueDropsTheDisplayWidth(): void
    {
        self::assertEquals(Domain::integer(Field::Short, 6), Domain::column(Field::Short, 2)->value());
        self::assertSame(Domain::integer()->kind, Domain::integer()->value()->kind);
    }

    public function testNullIsIgnorable(): void
    {
        self::assertSame([Kind::Null, Field::Null, Coercibility::Ignorable], [Domain::null()->kind, Domain::null()->field, Domain::null()->coercibility]);
    }

    public function testWithCollationKeepsTheOtherAttributes(): void
    {
        $domain = Domain::string(3, Collation::binary())->withCollation(Collation::known('latin1_bin'), Coercibility::Explicit);

        self::assertSame([3, 'latin1_bin', Coercibility::Explicit], [$domain->length, $domain->collation->name, $domain->coercibility]);
    }

    public function testNameFollowsTheFieldAndTheCollation(): void
    {
        $utf8 = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame('BIGINT', Domain::integer()->name());
        self::assertSame('DECIMAL', Domain::decimal(1, 0)->name());
        self::assertSame('VARCHAR', Domain::string(1, $utf8)->name());
        self::assertSame('VARBINARY', Domain::string(1, Collation::binary())->name());
        self::assertSame('CHAR', Domain::string(1, $utf8, Field::String)->name());
        self::assertSame('BINARY', Domain::string(1, Collation::binary(), Field::String)->name());
        self::assertSame('TEXT', Domain::string(1, $utf8, Field::Blob)->name());
        self::assertSame('BLOB', Domain::string(1, Collation::binary(), Field::MediumBlob)->name());
        self::assertSame('NULL', Domain::null()->name());
        self::assertSame('ENUM', (new Domain(Kind::String, Field::Enum, 1, Domain::NOT_FIXED, false, $utf8, ['a']))->name());
    }

    public function testDeclaredNamesTheTypeOfTheSameClass(): void
    {
        self::assertSame('INT', Domain::integer(Field::Long, 11)->declared()->name());
        self::assertSame('DOUBLE', Domain::double()->declared()->name());
        self::assertSame('TIMESTAMP', (new Domain(Kind::DateTime, Field::Timestamp, 19))->declared()->name());
        self::assertSame('JSON', (new Domain(Kind::Json, Field::Json, 4294967295))->declared()->name());
    }

    public function testTextNamesTheStringTypeOfTheFieldAndCharacterSet(): void
    {
        self::assertSame('MEDIUMBLOB', Domain::string(1, Collation::binary(), Field::MediumBlob)->text()->name());
        self::assertSame('LONGTEXT', Domain::string(1, Collation::known('utf8mb4_bin'), Field::LongBlob)->text()->name());
        self::assertSame('GEOMETRY', Domain::string(1, Collation::binary(), Field::Geometry)->text()->name());
    }

}
