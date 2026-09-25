<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(CreateTableFromQueryStatement::class)]
#[Medium]
final class CreateTableFromQueryStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsDeclaredColumnsTogetherWithTheInputQuery(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE u (a INT)'));
        $sql = 'create table if not exists t (id int primary key, key k (id)) engine = InnoDB replace select a from u';
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame(['id'], array_map(static fn ($column): string => $column->name, $statement->definition->table->columns));
        self::assertCount(1, $statement->indexes);
        self::assertTrue($statement->ifNotExists);
        self::assertSame(DuplicateRows::Replace, $statement->duplicates);
        self::assertSame('a', $statement->query->resultColumns()[0]->name);
        self::assertSame($sql, $statement->toString());
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('CREATE TABLE IF NOT EXISTS `t`(`id` integer NOT NULL, PRIMARY KEY(`id`), INDEX `k`(`id`)) ENGINE `InnoDB` REPLACE AS SELECT `a` AS `a` FROM `u`', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testKeepsTableOptionsAndPartitioningBeforeTheQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind('CREATE TABLE t ( c BIT ) STORAGE DISK PARTITION BY KEY ( ) IGNORE AS TABLE u');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        $properties = $statement->definition->table->properties;
        self::assertInstanceOf(\SqlSemantics\Schema\Table\MySqlProperties::class, $properties);
        self::assertSame(\SqlSemantics\Schema\Table\TableStorage::Disk, $properties->storage);
        self::assertNotNull($properties->partitioning);
        self::assertSame(DuplicateRows::Ignore, $statement->duplicates);
        self::assertSame('CREATE TABLE `t`(`c` bit) STORAGE DISK PARTITION BY KEY() IGNORE AS TABLE `u`', (new SimpleSerializer())->serialize($statement));
    }

    public function testWithQueryReplacesTheInputQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind('CREATE TABLE t (c INT) SELECT a FROM u');
        $query = $binder->bind('SELECT 2 AS b');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        $changed = $statement->withQuery($query);
        self::assertNotSame($statement, $changed);
        self::assertSame('CREATE TABLE `t`(`c` integer) AS SELECT 2 AS `b`', $changed->toString());
        self::assertSame('CREATE TABLE t (c INT) SELECT a FROM u', $statement->toString());
    }

    public function testWithDuplicatesReplacesThePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c INT) IGNORE SELECT 1 AS c');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertSame(DuplicateRows::Replace, $statement->withDuplicates(DuplicateRows::Replace)->duplicates);
        self::assertNull($statement->withDuplicates(null)->duplicates);
        self::assertSame('CREATE TABLE `t`(`c` integer) AS SELECT 1 AS `c`', $statement->withDuplicates(null)->toString());
        self::assertSame(DuplicateRows::Ignore, $statement->duplicates);
    }

    public function testWithDefinitionReplacesTheDeclaredTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE TABLE t (c INT) SELECT 1 AS c');
        $other = $binder->bind('CREATE TABLE t (d BIGINT, KEY k (d))');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $other);
        $changed = $statement->withDefinition($other->definition, $other->indexes);
        self::assertSame('CREATE TABLE `t`(`d` bigint, INDEX `k`(`d`)) AS SELECT 1 AS `c`', $changed->toString());
        self::assertCount(1, $changed->indexes);
        self::assertSame('c', $statement->definition->table->columns[0]->name);
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c INT) REPLACE SELECT 1 AS c');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->definition, $copy->definition);
        self::assertSame($statement->query, $copy->query);
        self::assertSame($statement->duplicates, $copy->duplicates);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c INT) SELECT 1 AS c');
        $other = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTableFromQueryStatement($other->origin, $statement->definition, $statement->query);
    }

    public function testRejectsADeclarationWithoutColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c INT) SELECT 1 AS c');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        $table = $statement->definition->table;
        $empty = new \SqlSemantics\Model\Definition\TableDeclaration(new \SqlSemantics\Schema\TableDefinition($table->schema, $table->name, [], [], $table->source, $table->resolved, [], $table->properties));
        $this->expectException(InvalidStructure::class);
        new CreateTableFromQueryStatement($statement->origin, $empty, $statement->query);
    }

    public function testRejectsColumnsOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c INT) SELECT 1 AS c');
        $foreign = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (c integer)');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $foreign);
        $this->expectException(InvalidStructure::class);
        new CreateTableFromQueryStatement($statement->origin, $foreign->definition, $statement->query);
    }

    public function testRejectsAnInputQueryThatLocksAStoredTableForUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE u (a INT)'));
        $statement = $binder->bind('CREATE TABLE t (c INT) SELECT a FROM u LOCK IN SHARE MODE');
        $locked = $binder->bind('SELECT a FROM u WHERE a IN (SELECT a FROM u FOR UPDATE)');
        self::assertInstanceOf(CreateTableFromQueryStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $locked);
        self::assertSame('CREATE TABLE `t`(`c` integer) AS SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE', (new SimpleSerializer())->serialize($statement));
        $this->expectException(InvalidStructure::class);
        new CreateTableFromQueryStatement($statement->origin, $statement->definition, $locked);
    }
}
