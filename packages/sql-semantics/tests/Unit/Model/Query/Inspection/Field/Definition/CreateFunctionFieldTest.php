<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateFunctionField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateFunctionField::class)]
#[Medium]
final class CreateFunctionFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Function', 'sql_mode', 'Create Function', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (CreateFunctionField $field): string => $field->label(), CreateFunctionField::cases()));
    }

    #[TestWith([CreateFunctionField::Name, Nullability::NotNull])]
    #[TestWith([CreateFunctionField::Definition, Nullability::MaybeNull])]
    #[TestWith([CreateFunctionField::DatabaseCollation, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(CreateFunctionField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateFunctionField::cases()), 'varchar'), array_map(static fn (CreateFunctionField $field): string => $field->type(), CreateFunctionField::cases()));
    }
}
