<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Activation;
use MySqlMemory\Program\Row;
use MySqlMemory\Program\Statements;
use MySqlMemory\Program\Variable;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Statements::class)]
#[Small]
final class StatementsTest extends TestCase
{
    public function testOperationResolvesANameToAVariableBeforeAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 10)');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 99; SELECT a, t.a FROM t; END');

        $result1 = $session->query('CALL p()')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['99', '1']], $result1->rows);
    }

    public function testRunKeepsTheResultSetOfAQueryOfAProcedure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(Select::class, $select);

        $reply = (new Statements($session, $activation))->run($select);

        self::assertSame([true, 1], [$reply instanceof ResultSet, count($activation->results)]);
    }

    public function testRunRefusesAResultSetFromAFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE q() SELECT 'inner'");
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN CALL q(); RETURN 1; END');

        $this->expectExceptionCode(1415);
        $this->expectExceptionMessage('Not allowed to return a result set from a function');

        $session->query('SELECT f()');
    }

    public function testValueStoresTheValueAssignedToAVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE v INT; SET v = 'abc'; END");

        $this->expectExceptionCode(1366);
        $this->expectExceptionMessage("Incorrect integer value: 'abc' for column 'v' at row 1");

        $session->query('CALL p()');
    }

    public function testTruthReadsAConditionOverTheVariablesInScope(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $activation->scope[] = new Row(new ParameterList([]), [new Variable('x', Domain::integer(), 3)]);
        $session->program = $activation;
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT x > 2, x IS NULL')->statement;
        self::assertInstanceOf(Select::class, $select);
        $statements = new Statements($session, $activation);

        [$first, $second] = $select->items;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $first);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $second);

        self::assertSame([true, false], [$statements->truth($first->expression), $statements->truth($second->expression)]);
    }
}
