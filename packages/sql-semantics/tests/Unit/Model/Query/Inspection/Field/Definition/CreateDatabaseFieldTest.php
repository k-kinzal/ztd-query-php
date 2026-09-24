<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateDatabaseField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateDatabaseField::class)]
#[Medium]
final class CreateDatabaseFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Database', 'Create Database'], array_map(static fn (CreateDatabaseField $field): string => $field->label(), CreateDatabaseField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateDatabaseField::cases()), 'varchar'), array_map(static fn (CreateDatabaseField $field): string => $field->type(), CreateDatabaseField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateDatabaseField::cases()), Nullability::NotNull), array_map(static fn (CreateDatabaseField $field): Nullability => $field->nullability(), CreateDatabaseField::cases()));
    }
}
