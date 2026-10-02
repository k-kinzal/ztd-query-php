<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(E\Rendering\SqliteUnaryLayout::class)]
#[Small]
final class SqliteUnaryLayoutTest extends TestCase
{
    public function testWriteSeparatesAdjacentMinusSignsWithoutChangingNegation(): void
    {
        $layout = new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Negate, '-', '', false);
        $sql = $layout->write('-1', false);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $sql);
        self::assertSame('- -1', $sql);
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }

    public function testWriteSeparatesTheNotKeywordFromAnOperandWord(): void
    {
        $layout = new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Not, 'not', '', false);
        self::assertSame('not NULL', $layout->write('NULL', false));
    }

    public function testWriteKeepsRequiredGroupingEvenWhenTheRequestedLayoutOmitsIt(): void
    {
        $one = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('1'));
        $two = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('2'));
        $sum = new C\Expression\BinaryInput($one, E\SqliteBinaryOperator::Add, $two, new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::Add, '+', '', '', false));
        $input = new C\Expression\UnaryInput(E\SqliteUnaryOperator::Negate, $sum, new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Negate, '-', '', false));
        $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')));
        $query = new \SqlSemantics\Statement\Query\Select($catalog, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($input))));
        $result = (new PDO('sqlite::memory:'))->query($query->toString());
        self::assertSame('SELECT -(1+2)', $query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(-3, $result->fetchColumn());
    }

    public function testRejectsAChangedPrefixOperation(): void
    {
        $this->expectException(InvalidConstruction::class);
        new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Plus, '-');
    }

    public function testRejectsAnOperandHiddenInTheGap(): void
    {
        $this->expectException(InvalidConstruction::class);
        new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Negate, '-', '1+');
    }

    public function testRejectsAMismatchingOperationAtTheActualExpressionBoundary(): void
    {
        $layout = new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Plus, '+');
        $this->expectException(InvalidConstruction::class);
        new E\SqliteUnary(E\SqliteUnaryOperator::Negate, new E\NullConstant(), $layout);
    }

    public function testRejectsAMismatchingOperationAtTheNewInputBoundary(): void
    {
        $layout = new E\Rendering\SqliteUnaryLayout(E\SqliteUnaryOperator::Plus, '+');
        $this->expectException(InvalidConstruction::class);
        new C\Expression\UnaryInput(E\SqliteUnaryOperator::Negate, new E\NullConstant(), $layout);
    }
}
