<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineCodeField;
use SqlSemantics\Type\Nullability;

#[CoversClass(RoutineCodeField::class)]
#[Medium]
final class RoutineCodeFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Pos', 'Instruction'], array_map(static fn (RoutineCodeField $field): string => $field->label(), RoutineCodeField::cases()));
    }

    #[TestWith([RoutineCodeField::Position, 'bigint'])]
    #[TestWith([RoutineCodeField::Instruction, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(RoutineCodeField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(RoutineCodeField::cases()), Nullability::NotNull), array_map(static fn (RoutineCodeField $field): Nullability => $field->nullability(), RoutineCodeField::cases()));
    }
}
