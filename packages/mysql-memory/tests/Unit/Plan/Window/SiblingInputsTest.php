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
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $inputs = new \MySqlMemory\Plan\Window\SiblingInputs($planner);

        self::assertCount(2, $inputs->roots($operation->statement));
    }

    public function testNodesCapturesWindowArgumentsAndParentsBeforeOrdinaryAssignments(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT @x:=@x+1, FIRST_VALUE(@x:=@x+10) OVER ()+@x');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $inputs = new \MySqlMemory\Plan\Window\SiblingInputs($planner);

        self::assertCount(2, $inputs->nodes($operation->statement));
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
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->statement);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
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

}
