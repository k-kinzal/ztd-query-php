<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Modern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;

#[CoversClass(ExpressionRule::class)]
#[Medium]
final class ExpressionRuleTest extends TestCase
{
    public function testStatementFoldsTheClausesIntoASingleBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t ORDER BY a LIMIT 1 FOR UPDATE');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertCount(1, $operation->statement->locking);
        self::assertNotNull($operation->statement->limit);
    }

    public function testIntoLowersTheIntoAfterTheQuery(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('(SELECT a FROM t FOR UPDATE INTO @x)');

        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        self::assertSame(IntoPosition::AfterLocking, $operation->statement->query->intoPosition);
    }

    public function testExpressionWrapsANonBlockBody(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1) SELECT 1 UNION SELECT 2 ORDER BY 1 FOR UPDATE');

        self::assertInstanceOf(QueryStatement::class, $operation->statement);
        self::assertInstanceOf(QueryExpression::class, $operation->statement->query);
        self::assertNotNull($operation->statement->query->with);
    }

    public function testBodyBuildsALeftDeepChain(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT 2 EXCEPT SELECT 3');

        self::assertInstanceOf(SetOperation::class, $operation->statement);
        self::assertSame(SetOperator::Except, $operation->statement->operator);
        self::assertInstanceOf(SetOperation::class, $operation->statement->left);
    }

    public function testQuantifierLowersDistinctAndAll(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION DISTINCT SELECT 2');

        self::assertInstanceOf(SetOperation::class, $operation->statement);
        self::assertSame(SetQuantifier::Distinct, $operation->statement->quantifier);
    }

    public function testParenthesizedKeepsEveryPair(): void
    {
        self::assertSame('((SELECT 1)) UNION (SELECT 2)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('((select 1)) union (select 2)')->toString());
    }

    public function testInnerAnswersTheQueryInsideTheOuterParentheses(): void
    {
        self::assertSame('SELECT (SELECT 1) AS v, EXISTS ((SELECT 2)) AS w FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select (select 1) as v, exists ((select 2)) as w from t')->toString());
    }

    public function testLockedLowersTheLockingClausesInParentheses(): void
    {
        self::assertSame('(SELECT a FROM t FOR UPDATE) UNION SELECT 1', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('(select a from t for update) union select 1')->toString());
    }

    public function testSubqueryRemovesTheParenthesesOfTheSyntax(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM (SELECT 1) AS d');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DerivedTable::class, $operation->statement->from);
        self::assertInstanceOf(Select::class, $operation->statement->from->query);
    }
}
