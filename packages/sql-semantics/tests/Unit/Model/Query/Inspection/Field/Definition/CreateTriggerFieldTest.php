<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTriggerField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateTriggerField::class)]
#[Medium]
final class CreateTriggerFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Trigger', 'sql_mode', 'SQL Original Statement', 'character_set_client', 'collation_connection', 'Database Collation', 'Created'], array_map(static fn (CreateTriggerField $field): string => $field->label(), CreateTriggerField::cases()));
    }

    #[TestWith([CreateTriggerField::Created, 'timestamp'])]
    #[TestWith([CreateTriggerField::Definition, 'varchar'])]
    #[TestWith([CreateTriggerField::Name, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(CreateTriggerField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([CreateTriggerField::Created, Nullability::MaybeNull])]
    #[TestWith([CreateTriggerField::Definition, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(CreateTriggerField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }
}
