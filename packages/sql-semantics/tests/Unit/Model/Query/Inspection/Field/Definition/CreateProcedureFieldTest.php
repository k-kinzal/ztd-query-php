<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateProcedureField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateProcedureField::class)]
#[Medium]
final class CreateProcedureFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Procedure', 'sql_mode', 'Create Procedure', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (CreateProcedureField $field): string => $field->label(), CreateProcedureField::cases()));
    }

    #[TestWith([CreateProcedureField::Name, Nullability::NotNull])]
    #[TestWith([CreateProcedureField::Definition, Nullability::MaybeNull])]
    #[TestWith([CreateProcedureField::SqlMode, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(CreateProcedureField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateProcedureField::cases()), 'varchar'), array_map(static fn (CreateProcedureField $field): string => $field->type(), CreateProcedureField::cases()));
    }
}
