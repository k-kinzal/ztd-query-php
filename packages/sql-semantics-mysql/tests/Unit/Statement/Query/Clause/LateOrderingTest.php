<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(LateOrdering::class)]
#[Medium]
final class LateOrderingTest extends TestCase
{
    public function testRenderWritesTheOrderingAfterTheLockingClausesOfTheBlock(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('select (select a from t for update order by b limit 1) as v', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $subquery = $item->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        $block = $subquery->query;
        self::assertInstanceOf(Select::class, $block);
        $late = $block->late;
        self::assertNotNull($late);
        $resolution = $operation->facts->scalar($late->orderBy[0]->expression)->resolution;

        self::assertSame('SELECT (SELECT a FROM t FOR UPDATE ORDER BY b LIMIT 1) AS v', $operation->toString());
        self::assertSame([], $block->orderBy);
        self::assertNull($block->limit);
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[1], $resolution->declaration());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesALimitThatReplacesTheLimitOfTheBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (SELECT 1 LIMIT 1 LIMIT 2)', []);

        self::assertSame('SELECT (SELECT 1 LIMIT 1 LIMIT 2)', $operation->toString());
        self::assertNotNull((new LateOrdering([], new RowLimit(new NumberLiteral('2'))))->limit);
    }

    public function testRejectsALateOrderingWithoutClauses(): void
    {
        $this->expectExceptionMessage('A late ordering holds an ORDER BY or a LIMIT.');

        new LateOrdering([]);
    }
}
