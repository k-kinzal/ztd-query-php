<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Running;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Running::class)]
#[Small]
final class RunningTest extends TestCase
{
    public function testUsedAnswersTheRunningAStatementReadsAndWrites(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t SELECT * FROM e.u')->statement;

        self::assertSame(['e.u', 'd.t'], (new Running())->used($statement, 'd'));
    }

    public function testCheckRefusesAWriteOfATableTheInvokingStatementUses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY)');
        $session->query('CREATE FUNCTION f(x INT) RETURNS INT DETERMINISTIC BEGIN INSERT INTO t VALUES (x); RETURN x; END');

        $this->expectExceptionCode(1442);
        $this->expectExceptionMessage("Can't update table 't' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.");

        $session->query('INSERT INTO t VALUES (f(20))');
    }

    public function testKeyQualifiesANameWithTheCurrentDatabase(): void
    {
        self::assertSame(['d.t', 'e.u'], [(new Running())->key(new QualifiedName(new Name('T')), 'D'), (new Running())->key(new QualifiedName(new Name('u'), new Name('e')), 'd')]);
    }
}
