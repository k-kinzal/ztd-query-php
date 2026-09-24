<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateEventField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateEventField::class)]
#[Medium]
final class CreateEventFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Event', 'sql_mode', 'time_zone', 'Create Event', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (CreateEventField $field): string => $field->label(), CreateEventField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateEventField::cases()), 'varchar'), array_map(static fn (CreateEventField $field): string => $field->type(), CreateEventField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateEventField::cases()), Nullability::NotNull), array_map(static fn (CreateEventField $field): Nullability => $field->nullability(), CreateEventField::cases()));
    }
}
