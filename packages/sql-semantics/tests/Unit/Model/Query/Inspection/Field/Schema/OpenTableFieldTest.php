<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\OpenTableField;
use SqlSemantics\Type\Nullability;

#[CoversClass(OpenTableField::class)]
#[Medium]
final class OpenTableFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Database', 'Table', 'In_use', 'Name_locked'], array_map(static fn (OpenTableField $field): string => $field->label(), OpenTableField::cases()));
    }

    #[TestWith([OpenTableField::InUse, 'bigint'])]
    #[TestWith([OpenTableField::NameLocked, 'bigint'])]
    #[TestWith([OpenTableField::Database, 'varchar'])]
    #[TestWith([OpenTableField::Table, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(OpenTableField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(OpenTableField::cases()), Nullability::NotNull), array_map(static fn (OpenTableField $field): Nullability => $field->nullability(), OpenTableField::cases()));
    }
}
