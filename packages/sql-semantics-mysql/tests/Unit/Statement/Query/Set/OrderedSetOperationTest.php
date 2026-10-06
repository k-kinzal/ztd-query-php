<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AliasTarget;

#[CoversClass(OrderedSetOperation::class)]
#[Medium]
final class OrderedSetOperationTest extends TestCase
{
    public function testDeriveQueryOrdersTheResultAndKeepsTheClausesOfTheLastSelect(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (SELECT 1 AS x UNION SELECT 2 FROM DUAL LIMIT 1 ORDER BY x LIMIT 3)', []);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $subquery = $item->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        $union = $subquery->query;
        self::assertInstanceOf(OrderedSetOperation::class, $union);

        self::assertNotNull($union->right->limit);
        self::assertNotNull($union->limit);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($union->orderBy[0]->expression)->resolution);
        self::assertSame('x', $operation->facts->query($union)->projection[0]->name?->value);
    }

    public function testDeriveQueryNeedsMySql56(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))], new Dual(), null, null, null, [], null, [], null, null, [new LockingClause(LockStrength::Update)]);
        $union = new OrderedSetOperation($one, SetOperator::Union, null, $two, [], new RowLimit(new NumberLiteral('1')));

        $this->expectExceptionMessage('A set operation ordered after the clauses of its last SELECT needs MySQL 5.6.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]), new Select([], [new SelectExpression(new ScalarSubquery($union))]));
    }

    public function testRenderWritesTheOrderingAfterTheLockingClauseOfTheLastSelect(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');

        self::assertSame('SELECT (SELECT 1 UNION SELECT a FROM t FOR UPDATE ORDER BY 1) AS v', $semantics->analyze('select (select 1 union select a from t for update order by 1) as v')->toString());
        self::assertInstanceOf(QueryExpression::class, $semantics->analyze('SELECT 1 UNION SELECT a FROM t ORDER BY 1')->statement);
    }

    public function testRejectsALastSelectWithoutClausesOfItsOwn(): void
    {
        $this->expectExceptionMessage('The last SELECT of an ordered set operation writes an ORDER BY, LIMIT or locking clause of its own and nothing after them.');

        new OrderedSetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))]), SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('2'))]), [], new RowLimit(new NumberLiteral('1')));
    }

    public function testRejectsAsAnOperandOfAnotherSetOperation(): void
    {
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))], new Dual(), null, null, null, [], null, [], null, null, [new LockingClause(LockStrength::Update)]);
        $union = new OrderedSetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))]), SetOperator::Union, null, $two, [], new RowLimit(new NumberLiteral('1')));

        $this->expectExceptionMessage('A set operation ordered after its last SELECT ends its subquery.');

        new SetOperation($union, SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));
    }
}
