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
    public function testExpressionPreservesADeferredNestedQueryInsideItsOuterOperands(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'CREATE PROCEDURE p() SELECT missing, (SELECT GROUP_CONCAT(1) OVER ())';
        $operation = $semantics->analyze($sql);
        self::assertSame($sql, $operation->toString());
        self::assertSame($sql, $semantics->analyze($operation->toString())->toString());
    }

    public function testStatementFoldsTheClausesIntoASingleBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t ORDER BY a LIMIT 1 FOR UPDATE');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertCount(1, $operation->query->locking);
        self::assertNotNull($operation->query->limit);
    }

    public function testIntoLowersTheIntoAfterTheQuery(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('(SELECT a FROM t FOR UPDATE INTO @x)');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(ParenthesizedQuery::class, $operation->query);
        self::assertInstanceOf(Select::class, $operation->query->query);
        self::assertSame(IntoPosition::AfterLocking, $operation->query->query->intoPosition);
    }

    public function testExpressionWrapsANonBlockBody(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1) SELECT 1 UNION SELECT 2 ORDER BY 1 FOR UPDATE');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(QueryStatement::class, $operation->query);
        self::assertInstanceOf(QueryExpression::class, $operation->query->query);
        self::assertNotNull($operation->query->query->with);
    }

    public function testBodyBuildsALeftDeepChain(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT 2 EXCEPT SELECT 3');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(SetOperation::class, $operation->query);
        self::assertSame(SetOperator::Except, $operation->query->operator);
        self::assertInstanceOf(SetOperation::class, $operation->query->left);
    }

    public function testQuantifierLowersDistinctAndAll(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION DISTINCT SELECT 2');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(SetOperation::class, $operation->query);
        self::assertSame(SetQuantifier::Distinct, $operation->query->quantifier);
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

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertInstanceOf(DerivedTable::class, $operation->query->from);
        self::assertInstanceOf(Select::class, $operation->query->from->query);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLaterOperands(): iterable
    {
        yield 'union' => ['SELECT 1 UNION SELECT HIGH_PRIORITY 2', 'HIGH_PRIORITY'];
        yield 'parenthesized operand' => ['SELECT 1 UNION (SELECT SQL_BUFFER_RESULT 2)', 'SQL_BUFFER_RESULT'];
        yield 'insert source' => ['INSERT INTO t SELECT 1 EXCEPT SELECT SQL_CALC_FOUND_ROWS 2', 'SQL_CALC_FOUND_ROWS'];
        yield 'stored program body' => ['CREATE PROCEDURE p() SELECT 1 UNION SELECT HIGH_PRIORITY 2', 'HIGH_PRIORITY'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLaterOperands')]
    public function testBodyRefusesAModifierOfTheFirstBlockInALaterOperandOnceRead(string $sql, string $option): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The modifier belongs to the first block of the statement.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption::class, $failure->problem);
            self::assertSame("Incorrect usage/placement of '$option'", $failure->problem->message());
            self::assertFalse($failure->aborts);
        }
        self::assertSame('SELECT HIGH_PRIORITY 1 UNION SELECT SQL_NO_CACHE 2', (new Semantics(Dialect::MySql))->analyze('SELECT HIGH_PRIORITY 1 UNION SELECT SQL_NO_CACHE 2')->toString());
    }
}
