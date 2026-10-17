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

    public function testCheckDeniesWritingAPerformanceSchemaTable(): void
    {
        $s = (new Instance())->connect();

        $this->expectExceptionMessage("DELETE command denied to user 'root'@'localhost' for table 'global_variables'");
        $s->query('DELETE FROM performance_schema.global_variables');
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

    public function testWrittenAnswersTheTargetsAndTheTablesOfAnUpdate(): void
    {
        $s = (new Instance())->connect();

        self::assertSame([['t'], ['t', 'u'], []], [array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->written($s->analyze('INSERT INTO t VALUES (1)')->statement)), array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->written($s->analyze('UPDATE t JOIN u ON t.a = u.a SET t.a = 1')->statement)), (new Guard())->written($s->analyze('CREATE TABLE t (a INT)')->statement)]);
    }

    public function testDefinedAnswersTheTablesCreatedChangedRenamedOrLocked(): void
    {
        $s = (new Instance())->connect();

        self::assertSame([['t'], ['a', 'b'], ['t'], []], [array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($s->analyze('CREATE TABLE t (a INT)')->statement)), array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($s->analyze('RENAME TABLE a TO b')->statement)), array_map(static fn (QualifiedName $name): string => $name->name->value, (new Guard())->defined($s->analyze('LOCK TABLES t READ')->statement)), (new Guard())->defined($s->analyze('SELECT 1')->statement)]);
    }

    public function testSchemaAnswersTheDatabaseOfAName(): void
    {
        $s = (new Instance())->connect(database: 'mysql');

        self::assertSame(['mysql', 'sys'], [(new Guard())->schema(new QualifiedName(new Name('t')), $s), (new Guard())->schema(new QualifiedName(new Name('t'), new Name('sys')), $s)]);
    }

    public function testVerbAnswersTheCommandAWriteIsDeniedAs(): void
    {
        $s = (new Instance())->connect();

        self::assertSame(['UPDATE', 'INSERT'], [(new Guard())->verb($s->analyze('UPDATE t SET a = 1')->statement), (new Guard())->verb($s->analyze('INSERT INTO t VALUES (1)')->statement)]);
    }
}
