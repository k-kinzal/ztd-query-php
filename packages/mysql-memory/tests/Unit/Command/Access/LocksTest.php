<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\Locks;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Locks::class)]
#[Small]
final class LocksTest extends TestCase
{
    public function testCheckRefusesATableThatIsNotLocked(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1100);
        $this->expectExceptionMessage("Table 'u' was not locked with LOCK TABLES");

        $session->query('SELECT * FROM t, u');
    }

    public function testCheckRefusesAWriteUnderAReadLock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1099);
        $this->expectExceptionMessage("Table 't' was locked with a READ lock and can't be updated");

        $session->query('INSERT INTO t VALUES (1)');
    }

    public function testCheckAllowsACommonTableExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t WRITE; INSERT INTO t VALUES (1)');

        $result = $session->query('WITH c AS (SELECT a FROM t) SELECT * FROM c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testWrittenAnswersTheTablesOfAnUpdate(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('UPDATE t SET a = 1')->statement;

        self::assertCount(1, (new Locks())->written($statement));
    }

    public function testAllowedRefusesATableUnderAnotherName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Table 'x' was not locked with LOCK TABLES");

        (new Locks())->allowed($session, new QualifiedName(new Name('t')), 'x', [], false);
    }
}
