<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;

#[CoversClass(Builtin::class)]
#[Small]
final class BuiltinTest extends TestCase
{
    public function testValuesAreCanonicalSpellingsWithoutDuplicates(): void
    {
        $values = array_column(Builtin::cases(), 'value');
        self::assertSame($values, array_values(array_unique($values)));
        self::assertSame('double precision', Builtin::DoublePrecision->value);
        self::assertSame('timestamp with time zone', Builtin::TimestampTz->value);
    }

    #[DataProvider('providerFamilies')]
    public function testIsNumericIsCharacterIsBinaryAndIsTemporalPartitionTheTypes(Builtin $type, bool $numeric, bool $character, bool $binary, bool $temporal): void
    {
        self::assertSame($numeric, $type->isNumeric());
        self::assertSame($character, $type->isCharacter());
        self::assertSame($binary, $type->isBinary());
        self::assertSame($temporal, $type->isTemporal());
    }

    public function testIsNumericCoversExactAndApproximateNumbersOnly(): void
    {
        self::assertSame([Builtin::TinyInt, Builtin::SmallInt, Builtin::MediumInt, Builtin::Integer, Builtin::BigInt, Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision], array_values(array_filter(Builtin::cases(), static fn (Builtin $type): bool => $type->isNumeric())));
    }

    public function testIsCharacterCoversStringAndEnumerationTypes(): void
    {
        self::assertSame([Builtin::Char, Builtin::VarChar, Builtin::TinyText, Builtin::Text, Builtin::MediumText, Builtin::LongText, Builtin::Enum, Builtin::Set], array_values(array_filter(Builtin::cases(), static fn (Builtin $type): bool => $type->isCharacter())));
    }

    public function testIsBinaryCoversOctetStringTypes(): void
    {
        self::assertSame([Builtin::Binary, Builtin::VarBinary, Builtin::TinyBlob, Builtin::Blob, Builtin::MediumBlob, Builtin::LongBlob, Builtin::Bytea], array_values(array_filter(Builtin::cases(), static fn (Builtin $type): bool => $type->isBinary())));
    }

    public function testIsTemporalCoversDatesTimesAndDurationsWithASecondsPrecision(): void
    {
        self::assertSame([Builtin::Date, Builtin::Time, Builtin::TimeTz, Builtin::DateTime, Builtin::Timestamp, Builtin::TimestampTz, Builtin::Interval], array_values(array_filter(Builtin::cases(), static fn (Builtin $type): bool => $type->isTemporal())));
    }

    /**
     * @return iterable<string, array{Builtin, bool, bool, bool, bool}>
     */
    public static function providerFamilies(): iterable
    {
        yield 'integer' => [Builtin::Integer, true, false, false, false];
        yield 'numeric' => [Builtin::Numeric, true, false, false, false];
        yield 'varchar' => [Builtin::VarChar, false, true, false, false];
        yield 'enum' => [Builtin::Enum, false, true, false, false];
        yield 'bytea' => [Builtin::Bytea, false, false, true, false];
        yield 'timestamp' => [Builtin::TimestampTz, false, false, false, true];
        yield 'unknown' => [Builtin::Unknown, false, false, false, false];
        yield 'boolean' => [Builtin::Boolean, false, false, false, false];
        yield 'json' => [Builtin::Json, false, false, false, false];
    }
}
