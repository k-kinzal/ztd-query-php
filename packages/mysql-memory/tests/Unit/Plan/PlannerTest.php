<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Command\Output;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Path\Combine\SetOperation as SetPath;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;

#[CoversClass(Planner::class)]
#[Small]
final class PlannerTest extends TestCase
{
    public function testQueryPlansASelectIntoItsRowsNamesAndTypes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (2, 'x'), (1, 'y')");
        $operation = $session->analyze('SELECT b, a FROM t WHERE a > 1');
        $statement = $operation->statement;
        self::assertInstanceOf(Query::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->query($statement, null);

        self::assertSame(['b', 'a'], $plan->names);
        self::assertSame([Field::VarString, Field::Long], array_map(static fn (Domain $domain): Field => $domain->field, $plan->domains));
        self::assertSame([['x', '2']], (new Output())->result($plan, $context)->rows);
    }

    public function testQueryPlansTheQueryInsideParentheses(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('(SELECT 1 AS n)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame('n', $result->columns[0]->name);
    }

    public function testExpressionSortsAndLimitsTheRowsOfItsBody(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (2), (3), (1)');
        $operation = $session->analyze('(SELECT a FROM t) ORDER BY a DESC LIMIT 2');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->expression($statement, null);

        self::assertInstanceOf(Limit::class, $plan->root);
        self::assertInstanceOf(Sort::class, $plan->root->input);
        self::assertSame(['a'], $plan->names);
        self::assertSame([['3'], ['2']], (new Output())->result($plan, $context)->rows);
    }

    public function testExpressionLeavesOutAKeyConstantForTheStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (2), (1)');
        $result = $session->query('TABLE t ORDER BY DATABASE() + 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2'], ['1']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testExpressionLeavesTheRowsOfAValuesStatementInWrittenOrder(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query('VALUES ROW(1), ROW(3), ROW(2) ORDER BY column_0 DESC LIMIT 2')[0];
        $union = $session->query('(VALUES ROW(1), ROW(2)) UNION ALL (VALUES ROW(3)) ORDER BY column_0 DESC')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $union);
        self::assertSame([['1'], ['3']], $result->rows);
        self::assertSame([['3'], ['2'], ['1']], $union->rows);
    }

    public function testExpressionAnswersThePlanOfItsBodyWithoutOrderOrLimit(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH c AS (SELECT 1 AS n) SELECT n FROM c');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->expression($statement, null);

        self::assertInstanceOf(Project::class, $plan->root);
        self::assertSame([['1']], (new Output())->result($plan, $context)->rows);
    }

