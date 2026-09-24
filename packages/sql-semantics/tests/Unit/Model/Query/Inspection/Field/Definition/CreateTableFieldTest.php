<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTableField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateTableField::class)]
#[Medium]
final class CreateTableFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Table', 'Create Table'], array_map(static fn (CreateTableField $field): string => $field->label(), CreateTableField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateTableField::cases()), 'varchar'), array_map(static fn (CreateTableField $field): string => $field->type(), CreateTableField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateTableField::cases()), Nullability::NotNull), array_map(static fn (CreateTableField $field): Nullability => $field->nullability(), CreateTableField::cases()));
    }
}
