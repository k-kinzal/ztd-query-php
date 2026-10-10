<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Subqueries;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Subqueries::class)]
#[Small]
final class SubqueriesTest extends TestCase
{
    public function testScalarReadsTheValueOfASubqueryOrNullForNoRow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT (SELECT 5), (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', null]], $result->rows);
    }

    public function testScalarRefusesASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT (SELECT 1, 2)');
    }

    public function testScalarRefusesASubqueryOfMoreThanOneRow(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1242);
        $this->expectExceptionMessage('Subquery returns more than 1 row');

        $session->query('SELECT (SELECT 1 UNION SELECT 2)');
    }

    public function testExistsTellsWhetherTheSubqueryHasARow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT EXISTS (SELECT 1), EXISTS (SELECT 1 FROM DUAL WHERE 0), NOT EXISTS (SELECT NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '0']], $result->rows);
    }

    public function testInTellsWhetherAValueIsAmongTheRowsOfTheSubquery(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 IN (SELECT 1 UNION SELECT 2), 3 IN (SELECT 1 UNION SELECT NULL), 3 NOT IN (SELECT 1 UNION SELECT 2), NULL IN (SELECT 1), 1 IN (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null, '1', null, '0']], $result->rows);
    }

    public function testQuantifiedComparisonComparesWithAnyOrAllRows(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 > ALL (SELECT 1 UNION SELECT 2), 2 > ANY (SELECT 1 UNION SELECT 2), 2 = SOME (SELECT 3), 1 = ALL (SELECT 1 FROM DUAL WHERE 0), 1 = ANY (SELECT 1 FROM DUAL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '0', '1', '0']], $result->rows);
    }

    public function testQuantifiedRefusesASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT 1 IN (SELECT 1, 2)');
    }

    public function testQuantifiedComparesWithTheRowOfASubqueryWithoutATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query("SELECT CONCAT('x') = ALL (SELECT 1), 2 NOT IN (VALUES ROW(1)), CONCAT('x') > ALL (SELECT 1) FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '0'], ['0', '1', '0'], ['0', '1', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $warnings->rows);
    }

    public function testSingleAnswersTheExpressionOfARowWithoutATable(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT (SELECT 1), (VALUES ROW(2)), (SELECT MAX(3)), (SELECT 4 UNION SELECT 5)');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $select = $operation->field(0)->expression;
        $values = $operation->field(1)->expression;
        $aggregate = $operation->field(2)->expression;
        $union = $operation->field(3)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $select);
        self::assertInstanceOf(ScalarSubquery::class, $values);
        self::assertInstanceOf(ScalarSubquery::class, $aggregate);
        self::assertInstanceOf(ScalarSubquery::class, $union);
        $subqueries = $planner->compiler->subqueries;

        self::assertInstanceOf(NumberLiteral::class, $subqueries->single($select->query));
        self::assertInstanceOf(NumberLiteral::class, $subqueries->single($values->query));
        self::assertNull($subqueries->single($aggregate->query));
        self::assertNull($subqueries->single($union->query));
    }

    public function testScalarRunsAnUncorrelatedSubqueryOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $session->query('SELECT (SELECT 1/0 FROM t LIMIT 1), (SELECT MAX(1/0)) FROM t');
        $once = $session->query('SHOW COUNT(*) WARNINGS')[0];
        $session->query('SELECT (SELECT 1/0), (SELECT 1/0 FROM t AS u WHERE u.a = t.a) FROM t');
        $everyRow = $session->query('SHOW COUNT(*) WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $once);
        self::assertSame([['2']], $once->rows);
        self::assertInstanceOf(ResultSet::class, $everyRow);
        self::assertSame([['6']], $everyRow->rows);
    }

    public function testSubstitutedHoldsForASelectOfOneExpressionWithoutATableOrAnAggregate(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT (SELECT 1 FROM DUAL WHERE 1), (SELECT MAX(1)), (SELECT 1 HAVING 1)');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $where = $operation->field(0)->expression;
        $aggregate = $operation->field(1)->expression;
        $having = $operation->field(2)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $where);
        self::assertInstanceOf(ScalarSubquery::class, $aggregate);
        self::assertInstanceOf(ScalarSubquery::class, $having);
        $subqueries = $planner->compiler->subqueries;

        self::assertSame([true, false, false], [$subqueries->substituted($where->query), $subqueries->substituted($aggregate->query), $subqueries->substituted($having->query)]);
    }

    public function testInnermostRemovesTheParenthesesAndTheQueryExpressionsWithoutAWithClause(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT ((SELECT 1)), (WITH c AS (SELECT 1) SELECT 2)');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $parenthesized = $operation->field(0)->expression;
        $with = $operation->field(1)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $parenthesized);
        self::assertInstanceOf(ScalarSubquery::class, $with);
        $subqueries = $planner->compiler->subqueries;

        self::assertInstanceOf(Select::class, $subqueries->innermost($parenthesized->query));
        self::assertInstanceOf(QueryExpression::class, $subqueries->innermost($with->query));
    }

    public function testSelectedAnswersTheExpressionOfASelectWithoutATableOrAnAggregate(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT (SELECT 1), (SELECT 1 FROM DUAL WHERE 1), (SELECT MAX(3))');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $plain = $operation->field(0)->expression;
        $where = $operation->field(1)->expression;
        $aggregate = $operation->field(2)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $plain);
        self::assertInstanceOf(ScalarSubquery::class, $where);
        self::assertInstanceOf(ScalarSubquery::class, $aggregate);
        self::assertInstanceOf(Select::class, $plain->query);
        self::assertInstanceOf(Select::class, $where->query);
        self::assertInstanceOf(Select::class, $aggregate->query);
        $subqueries = $planner->compiler->subqueries;

        self::assertInstanceOf(NumberLiteral::class, $subqueries->selected($plain->query));
        self::assertNull($subqueries->selected($where->query));
        self::assertNull($subqueries->selected($aggregate->query));
    }

    public function testAggregatedTellsWhetherAnExpressionHoldsAnAggregateOfItsBlock(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT 1 + MAX(2), GROUP_CONCAT('a'), JSON_OBJECTAGG('k', 1), 1 + (SELECT MAX(2))");
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $maximum = $operation->field(0)->expression;
        $concatenation = $operation->field(1)->expression;
        $object = $operation->field(2)->expression;
        $nested = $operation->field(3)->expression;
        self::assertNotNull($maximum);
        self::assertNotNull($concatenation);
        self::assertNotNull($object);
        self::assertNotNull($nested);
        $subqueries = $planner->compiler->subqueries;

        self::assertSame([true, true, true, false], [$subqueries->aggregated($maximum), $subqueries->aggregated($concatenation), $subqueries->aggregated($object), $subqueries->aggregated($nested)]);
    }
}
