<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\TableStatusField;
use SqlSemantics\Type\Nullability;

#[CoversClass(TableStatusField::class)]
#[Medium]
final class TableStatusFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Name', 'Engine', 'Version', 'Row_format', 'Rows', 'Avg_row_length', 'Data_length', 'Max_data_length', 'Index_length', 'Data_free', 'Auto_increment', 'Create_time', 'Update_time', 'Check_time', 'Collation', 'Checksum', 'Create_options', 'Comment'], array_map(static fn (TableStatusField $field): string => $field->label(), TableStatusField::cases()));
    }

    #[TestWith([TableStatusField::Version, 'bigint'])]
    #[TestWith([TableStatusField::Rows, 'bigint'])]
    #[TestWith([TableStatusField::AutoIncrement, 'bigint'])]
    #[TestWith([TableStatusField::Checksum, 'bigint'])]
    #[TestWith([TableStatusField::CreateTime, 'datetime'])]
    #[TestWith([TableStatusField::CheckTime, 'datetime'])]
    #[TestWith([TableStatusField::Name, 'varchar'])]
    #[TestWith([TableStatusField::Engine, 'varchar'])]
    #[TestWith([TableStatusField::Comment, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(TableStatusField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([TableStatusField::Name, Nullability::NotNull])]
    #[TestWith([TableStatusField::Engine, Nullability::MaybeNull])]
    #[TestWith([TableStatusField::Rows, Nullability::MaybeNull])]
    #[TestWith([TableStatusField::Comment, Nullability::MaybeNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(TableStatusField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }
}
