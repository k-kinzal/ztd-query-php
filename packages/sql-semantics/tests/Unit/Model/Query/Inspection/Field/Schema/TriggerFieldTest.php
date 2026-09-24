<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\TriggerField;
use SqlSemantics\Type\Nullability;

#[CoversClass(TriggerField::class)]
#[Medium]
final class TriggerFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Trigger', 'Event', 'Table', 'Statement', 'Timing', 'Created', 'sql_mode', 'Definer', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (TriggerField $field): string => $field->label(), TriggerField::cases()));
    }

    #[TestWith([TriggerField::Created, 'timestamp'])]
    #[TestWith([TriggerField::Name, 'varchar'])]
    #[TestWith([TriggerField::Statement, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(TriggerField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([TriggerField::Created, Nullability::MaybeNull])]
    #[TestWith([TriggerField::Name, Nullability::NotNull])]
    #[TestWith([TriggerField::Definer, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(TriggerField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }
}
