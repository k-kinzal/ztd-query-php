<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Field::class)]
#[Small]
final class FieldTest extends TestCase
{
    public function testIntegralCodesIncludeYear(): void
    {
        self::assertSame([Field::Tiny, Field::Short, Field::Long, Field::LongLong, Field::Int24, Field::Year], array_values(array_filter(Field::cases(), static fn (Field $field): bool => $field->integral())));
    }

    public function testTemporalCodesAreDatesTimesAndTimestamps(): void
    {
        self::assertSame([Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::NewDate], array_values(array_filter(Field::cases(), static fn (Field $field): bool => $field->temporal())));
    }

    public function testBlobCodesIncludeJson(): void
    {
        self::assertSame([Field::Json, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob], array_values(array_filter(Field::cases(), static fn (Field $field): bool => $field->blob())));
    }

    public function testTypeNameNamesEveryCode(): void
    {
        self::assertSame(
            ['DECIMAL', 'TINYINT', 'SMALLINT', 'INT', 'FLOAT', 'DOUBLE', 'NULL', 'TIMESTAMP', 'BIGINT', 'MEDIUMINT', 'DATE', 'TIME', 'DATETIME', 'YEAR', 'DATE', 'VARCHAR', 'BIT', 'VECTOR', 'JSON', 'DECIMAL', 'ENUM', 'SET', 'TEXT', 'TEXT', 'TEXT', 'TEXT', 'VARCHAR', 'CHAR', 'GEOMETRY'],
            array_map(static fn (Field $field): string => $field->typeName(false), Field::cases()),
        );
    }

    public function testTypeNameNamesTheBinaryFormOfAStringCode(): void
    {
        self::assertSame(['VARBINARY', 'VARBINARY', 'BINARY', 'BLOB', 'BLOB', 'INT'], [Field::VarChar->typeName(true), Field::VarString->typeName(true), Field::String->typeName(true), Field::TinyBlob->typeName(true), Field::LongBlob->typeName(true), Field::Long->typeName(true)]);
    }
}
