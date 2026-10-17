<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\Assignments;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;

#[CoversClass(Assignments::class)]
#[Small]
final class AssignmentsTest extends TestCase
{
    public function testPositionFindsTheColumnAnAssignmentWrites(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, c INT)');
        $operation = $session->analyze('UPDATE t SET c = 1, A = 2');
        $statement = $operation->statement;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $table = $session->instance->dictionary->table('d', 't');

        self::assertInstanceOf(Update::class, $statement);
        self::assertNotNull($table);
        self::assertSame([2, 0], [(new Assignments($planner, $table))->position($statement->assignments[0]->column), (new Assignments($planner, $table))->position($statement->assignments[1]->column)]);
    }

    public function testPositionRefusesAColumnOfAnotherTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (x INT)');
        $operation = $session->analyze('UPDATE t, u SET u.x = 1');
        $statement = $operation->statement;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $table = $session->instance->dictionary->table('d', 't');

        self::assertInstanceOf(Update::class, $statement);
        self::assertNotNull($table);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'x' in 'field list'");

        (new Assignments($planner, $table))->position($statement->assignments[0]->column);
    }
}
