<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Projection\Ordering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Ordering::class)]
#[Small]
final class OrderingTest extends TestCase
{
    public function testKeysPlacesAKeyOutsideTheSelectListAfterItAndLeavesOutAConstantKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT a AS x FROM t ORDER BY x DESC, b, 1 + 1');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->from);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope(null);
        $planner->relations->plan($statement->from, $scope);
        $fields = $planner->blocks->fields($statement);
        $expression = $planner->compiler->names->field($fields[0], $scope);

        [$keys, $expressions] = (new Ordering($planner))->keys($statement, $fields, [$expression->domain()], [$expression], $scope);

        self::assertSame([[0, true], [1, false], 2], [[$keys[0][0], $keys[0][2]], [$keys[1][0], $keys[1][2]], count($expressions)]);
        self::assertCount(2, $keys);
    }

    public function testEvaluationReadsLegacyAliasesWithoutRepeatingAssignments(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(a INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+a AS v FROM t ORDER BY ABS(v)')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1'], ['3'], ['6']], $result->rows);
        self::assertSame(6, $session->variables->user('x')[0]);
    }
}
