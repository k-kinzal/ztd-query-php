<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Definition\ColumnNullability;
use SqlSemantics\Statement\Schema\Definition\ColumnPrimaryKey;
use SqlSemantics\Statement\Schema\Definition\KeyDirection;
use SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition;
use SqlSemantics\Statement\Type\SqliteDeclaration;

#[CoversClass(SqliteColumnDefinition::class)]
#[Small]
final class SqliteColumnDefinitionTest extends TestCase
{
    public function testToStringDerivesTheColumnFromItsTypeAndRules(): void
    {
        $type = new SqliteDeclaration('TEXT');
        $rule = new ColumnNullability(false);
        $definition = new SqliteColumnDefinition(new Name('label'), $type, $rule);
        self::assertSame('label TEXT NOT NULL', $definition->toString());
        self::assertSame($type->descriptor, $definition->column->type);
        self::assertSame([$rule], $definition->constraints);
        self::assertSame(Nullability::NotNull, $definition->column->nullability);
    }

    #[TestWith(['INTEGER', false, false, KeyDirection::Implicit, Nullability::NotNull])]
    #[TestWith(['INTEGER', true, false, KeyDirection::Implicit, Nullability::MaybeNull])]
    #[TestWith(['INTEGER', false, false, KeyDirection::Descending, Nullability::MaybeNull])]
    #[TestWith(['INT', false, false, KeyDirection::Implicit, Nullability::MaybeNull])]
    #[TestWith(['INT', false, true, KeyDirection::Implicit, Nullability::NotNull])]
    public function testToStringKeepsPrimaryKeyNullabilityDependentOnNativeIdentity(string $name, bool $custom, bool $strict, KeyDirection $direction, Nullability $expected): void
    {
        $definition = new SqliteColumnDefinition(new Name('id'), new SqliteDeclaration($name, $strict, $custom), new ColumnPrimaryKey($direction));
        self::assertSame($expected, $definition->column->nullability);
        self::assertStringContainsString('PRIMARY KEY', $definition->toString());
    }
}
