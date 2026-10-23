<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Plan\Window\SiblingInputs::class)]
#[Small]
final class SiblingInputsTest extends TestCase
{
    public function testRootsSelectsOrdinaryVariableOutputsBesideAnAssignment(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT @x:=1, @x, @x+ROW_NUMBER() OVER ()');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement->query);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement->query, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $inputs = new \MySqlMemory\Plan\Window\SiblingInputs($planner);

        self::assertCount(2, $inputs->roots($operation->statement->query));
    }

    public function testNodesCapturesWindowArgumentsAndParentsBeforeOrdinaryAssignments(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT @x:=@x+1, FIRST_VALUE(@x:=@x+10) OVER ()+@x');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement->query);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement->query, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $inputs = new \MySqlMemory\Plan\Window\SiblingInputs($planner);

        self::assertCount(2, $inputs->nodes($operation->statement->query));
    }

    public function testVariablesExcludesEffectsAlreadyConsumedByAnAggregate(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT SUM(@x:=@x+1),ROW_NUMBER() OVER ()');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement->query);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement->query, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $inputs = new \MySqlMemory\Plan\Window\SiblingInputs($planner);

        self::assertSame([], $inputs->roots($operation->statement->query));
    }

    public function testGroupedRetainsThePreviousOrdinaryValuesForRollupRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+1 AS v,SUM(@x) OVER () AS w FROM t GROUP BY n WITH ROLLUP')[0];
        $state = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $state);

        self::assertSame([['1','5'],['2','5'],['3','5'],['3','5']], $result->rows);
        self::assertSame([['3']], $state->rows);
    }

    public function testStoredRestoresTheCapturedFieldWithoutReevaluatingTheAssignment(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement->query);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement->query, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $domain = \MySqlMemory\Typing\Domain::integer();
        $assignment = new \MySqlMemory\Evaluation\Leaf\Assignment('x', new \MySqlMemory\Evaluation\Leaf\Constant($domain, 99), $domain, $domain);
        $read = (new \MySqlMemory\Plan\Window\SiblingInputs($planner))->stored($assignment, 0, true);
        $frame = new \MySqlMemory\Evaluation\Frame($planner->compiler->connection->context, [7]);

        self::assertSame(7, $read->evaluate($frame));
        self::assertSame(7, $session->variables->user('x')[0]);
    }

    public function testStreamedPublishesOnlyTheReturnedAssignmentAfterAnOffset(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+10+0*ROW_NUMBER() OVER () AS w,@x:=@x+1 AS v FROM t LIMIT 1 OFFSET 1')[0];
        $state = $session->query('SELECT @x')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $state);

        self::assertSame([['20','11']], $result->rows);
        self::assertSame([['11']], $state->rows);
    }

    public function testRootsKeepsAggregateParentsOutsideRetainedSiblingFields(): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;CREATE TABLE t(n INT,d DATE);INSERT INTO t VALUES(1,'2001-01-02'),(2,'2001-01-03');SET @x=0");
        $result = $session->query('SELECT @x:=MIN(d),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP')[0];
        $variable = $session->query('SELECT @x,CHARSET(@x)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $variable);
        self::assertSame([['2001-01-02', '1'], ['2001-01-03', '2'], ['2001-01-02', '3']], $result->rows);
        self::assertSame([['2001-01-02', 'latin1']], $variable->rows);
    }

}
