<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateUserField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CreateUserField::class)]
#[Medium]
final class CreateUserFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['CREATE USER for '], array_map(static fn (CreateUserField $field): string => $field->label(), CreateUserField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateUserField::cases()), 'varchar'), array_map(static fn (CreateUserField $field): string => $field->type(), CreateUserField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CreateUserField::cases()), Nullability::NotNull), array_map(static fn (CreateUserField $field): Nullability => $field->nullability(), CreateUserField::cases()));
    }
}
