<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\IndexLayout;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IndexLayout::class)]
#[Small]
final class IndexLayoutTest extends TestCase
{
    public function testAddedReconstructsOccurrencesThroughTheirConstructors(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new ColumnUse(new Name('a')), new NumberLiteral('1'));
        $index = new IndexDefinition(IndexKind::Index, [new ExpressionPart($expression)]);
        $copy = (new IndexLayout())->added($index);

        self::assertEquals($index, $copy);
        self::assertNotSame($index, $copy);
        self::assertNotSame($index->parts[0], $copy->parts[0]);
        self::assertInstanceOf(ExpressionPart::class, $copy->parts[0]);
        self::assertNotSame($expression, $copy->parts[0]->expression);
    }

    public function testDependenciesAllowsAColumnThatKeepsItsName(): void
    {
        $column = new ColumnUse(new Name('a'));
        $part = new ExpressionPart(new Arithmetic(ArithmeticOperator::Plus, $column, new NumberLiteral('1')));
        (new IndexLayout())->dependencies($part, static fn (string $name): string => strtoupper($name));

        self::assertSame('a', $column->name->value);
    }

    public function testDependenciesRefusesADroppedColumn(): void
    {
        $part = new ExpressionPart(new Arithmetic(ArithmeticOperator::Plus, new ColumnUse(new Name('a')), new NumberLiteral('1')));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3837);
        (new IndexLayout())->dependencies($part, static fn (string $name) => false);
    }
}
