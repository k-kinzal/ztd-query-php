<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Statement\Operation;

#[CoversClass(LeadingUnion::class)]
#[Medium]
final class LeadingUnionTest extends TestCase
{
    public function testRenderKeepsTheOwnClausesOfAnEarlierSelect(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 union all select a from t for update union select 3 limit 1 union select 4');
        $union = $operation->statement;
        self::assertInstanceOf(SetOperation::class, $union);
        $leading = $union->left;
        self::assertInstanceOf(LeadingUnion::class, $leading);
        $first = $leading->left;
        self::assertInstanceOf(LeadingUnion::class, $first);

        self::assertNotNull($leading->right->limit);
        self::assertCount(1, $first->right->locking);
        self::assertSame(SetQuantifier::All, $first->quantifier);
        self::assertInstanceOf(Select::class, $first->left);
        self::assertCount(1, $operation->facts->query($first->left)->projection);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderNeedsALastOperandWithOnlyItsOwnLimitOrLocking(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $ordered = new Select([], [new SelectExpression(new NumberLiteral('2'))], new Dual(), null, null, null, [], null, [new OrderItem(new NumberLiteral('1.5'))]);

        $this->expectExceptionMessage('The last operand of a leading union keeps its own LIMIT or locking clauses and nothing else.');

        new LeadingUnion($one, null, $ordered);
    }

    public function testRenderNeedsUnionAfterIt(): void
    {
        $limited = new Select([], [new SelectExpression(new NumberLiteral('2'))], null, null, null, null, [], null, [], new RowLimit(new NumberLiteral('1')));
        $leading = new LeadingUnion(new Select([], [new SelectExpression(new NumberLiteral('1'))]), null, $limited);

        $this->expectExceptionMessage('A leading union continues with UNION.');

        new SetOperation($leading, SetOperator::Intersect, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));
    }

    public function testRenderWritesTheOperandsAroundUnion(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame('SELECT 1 FROM t FOR UPDATE UNION DISTINCT SELECT 2 FROM t LOCK IN SHARE MODE UNION SELECT 3', $semantics->analyze('SELECT 1 FROM t FOR UPDATE UNION DISTINCT SELECT 2 FROM t LOCK IN SHARE MODE UNION SELECT 3')->toString());
    }

    public function testRenderNeedsMySql5ForTheFormAndMySql56ForAnOwnLimit(): void
    {
        $locked = new Select([], [new SelectExpression(new NumberLiteral('2'))], new Dual(), null, null, null, [], null, [], null, null, [new LockingClause(LockStrength::Update)]);
        $union = new SetOperation(new LeadingUnion(new Select([], [new SelectExpression(new NumberLiteral('1'))]), null, $locked), SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));
        $accepted = new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]), $union);
        $limited = new Select([], [new SelectExpression(new NumberLiteral('2'))], null, null, null, null, [], null, [], new RowLimit(new NumberLiteral('1')));
        $limitedUnion = new SetOperation(new LeadingUnion(new Select([], [new SelectExpression(new NumberLiteral('1'))]), null, $limited), SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));

        self::assertSame('SELECT 1 UNION SELECT 2 FROM DUAL FOR UPDATE UNION SELECT 3', $accepted->toString());
        $this->expectExceptionMessage('A SELECT that keeps its own LIMIT before a later UNION needs MySQL 5.6.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]), $limitedUnion);
    }
}
