<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Domain::class)]
#[Small]
final class DomainTest extends TestCase
{
    public function testWithNumericBytesKeepsTheRestOfTheDomain(): void
    {
        $domain = Domain::string(2, Collation::binary());

        $numeric = $domain->withNumericBytes();

        self::assertSame([false, true, false, 2, Field::VarString], [$domain->numericBytes, $numeric->numericBytes, $numeric->withNumericBytes(false)->numericBytes, $numeric->length, $numeric->field]);
    }

    public function testOfTakesTheResolvedTypeAndANullability(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $resolved = Domain::string(10, $collation)->withCollation($collation, Coercibility::Coercible)->resolved();

        $domain = Domain::of($resolved, true);

        self::assertSame([Kind::String, Field::VarString, 10, 31, $collation, true, Coercibility::Coercible], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->collation, $domain->nullable, $domain->coercibility]);
    }

    public function testOfKeepsTheDisplayWidthOfAColumn(): void
    {
        $domain = Domain::of(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::column(Field::Long, 2), false);

        self::assertSame([11, 2, 2, 2], [$domain->length, $domain->display, $domain->byteLength(), $domain->withNullable(true)->resolved()->display]);
    }

    public function testResolvedDropsTheNullability(): void
    {
        $resolved = Domain::decimal(10, 2, true)->resolved();

        self::assertSame([Kind::Decimal, Field::NewDecimal, 11, 2, true], [$resolved->kind, $resolved->field, $resolved->length, $resolved->decimals, $resolved->unsigned]);
    }

    public function testIntegerIsANotNullBinaryNumber(): void
    {
        $domain = Domain::integer(Field::Long, 11);

        self::assertSame([Kind::Integer, Field::Long, 11, 0, false, false, 'binary'], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->unsigned, $domain->nullable, $domain->collation->name]);
    }

    public function testDecimalCountsTheSignAndThePointInTheLength(): void
    {
        self::assertSame([12, 11, 10, 11], [Domain::decimal(10, 2)->length, Domain::decimal(10, 2, true)->length, Domain::decimal(10, 0, true)->length, Domain::decimal(10, 0)->length]);
    }

    public function testDoubleHasNoFixedDecimalsByDefault(): void
    {
        $domain = Domain::double();

        self::assertSame([Kind::Double, Field::Double, 22, Domain::NOT_FIXED, false], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->nullable]);
    }

    public function testStringHoldsTheCollation(): void
    {
        $collation = Collation::known('latin1_swedish_ci');

        $domain = Domain::string(5, $collation, Field::String);

        self::assertSame([Kind::String, Field::String, 5, Domain::NOT_FIXED, $collation, Coercibility::Implicit], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->collation, $domain->coercibility]);
    }

    public function testNullIsIgnorable(): void
    {
        $domain = Domain::null();

        self::assertSame([Kind::Null, Field::Null, true, Coercibility::Ignorable], [$domain->kind, $domain->field, $domain->nullable, $domain->coercibility]);
    }

    public function testWithNullableAnswersTheSameDomainWhenNothingChanges(): void
    {
        $domain = Domain::integer();

        self::assertSame([$domain, true, Field::LongLong], [$domain->withNullable(false), $domain->withNullable(true)->nullable, $domain->withNullable(true)->field]);
    }

    public function testWithCollationReplacesTheCollationAndCoercibility(): void
    {
        $domain = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))->withCollation(Collation::known('utf8mb4_bin'), Coercibility::Explicit);

        self::assertSame(['utf8mb4_bin', Coercibility::Explicit, 3], [$domain->collation->name, $domain->coercibility, $domain->length]);
    }

    public function testPrecisionCountsTheDigitsOfADecimal(): void
    {
        self::assertSame([10, 10, 5, 1], [Domain::decimal(10, 2)->precision(), Domain::decimal(10, 0, true)->precision(), Domain::decimal(5, 5)->precision(), Domain::decimal(0, 0)->precision()]);
    }

    public function testByteLengthMultipliesTheLengthOfAStringByItsWidestCharacter(): void
    {
        self::assertSame([40, 10, 10, 7], [Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'))->byteLength(), Domain::string(10, Collation::known('latin1_swedish_ci'))->byteLength(), (new Domain(Kind::Date, Field::Date, 10))->byteLength(), Domain::decimal(5, 2)->byteLength()]);
    }

    public function testByteLengthReportsTheDisplayWidthOfAnIntegerColumn(): void
    {
        self::assertSame([5, 11], [(new Domain(Kind::Integer, Field::Long, 11, 0, false, null, true, [], Coercibility::Numeric, false, 5))->byteLength(), Domain::integer(Field::Long, 11)->byteLength()]);
    }

    public function testFlagsDescribeTheColumnDefinition(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([32897, 32929, 1, 129, 256, 2048], [Domain::integer(Field::Long, 11)->flags(), Domain::integer(Field::LongLong, 20, true)->flags(), Domain::string(10, $collation)->flags(), Domain::string(10, Collation::binary())->flags(), (new Domain(Kind::String, Field::Enum, 1, Domain::NOT_FIXED, false, $collation, true, ['a']))->flags(), (new Domain(Kind::String, Field::Set, 1, Domain::NOT_FIXED, false, $collation, true, ['a']))->flags()]);
    }

    public function testWithQuietKeepsTheQuietnessThroughOtherChanges(): void
    {
        $quiet = Domain::string(3, Collation::known('latin1_swedish_ci'))->withQuiet();

        self::assertTrue($quiet->quiet);
        self::assertTrue($quiet->withNullable(true)->quiet);
        self::assertFalse($quiet->withQuiet(false)->quiet);
        self::assertSame($quiet, $quiet->withQuiet());
    }

    public function testWithSourceNamesWhereAJsonValueComesFrom(): void
    {
        $domain = Domain::integer();

        self::assertSame(['j', $domain], [$domain->withSource('j')->source, $domain->withSource('')]);
    }

    public function testByteLengthRetainsTheByteBoundsOfBlobFields(): void
    {
        $fields = [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob];
        $lengths = array_map(static fn (Field $field): int => Domain::string(100, Collation::known('utf8mb4_general_ci'), $field)->byteLength(), $fields);

        self::assertSame([100, 100, 100, 100], $lengths);
    }

}
