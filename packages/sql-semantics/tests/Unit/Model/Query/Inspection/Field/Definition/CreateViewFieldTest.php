<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateViewField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateViewField::class)]
#[Medium]
final class CreateViewFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['View', 'Create View', 'character_set_client', 'collation_connection'], array_map(static fn (CreateViewField $field): string => $field->label(), CreateViewField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateViewField::cases()), 'varchar'), array_map(static fn (CreateViewField $field): string => $field->type(), CreateViewField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateViewField::cases()), Nullability::NotNull), array_map(static fn (CreateViewField $field): Nullability => $field->nullability(), CreateViewField::cases()));
    }
}
