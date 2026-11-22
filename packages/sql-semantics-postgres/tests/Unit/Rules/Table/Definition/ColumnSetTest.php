<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet::class)]
#[Medium]
final class ColumnSetTest extends TestCase
{
    public function testAddKeepsTheFirstColumnOfAName(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, true);
        self::assertSame(false, $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, false));
    }

    public function testInheritMergesTheNotNullFact(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, false);
        $set->inherit(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull));
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull, $set->columns()[0]->nullability);
    }

    public function testRequireMarksAColumnNotNull(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, false);
        $set->require(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'));
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull, $set->columns()[0]->nullability);
    }

    public function testCloseStopsAddingColumns(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->close();
        self::assertSame([
          0 => false,
          1 => 0,
        ], [$set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, false), count($set->columns())]);
    }

    public function testClosedIsFalseForANewSet(): void
    {
        self::assertSame(false, (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet())->closed());
    }

    public function testColumnsAnswersTheColumnsInOrder(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, false);
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, false);
        self::assertSame([
          0 => 'b',
          1 => 'a',
        ], array_map(static fn ($column): string => $column->name->value, $set->columns()));
    }

    public function testAddKeepsTheGeneratedFlag(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, false, true);
        self::assertSame(true, $set->columns()[0]->generated);
    }

    public function testInheritMergesTheGeneratedFlag(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $set->add(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, false);
        $set->inherit(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::Nullable, true));
        self::assertSame([true, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::Nullable], [$set->columns()[0]->generated, $set->columns()[0]->nullability]);
    }

    public function testIdentifyMarksAnExplicitIdentitySequence(): void
    {
        $set = new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnSet();
        $column = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('n');
        $set->add($column, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, true);
        $set->identify($column);

        self::assertTrue($set->columns()[0]->identity);
    }
}
