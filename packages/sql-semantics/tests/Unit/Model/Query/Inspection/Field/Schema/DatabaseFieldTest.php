<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\DatabaseField;
use SqlSemantics\Type\Nullability;

#[CoversClass(DatabaseField::class)]
#[Medium]
final class DatabaseFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Database'], array_map(static fn (DatabaseField $field): string => $field->label(), DatabaseField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(DatabaseField::cases()), 'varchar'), array_map(static fn (DatabaseField $field): string => $field->type(), DatabaseField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(DatabaseField::cases()), Nullability::NotNull), array_map(static fn (DatabaseField $field): Nullability => $field->nullability(), DatabaseField::cases()));
    }
}
