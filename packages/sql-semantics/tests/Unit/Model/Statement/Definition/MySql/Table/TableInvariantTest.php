<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Table\TableInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableInvariant::class)]
#[Medium]
final class TableInvariantTest extends TestCase
{
    public function testTableAcceptsAPhysicalTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        TableInvariant::table($statement->origin, $statement->from);
        self::assertSame('t', $statement->from->declaration->name);
    }

    public function testTableRejectsAnAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT * FROM t AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        TableInvariant::table($statement->origin, $statement->from);
    }

    public function testDialectRejectsSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        TableInvariant::dialect($statement->origin);
    }

    public function testReleaseReadsTheSnapshotGrammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1');
        self::assertSame('mysql-5.7.44', TableInvariant::release($statement->origin));
    }

    public function testModernRejectsLegacyReleases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        TableInvariant::modern($statement->origin, 'SECONDARY_LOAD');
    }

    public function testModernAcceptsCurrentReleases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1');
        TableInvariant::modern($statement->origin, 'SECONDARY_LOAD');
        self::assertSame('mysql-8.4.7', TableInvariant::release($statement->origin));
    }

    public function testSince57RejectsMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        TableInvariant::since57($statement->origin, 'RENAME INDEX');
    }

    public function testSince57AcceptsMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1');
        TableInvariant::since57($statement->origin, 'RENAME INDEX');
        self::assertSame('mysql-5.7.44', TableInvariant::release($statement->origin));
    }

    public function testOnlyRejectsAnUnlistedRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        TableInvariant::only($statement->origin, ['mysql-5.6.51'], 'ALTER IGNORE TABLE');
    }
}
