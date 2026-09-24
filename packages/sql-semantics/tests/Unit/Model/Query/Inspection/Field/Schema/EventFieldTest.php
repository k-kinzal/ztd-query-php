<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\EventField;
use SqlSemantics\Type\Nullability;

#[CoversClass(EventField::class)]
#[Medium]
final class EventFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Db', 'Name', 'Definer', 'Time zone', 'Type', 'Execute at', 'Interval value', 'Interval field', 'Starts', 'Ends', 'Status', 'Originator', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (EventField $field): string => $field->label(), EventField::cases()));
    }

    #[TestWith([EventField::ExecuteAt, 'datetime'])]
    #[TestWith([EventField::Starts, 'datetime'])]
    #[TestWith([EventField::Ends, 'datetime'])]
    #[TestWith([EventField::Originator, 'bigint'])]
    #[TestWith([EventField::Name, 'varchar'])]
    #[TestWith([EventField::IntervalValue, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(EventField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([EventField::ExecuteAt, Nullability::MaybeNull])]
    #[TestWith([EventField::IntervalValue, Nullability::MaybeNull])]
    #[TestWith([EventField::IntervalField, Nullability::MaybeNull])]
    #[TestWith([EventField::Starts, Nullability::MaybeNull])]
    #[TestWith([EventField::Ends, Nullability::MaybeNull])]
    #[TestWith([EventField::Name, Nullability::NotNull])]
    #[TestWith([EventField::Originator, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(EventField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testTypeAndNullabilityDescribeEveryField(): void
    {
        self::assertSame(['varchar', 'varchar', 'varchar', 'varchar', 'varchar', 'datetime', 'varchar', 'varchar', 'datetime', 'datetime', 'varchar', 'bigint', 'varchar', 'varchar', 'varchar'], array_map(static fn (EventField $field): string => $field->type(), EventField::cases()));
        self::assertSame(['NotNull', 'NotNull', 'NotNull', 'NotNull', 'NotNull', 'MaybeNull', 'MaybeNull', 'MaybeNull', 'MaybeNull', 'MaybeNull', 'NotNull', 'NotNull', 'NotNull', 'NotNull', 'NotNull'], array_map(static fn (EventField $field): string => $field->nullability()->name, EventField::cases()));
    }
}