    public function testCommonTablePlansAnExpressionOnceForEveryReference(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH c AS (SELECT 1 AS n UNION ALL SELECT 2) SELECT x.n, y.n FROM c AS x, c AS y ORDER BY x.n, y.n');
        $statement = $operation->statement;
        self::assertInstanceOf(Query::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->query($statement, null);

        self::assertCount(1, $planner->commonTables);
        self::assertSame([['1', '1'], ['1', '2'], ['2', '1'], ['2', '2']], (new Output())->result($plan, $context)->rows);
    }

    public function testCommonTableNamesTheColumnsByItsColumnList(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('WITH c (p, q) AS (SELECT 1, 2) SELECT * FROM c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['p', 'q'], array_map(static fn ($column): string => $column->name, $result->columns));
        self::assertSame([['1', '2']], $result->rows);
    }

    public function testSetCombinesBothSidesIntoOneSetPath(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 AS n UNION SELECT 1 UNION ALL SELECT 2');
        $statement = $operation->statement;
        self::assertInstanceOf(SetOperation::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->set($statement, null);

        self::assertInstanceOf(SetPath::class, $plan->root);
        self::assertSame(SetKind::Union, $plan->root->kind);
        self::assertFalse($plan->root->distinct);
        self::assertSame(['n'], $plan->names);
        self::assertSame([['1'], ['2']], (new Output())->result($plan, $context)->rows);
    }

    public function testSetTypesTheColumnsFromBothSides(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 1 UNION SELECT 'abc'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(Field::VarString, $result->columns[0]->type);
        self::assertSame([['1'], ['abc']], $result->rows);
    }

    public function testSetRaisesWhenTheSidesHaveDifferentNumbersOfColumns(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1222);
        $this->expectExceptionMessage('The used SELECT statements have a different number of columns');

        $session->query('SELECT 1 UNION SELECT 1, 2');
    }

    public function testValuesNamesTheColumnsFromColumn0(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("VALUES ROW(1, 'a'), ROW(2, 'b')");
        $statement = $operation->statement;
        self::assertInstanceOf(ValuesQuery::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = $planner->values($statement, null);

        self::assertInstanceOf(Inline::class, $plan->root);
        self::assertSame(['column_0', 'column_1'], $plan->names);
        self::assertSame([['1', 'a'], ['2', 'b']], (new Output())->result($plan, $context)->rows);
    }

    public function testMaterializedFlagsTheBlobColumnsAndYearColumnsZerofill(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $origins = $planner->materialized([Domain::integer(), Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob), new Domain(Kind::Year, Field::Year, 4, 0, true)]);

        self::assertEquals([null, new ColumnOrigin('', '', '', '', 16), new ColumnOrigin('', '', '', '', 64)], $origins);
    }

    public function testOutputsAnswersTheTypesOfTheColumnsOfASetOperation(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT 1, 'a' UNION SELECT 2.5, 'bc'");
        $statement = $operation->statement;
        self::assertInstanceOf(SetOperation::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $domains = $planner->outputs($statement);

        self::assertSame([Field::NewDecimal, Field::VarString], array_map(static fn (Domain $domain): Field => $domain->field, $domains));
        self::assertSame(1, $domains[0]->decimals);
        self::assertSame(2, $domains[1]->length);
        self::assertFalse($domains[0]->nullable);
    }

    public function testSettledConvertsTheRowsOfEachOperandIntoTheTypesOfTheOperation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, u BIGINT UNSIGNED, e DATE); INSERT INTO t VALUES (1, 18446744073709551615, \'2024-01-31\')');
        $decimal = $session->query('SELECT a FROM t UNION SELECT 2.5')[0];
        $text = $session->query("SELECT u FROM t UNION SELECT 'x'")[0];
        $moment = $session->query('SELECT e FROM t UNION SELECT NOW() - INTERVAL 1 YEAR LIMIT 1')[0];

        self::assertInstanceOf(ResultSet::class, $decimal);
        self::assertInstanceOf(ResultSet::class, $text);
        self::assertInstanceOf(ResultSet::class, $moment);
        self::assertSame([['1.0'], ['2.5']], $decimal->rows);
        self::assertSame([['18446744073709551615'], ['x']], $text->rows);
        self::assertSame([['2024-01-31 00:00:00']], $moment->rows);
    }

    public function testOperandSettlesNestedSetOperationsInTheOutermostTypes(): void
    {
        $session = (new Instance())->connect();
        $nested = $session->query("SELECT 1 UNION SELECT 2.5 UNION SELECT 'x'")[0];
        $parenthesized = $session->query("(SELECT 1 UNION SELECT 2.5) UNION SELECT 'x'")[0];
        $derived = $session->query("SELECT * FROM (SELECT 1 UNION SELECT 2.5) AS s UNION SELECT 'x'")[0];

        self::assertInstanceOf(ResultSet::class, $nested);
        self::assertInstanceOf(ResultSet::class, $parenthesized);
        self::assertInstanceOf(ResultSet::class, $derived);
        self::assertSame([[['1'], ['2.5'], ['x']], [['1'], ['2.5'], ['x']], [['1.0'], ['2.5'], ['x']]], [$nested->rows, $parenthesized->rows, $derived->rows]);
    }
}
