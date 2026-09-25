<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TableFromQuery;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableFromQuery::class)]
#[Medium]
final class TableFromQueryTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE SELECT a FROM u'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE AS SELECT a FROM u'])]
    #[TestWith(['mysql-8.0.44', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE AS TABLE u'])]
    #[TestWith(['mysql-8.1.0', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE SELECT a FROM u'])]
    #[TestWith(['mysql-8.2.0', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE SELECT a FROM u'])]
    #[TestWith(['mysql-8.3.0', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE SELECT a FROM u'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE AS TABLE u'])]
    #[TestWith(['mysql-9.0.1', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE SELECT a FROM u'])]
    #[TestWith(['mysql-9.1.0', 'CREATE TABLE t (c INT, KEY k (c)) IGNORE AS TABLE u'])]
    public function testBindReadsTheDeclarationTheIndexesAndTheQueryOnEveryRelease(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE u (a INT)')))->bind($sql);
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertSame('t', $statement->definition->table->name);
        self::assertSame('c', $statement->definition->table->columns[0]->name);
        self::assertSame('k', $statement->indexes[0]->definition->name);
        self::assertSame(DuplicateRows::Ignore, $statement->duplicates);
        self::assertSame('a', $statement->query->resultColumns()[0]->name);
        self::assertFalse($statement->ifNotExists);
        self::assertSame($sql, $statement->toString());
    }

    public function testBindReadsIfNotExists(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE IF NOT EXISTS t (c INT) SELECT 1 AS c');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertNull($statement->duplicates);
    }

    #[TestWith(['CREATE TABLE t (c INT) REPLACE SELECT 1 AS c', DuplicateRows::Replace])]
    #[TestWith(['CREATE TABLE t (c INT) ignore AS SELECT 1 AS c', DuplicateRows::Ignore])]
    #[TestWith(['CREATE TABLE t (c INT) AS SELECT 1 AS c', null])]
    public function testDuplicatesReadsThePolicyKeyword(string $sql, ?DuplicateRows $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertSame($expected, TableFromQuery::duplicates($statement->source));
    }

    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT) SELECT a FROM u LOCK IN SHARE MODE', 'CREATE TABLE `x`(`c` integer) AS SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT) AS SELECT a FROM u FOR SHARE OF u SKIP LOCKED', 'CREATE TABLE `x`(`c` integer) AS SELECT `a` AS `a` FROM `u` FOR SHARE OF `u` SKIP LOCKED'])]
    #[TestWith(['mysql-9.1.0', 'CREATE TABLE x (c INT) (SELECT a FROM (SELECT a FROM u) d FOR UPDATE)', 'CREATE TABLE `x`(`c` integer) AS SELECT `a` AS `a` FROM(SELECT `a` AS `a` FROM `u`) AS `d` FOR UPDATE'])]
    public function testQueryKeepsTheLockingClauseOfTheInputQuery(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT) SELECT a FROM u FOR UPDATE'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT) AS SELECT a FROM u FOR UPDATE OF u NOWAIT'])]
    #[TestWith(['mysql-9.1.0', 'CREATE TABLE x (c INT) AS SELECT a FROM (SELECT a FROM u FOR UPDATE) d'])]
    public function testQueryRejectsAnInputQueryThatLocksAStoredTableForUpdate(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE u (a INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::TableCreationLock->message());
        $binder->bind($sql);
    }
}
