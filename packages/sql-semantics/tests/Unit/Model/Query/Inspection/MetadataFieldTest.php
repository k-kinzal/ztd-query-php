<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineCodeField;
use SqlSemantics\Model\Query\Inspection\Field\Schema\CollationField;
use SqlSemantics\Model\Query\Inspection\Field\Schema\ColumnField;
use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Type\Nullability;

#[CoversClass(MetadataField::class)]
#[Medium]
final class MetadataFieldTest extends TestCase
{
    #[TestWith([CollationField::Id, 'Id'])]
    #[TestWith([ColumnField::Name, 'Field'])]
    #[TestWith([RoutineCodeField::Position, 'Pos'])]
    public function testLabelIsTheServerResultLabel(MetadataField $field, string $expected): void
    {
        self::assertSame($expected, $field->label());
    }

    #[TestWith([CollationField::Id, 'bigint'])]
    #[TestWith([ColumnField::Name, 'varchar'])]
    #[TestWith([RoutineCodeField::Instruction, 'varchar'])]
    public function testTypeIsAMySqlBuiltinTypeName(MetadataField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
        self::assertSame($expected, \SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::MySql, $field->type())->name);
    }

    #[TestWith([CollationField::Id, Nullability::NotNull])]
    #[TestWith([ColumnField::Default, Nullability::MaybeNull])]
    public function testNullabilityIsDeclaredPerField(MetadataField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }
}
