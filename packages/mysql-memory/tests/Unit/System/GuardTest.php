<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Guard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Guard::class)]
#[Small]
final class GuardTest extends TestCase
{
    public function testCheckDeniesDefiningATableInInformationSchema(): void
    {
        $s = (new Instance())->connect();

        $this->expectExceptionMessage("Access denied for user 'root'@'%' to database 'information_schema'");
        $s->query('CREATE TABLE information_schema.x (a INT)');
    }

    public function testCheckLetsAQueryReadASystemTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('CREATE TABLE d.t AS SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');

        $result1 = $s->query('SELECT COUNT(*) FROM d.t')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['5']], $result1->rows);
    }

    public function testWrittenAnswersOnlyTheBoundWriteTargets(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $insert = $s->analyze('INSERT INTO t VALUES (1)');
        $update = $s->analyze('UPDATE t JOIN u ON t.a=u.a SET t.a=1');
        $create = $s->analyze('CREATE TABLE v(a INT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $insert);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $update);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $create);
        self::assertSame([['t'], ['t'], []], array_map(static fn (\SqlSemantics\Statement\Operation $operation): array => array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->written($operation->statement, $operation->facts)), [$insert, $update, $create]));
    }

    public function testCheckAllowsASystemTableToSupplyAJoinedUpdateInput(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(x INT); INSERT INTO d.t VALUES(0)');
        $session->query('UPDATE d.t AS a, information_schema.TABLES AS a SET a.x=1');
        $result = $session->query('SELECT x FROM d.t')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testCheckStillRejectsAnAssignmentBoundToTheSystemInput(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(x INT)');
        $this->expectExceptionCode(1044);
        $session->query("UPDATE d.t AS a, information_schema.TABLES AS s SET s.TABLE_NAME='x'");
    }

    public function testDefinedAnswersTheTablesCreatedChangedRenamedOrLocked(): void
    {
        $s = (new Instance())->connect();
        $create = $s->analyze('CREATE TABLE t (a INT)');
        $rename = $s->analyze('RENAME TABLE a TO b');
        $lock = $s->analyze('LOCK TABLES t READ');
        $select = $s->analyze('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $create);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $rename);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $lock);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $select);
        self::assertSame([['t'], ['a', 'b'], ['t'], []], [array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($create->statement)), array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($rename->statement)), array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($lock->statement)), (new Guard())->defined($select->statement)]);
    }

    public function testSchemaAnswersTheDatabaseOfAName(): void
    {
        $s = (new Instance())->connect(database: 'mysql');

        self::assertSame(['mysql', 'sys'], [(new Guard())->schema(new QualifiedName(new Name('t')), $s), (new Guard())->schema(new QualifiedName(new Name('t'), new Name('sys')), $s)]);
    }

    public function testVerbAnswersTheCommandAWriteIsDeniedAs(): void
    {
        $s = (new Instance())->connect();
        $update = $s->analyze('UPDATE t SET a = 1');
        $insert = $s->analyze('INSERT INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $update);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $insert);
        self::assertSame(['UPDATE', 'INSERT'], [(new Guard())->verb($update->statement), (new Guard())->verb($insert->statement)]);
    }
}
