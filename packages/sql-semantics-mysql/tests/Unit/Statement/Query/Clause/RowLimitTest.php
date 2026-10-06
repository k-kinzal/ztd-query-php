<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(RowLimit::class)]
#[Medium]
final class RowLimitTest extends TestCase
{
    public function testRenderWritesTheOperandsInTheirSpelling(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT a FROM t LIMIT 5, 10', $semantics->analyze('select a from t limit 5,10')->toString());
        self::assertSame('SELECT a FROM t LIMIT 10 OFFSET 5', $semantics->analyze('select a from t limit 10 offset 5')->toString());
        self::assertSame('SELECT a FROM t LIMIT ?', $semantics->analyze('select a from t limit ?')->toString());
    }

    public function testOperandsAreTheCountAndTheOffset(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT 5, 10');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(RowLimit::class, $operation->statement->limit);
        self::assertInstanceOf(NumberLiteral::class, $operation->statement->limit->count);
        self::assertSame('10', $operation->statement->limit->count->text);
        self::assertSame(OffsetSpelling::Comma, $operation->statement->limit->spelling);
        self::assertInstanceOf(Known::class, $operation->facts->scalar($operation->statement->limit->count)->type);
    }

    public function testAnExpressionAsAnOperandIsRejected(): void
    {
        $this->expectExceptionMessage('A LIMIT operand is an unsigned integer, a parameter marker or a stored program variable.');

        new RowLimit(new NullLiteral());
    }

    public function testAnOffsetWithoutSpellingIsRejected(): void
    {
        $this->expectExceptionMessage('An offset is written in exactly one spelling.');

        new RowLimit(new NumberLiteral('1'), new NumberLiteral('2'));
    }
}
