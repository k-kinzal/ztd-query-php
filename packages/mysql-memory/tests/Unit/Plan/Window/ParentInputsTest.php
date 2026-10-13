<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\ParentInputs;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(ParentInputs::class)]
#[Small]
final class ParentInputsTest extends TestCase
{
    public function testPlanCapturesGroupedInputsBeforeWindowParentsAssignThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n+0*ROW_NUMBER() OVER () AS v FROM t GROUP BY n')[0];
        $state = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $state);

        self::assertSame([['1'], ['2'], ['3']], $result->rows);
        self::assertSame([['3']], $state->rows);
    }

    public function testPlanCapturesUnrelatedReadsBeforeEvaluatingWindowParentsForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n+0*ROW_NUMBER() OVER () AS v,@x FROM t ORDER BY v DESC LIMIT 2')[0];
        $state = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $state);

        self::assertSame([['6', '3'], ['3', '1']], $result->rows);
        self::assertSame([['3']], $state->rows);
    }

    public function testEmittedPublishesOnlyRetainedSortedAssignmentValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n+0*ROW_NUMBER() OVER () AS v FROM t GROUP BY n WITH ROLLUP ORDER BY v DESC LIMIT 2')[0];
        $state = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $state);

        self::assertSame([['3'], ['2']], $result->rows);
        self::assertSame([['2']], $state->rows);
        self::assertSame('<temporary>', $result->columns[0]->table);
    }

    public function testPlanKeepsDeferredAggregateParentsSeparateFromDistinctColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $stored = $session->query('SELECT DISTINCT @x:=@x+1+0*MIN(MIN(n)) OVER () AS v FROM t GROUP BY n')[0];
        $session->query('SET @x=0');
        $deferred = $session->query('SELECT DISTINCT @x:=@x+1+0*MIN(MIN(n)) OVER () AS v FROM t GROUP BY n WITH ROLLUP')[0];
        self::assertInstanceOf(ResultSet::class, $stored);
        self::assertInstanceOf(ResultSet::class, $deferred);

        self::assertSame([['1']], $stored->rows);
        self::assertSame([['1'], ['1'], ['1'], ['1']], $deferred->rows);
        self::assertSame(['<temporary>', ''], [$stored->columns[0]->table, $deferred->columns[0]->table]);
    }

    public function testPlanRetainsBlobFlagsOnStoredJsonAssignments(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2)');
        $result = $session->query("SELECT @x:=FIRST_VALUE(CAST('[1]' AS JSON)) OVER () FROM t GROUP BY n")[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['[1]'], ['[1]']], $result->rows);
        self::assertSame('<temporary>', $result->columns[0]->table);
        self::assertSame(16, $result->columns[0]->flags & 16);
    }

    public function testVisitExcludesAssignmentTargetsAndWindowAggregateAndSubqueryArguments(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT @target:=@read+SUM(@aggregate)+SUM(@window) OVER ()+(SELECT @subquery)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        $planner = new Planner($operation->statement->query, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $variables = [];
        (new ParentInputs($planner))->visit((new Windowing($planner))->roots($operation->statement->query)[0], $variables);

        self::assertSame(['read'], array_values(array_map(static fn ($variable): string => $variable->name->value, $variables)));
    }
}
