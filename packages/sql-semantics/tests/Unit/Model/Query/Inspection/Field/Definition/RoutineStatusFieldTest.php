<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineStatusField;
use SqlSemantics\Type\Nullability;

#[CoversClass(RoutineStatusField::class)]
#[Medium]
final class RoutineStatusFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Db', 'Name', 'Type', 'Definer', 'Modified', 'Created', 'Security_type', 'Comment', 'character_set_client', 'collation_connection', 'Database Collation'], array_map(static fn (RoutineStatusField $field): string => $field->label(), RoutineStatusField::cases()));
    }

    #[TestWith([RoutineStatusField::Modified, 'datetime'])]
    #[TestWith([RoutineStatusField::Created, 'datetime'])]
    #[TestWith([RoutineStatusField::Definer, 'varchar'])]
    #[TestWith([RoutineStatusField::Comment, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(RoutineStatusField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(RoutineStatusField::cases()), Nullability::NotNull), array_map(static fn (RoutineStatusField $field): Nullability => $field->nullability(), RoutineStatusField::cases()));
    }
}
