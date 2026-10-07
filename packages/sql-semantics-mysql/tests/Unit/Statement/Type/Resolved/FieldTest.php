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
}
