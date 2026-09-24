<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\TableField;
use SqlSemantics\Type\Nullability;

#[CoversClass(TableField::class)]
#[Medium]
final class TableFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Tables_in_', 'Table_type'], array_map(static fn (TableField $field): string => $field->label(), TableField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(TableField::cases()), 'varchar'), array_map(static fn (TableField $field): string => $field->type(), TableField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(TableField::cases()), Nullability::NotNull), array_map(static fn (TableField $field): Nullability => $field->nullability(), TableField::cases()));
    }
}
